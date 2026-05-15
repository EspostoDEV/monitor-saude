<?php

namespace App\Services;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Models\SyncSession;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthDataService
{
    protected InfoDengueService $dengueService;

    protected InfoGripeService $gripeService;

    protected RiskEngineService $riskService;

    public function __construct(
        InfoDengueService $dengueService,
        InfoGripeService $gripeService,
        RiskEngineService $riskService
    ) {
        $this->dengueService = $dengueService;
        $this->gripeService = $gripeService;
        $this->riskService = $riskService;
    }

    /**
     * Sincroniza dados epidemiológicos.
     */
    public function sync(string $disease, ?int $ibgeCode = null, ?string $uf = null, ?string $sessionId = null): int
    {
        $service = $this->getService($disease);
        $query = City::query();

        if ($ibgeCode) {
            $query->where('ibge_code', $ibgeCode);
        }

        if ($uf) {
            $query->where('uf', $uf);
        }

        $cities = $query->get();
        $totalRecords = 0;

        foreach ($cities->chunk(10) as $chunk) {
            $responses = Http::pool(fn ($pool) => $chunk->map(
                fn ($city) => $pool->as($city->ibge_code)
                    ->withOptions(['verify' => true])
                    ->get($service->getUrl($disease), [
                        'geocode' => $city->ibge_code,
                        'disease' => $service->getApiDiseaseName($disease),
                        'format' => 'json',
                        'ew_start' => 1,
                        'ey_start' => now()->year,
                        'ew_end' => 53,
                        'ey_end' => now()->year,
                    ])
            ));

            foreach ($chunk as $city) {
                $response = $responses[$city->ibge_code];

                if ($response instanceof Response && $response->successful()) {
                    $totalRecords += $this->persistData($city, $disease, $response->json());
                } else {
                    Log::error("Falha ao sincronizar cidade {$city->ibge_code}: ".($response?->body() ?? 'No response'));
                }
            }

            if ($sessionId) {
                $this->updateSessionProgress($sessionId, $chunk->count());
            }
        }

        if ($sessionId) {
            $this->finalizeSession($sessionId);
        }

        return $totalRecords;
    }

    protected function getService(string $disease): object
    {
        return str_contains($disease, 'gripe') ? $this->gripeService : $this->dengueService;
    }

    protected function persistData(City $city, string $disease, array $data): int
    {
        $count = 0;

        foreach ($data as $record) {
            $year = $record['year'] ?? $record['epi_year'] ?? null;
            $week = $record['epi_week'] ?? null;

            if (! $year || ! $week) {
                continue;
            }

            $currentCases = (int) ($record['casos'] ?? 0);

            // População vem da API (campo 'pop'). Fallback para 1 para evitar divisão por zero.
            $population = (int) ($record['pop'] ?? 1);

            $incidence = ($currentCases / $population) * 100000;
            $updatedAt = isset($record['versao_modelo']) ? Carbon::parse($record['versao_modelo']) : now();

            // Calcula o nível básico para a tabela base
            $level = $this->riskService->getAlertLevel($incidence, $currentCases, 'stable');

            EpidemicRecord::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'year' => $year,
                    'epi_week' => $week,
                    'disease_type' => $disease,
                ],
                [
                    'cases' => $currentCases,
                    'population' => $population,
                    'incidence' => $incidence,
                    'level' => $level,
                    'updated_at' => $updatedAt,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function updateSessionProgress(string $sessionId, int $processedCount): void
    {
        SyncSession::where('session_id', $sessionId)->increment('processed_cities', $processedCount);
    }

    protected function finalizeSession(string $sessionId): void
    {
        SyncSession::where('session_id', $sessionId)->update([
            'status' => 'finished',
            'completed_at' => now(),
        ]);
    }
}
