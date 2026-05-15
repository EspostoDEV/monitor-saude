<?php

namespace App\Services;

use App\Models\City;
use App\Models\EpidemicRecord;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthDataService
{
    protected InfoDengueService $dengueService;

    protected InfoGripeService $gripeService;

    public function __construct(InfoDengueService $dengueService, InfoGripeService $gripeService)
    {
        $this->dengueService = $dengueService;
        $this->gripeService = $gripeService;
    }

    public function sync(string $disease, ?int $ibgeCode = null, ?string $uf = null): array
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
        $chunks = $cities->chunk(10);
        $totalRecords = 0;
        $maxUpdatedAt = null;

        foreach ($chunks as $chunk) {
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
                    $data = $response->json();
                    $recordsSaved = $this->persistData($city, $disease, $data);
                    $totalRecords += $recordsSaved;

                    if ($recordsSaved > 0) {
                        $maxUpdatedAt = now();
                    }
                } else {
                    Log::error("Falha ao sincronizar cidade {$city->ibge_code}: ".($response?->body() ?? 'No response'));
                }
            }
        }

        return [
            'records_saved' => $totalRecords,
            'last_sync_at' => $maxUpdatedAt,
        ];
    }

    protected function getService(string $disease): object
    {
        return str_contains($disease, 'gripe') ? $this->gripeService : $this->dengueService;
    }

    protected function persistData(City $city, string $disease, array $data): int
    {
        $count = 0;

        foreach ($data as $record) {
            // Safety Guard: Ignora registros malformados ou incompletos
            if (! isset($record['year']) || ! isset($record['epi_week'])) {
                continue;
            }

            $currentCases = (int) ($record['casos'] ?? 0);

            // Freshness Tracking: Captura a versão do modelo ou data de atualização da Fiocruz
            $updatedAt = isset($record['versao_modelo'])
                ? Carbon::parse($record['versao_modelo'])
                : now();

            EpidemicRecord::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'year' => $record['year'],
                    'epi_week' => $record['epi_week'],
                    'disease_type' => $disease,
                ],
                [
                    'cases' => $currentCases,
                    'population' => $city->population,
                    'incidence' => ($currentCases / max($city->population, 1)) * 100000,
                    'updated_at' => $updatedAt,
                ]
            );
            $count++;
        }

        return $count;
    }
}
