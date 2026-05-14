<?php

namespace App\Services;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Models\SyncLog;
use App\Models\SyncSession;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthDataService
{
    protected string $baseUrl = 'https://info.dengue.mat.br/api/alert/';

    protected RiskEngineService $riskService;

    public function __construct(RiskEngineService $riskService)
    {
        $this->riskService = $riskService;
    }

    /**
     * Sincroniza dados de uma doença para um município específico ou todos.
     */
    public function sync(string $disease, ?int $ibgeCode = null, ?string $uf = null, ?string $sessionId = null): int
    {
        $query = City::query();
        if ($ibgeCode) {
            $query->where('ibge_code', $ibgeCode);
        }
        if ($uf) {
            $query->where('uf', $uf);
        }

        $cities = $query->get();
        $count = 0;

        // Resolve datas dinâmicas com janela de consolidação (Issue 1, 5)
        // Usamos 2 semanas de lag pois os dados epidemiológicos demoram a ser notificados e validados
        $consolidationLag = 2;
        $now = Carbon::now();
        $targetDate = $now->subWeeks($consolidationLag);

        $currentYear = $targetDate->year;
        $currentWeek = $targetDate->weekOfYear;

        if ($currentWeek <= 0) {
            $currentYear--;
            $currentWeek = 52;
        }

        $chunks = $cities->chunk(10); // Aumentado para 10 pois agora temos melhor controle

        foreach ($chunks as $chunk) {
            try {
                $responses = Http::pool(fn ($pool) => $chunk->map(fn ($city) => $pool->as($city->ibge_code)->timeout(30)->get($this->baseUrl, [
                    'disease' => $disease,
                    'geocode' => $city->ibge_code,
                    'format' => 'json',
                    'ew_start' => 1,
                    'ey_start' => $currentYear,
                    'ew_end' => $currentWeek,
                    'ey_end' => $currentYear,
                ])
                )
                );

                foreach ($chunk as $city) {
                    $response = $responses[$city->ibge_code] ?? null;

                    if ($response instanceof Response && $response->successful()) {
                        $data = $response->json();
                        if ($data && is_array($data)) {
                            $processed = $this->persistData($city, $disease, $data);
                            $count += $processed;
                        }
                    } else {
                        // Log de erro específico por cidade (Issue 6)
                        $errorMsg = 'No response';
                        if ($response instanceof Response) {
                            $errorMsg = 'HTTP '.$response->status();
                        } elseif ($response instanceof \Throwable) {
                            $errorMsg = 'Exception: '.$response->getMessage();
                        }

                        Log::warning("Falha ao sincronizar cidade {$city->name} ({$city->ibge_code}): ".$errorMsg);
                    }
                }

                // Atualiza o progresso na sessão (Issue 1, 2, 8)
                if ($sessionId) {
                    $session = SyncSession::where('session_id', $sessionId)->first();

                    foreach ($chunk as $city) {
                        // Evita contar a mesma cidade duas vezes na mesma sessão (Idempotência)
                        $cacheKey = "sync_session_{$sessionId}_city_{$city->ibge_code}";
                        if (Cache::add($cacheKey, true, now()->addHours(2))) {
                            $session->increment('processed_cities');
                        }
                    }

                    $session->increment('total_records_found', $count);

                    // Adiciona log de progresso a cada 100 municípios (Menos spam no console)
                    if ($session->processed_cities % 100 === 0) {
                        SyncLog::create([
                            'session_id' => $sessionId,
                            'disease' => $disease,
                            'level' => 'info',
                            'message' => "Progresso: {$session->processed_cities} de {$session->total_cities} municípios processados ({$session->progress}%).",
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::error('Erro no chunk de sincronização: '.$e->getMessage());
                if ($sessionId) {
                    SyncSession::where('session_id', $sessionId)->update(['last_error' => $e->getMessage()]);
                    SyncLog::create([
                        'session_id' => $sessionId,
                        'disease' => $disease,
                        'level' => 'error',
                        'message' => 'Erro no processamento: '.$e->getMessage(),
                    ]);
                }
            }
        }

        if ($sessionId && $uf) {
            $session = SyncSession::where('session_id', $sessionId)->first();

            SyncLog::create([
                'session_id' => $sessionId,
                'disease' => $disease,
                'level' => 'success',
                'message' => "Lote da UF {$uf} processado. ({$session->processed_cities}/{$session->total_cities})",
            ]);

            // Verifica se é o encerramento total da sessão
            if ($session->processed_cities >= $session->total_cities && $session->status !== 'finished') {
                $session->update(['status' => 'finished', 'completed_at' => now()]);

                // Automação Roadmap 2.2: Atualiza a View Materializada e limpa cache de inteligência
                try {
                    Artisan::call('app:refresh-stats-view');

                    // Limpa chaves globais de inteligência (visão nacional)
                    $currentYear = now()->year;
                    Cache::forget("epi_intel_national_{$currentYear}_{$disease}");
                    Cache::forget('epi_intel_national_'.($currentYear - 1)."_{$disease}");

                } catch (\Exception $e) {
                    \Log::error('Falha na automação pós-sync: '.$e->getMessage());
                }

                SyncLog::create([
                    'session_id' => $sessionId,
                    'disease' => $disease,
                    'level' => 'success',
                    'message' => "🏁 Sincronização de {$disease} CONCLUÍDA! View Materializada e Cache atualizados.",
                ]);
            }
        }

        return $count;
    }

    protected function persistData(City $city, string $disease, array $data): int
    {
        $inserted = 0;
        foreach ($data as $record) {
            EpidemicRecord::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'disease_type' => $disease,
                    'epi_week' => $record['epiweek'] ?? $record['epi_week'],
                    'year' => $record['eyear'] ?? $record['epi_year'],
                ],
                [
                    'cases' => $record['casos'] ?? 0,
                    'level' => $this->riskService->getAlertLevel(
                        $record['incidencia'] ?? 0,
                        $record['casos'] ?? 0,
                        'stable' // Tendência não disponível no momento do sync unitário
                    ),
                    'incidence' => $record['incidencia'] ?? 0,
                    're_inferior' => $record['re_inf'] ?? null,
                    're_superior' => $record['re_sup'] ?? null,
                    'population' => $record['pop'] ?? null,
                    'status' => 'synced',
                ]
            );
            $inserted++;
        }

        return $inserted;
    }
}
