<?php

namespace App\Services;

use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthDataService
{
    protected string $baseUrl = 'https://info.dengue.mat.br/api/alert/';

    /**
     * Sincroniza dados de uma doença para um município específico ou todos.
     */
    public function sync(string $disease, ?int $ibgeCode = null, ?string $uf = null): int
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

        foreach ($cities as $city) {
            $data = $this->fetchFromApi($disease, $city->ibge_code);
            
            if ($data) {
                $count += $this->persistData($city, $disease, $data);
            }
        }

        return $count;
    }

    /**
     * Busca dados na API do InfoDengue.
     */
    protected function fetchFromApi(string $disease, int $geocode): ?array
    {
        $year = now()->year; // 2026 no seu ambiente
        
        // Buscamos até a SE 17, que é a última confirmada com dados em 2026
        $params = [
            'disease' => $disease,
            'geocode' => $geocode,
            'format' => 'json',
            'ew_start' => 1,
            'ey_start' => $year,
            'ew_end' => 17,
            'ey_end' => $year,
        ];

        try {
            $response = Http::withOptions([
                'verify' => true,
            ])->timeout(30)->get($this->baseUrl, $params);

            if ($response->successful()) {
                return $response->json();
            }

            // Se for 404, apenas ignoramos silenciosamente (cidade ainda não processada em 2026)
            if ($response->status() === 404) {
                return null;
            }

            Log::warning("Falha na API InfoDengue para geocode {$geocode}: " . $response->status());
        } catch (\Exception $e) {
            Log::error("Erro de conexão com InfoDengue para geocode {$geocode}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Salva ou atualiza os registros no banco.
     */
    protected function persistData(City $city, string $disease, array $data): int
    {
        $inserted = 0;

        foreach ($data as $record) {
            // Mapeamento de campos da API para o nosso Schema
            // A API retorna campos como 'casos', 'nivel', 'incidencia', 're_inf', 're_sup', 'pop', 'epi_week', 'epi_year'
            
            $recordModel = EpidemicRecord::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'disease_type' => $disease,
                    'epi_week' => $record['epi_week'],
                    'year' => $record['epi_year'],
                ],
                [
                    'cases' => $record['casos'] ?? 0,
                    'level' => $record['nivel'] ?? 1,
                    'incidence' => $record['incidencia'] ?? 0,
                    're_inferior' => $record['re_inf'] ?? null,
                    're_superior' => $record['re_sup'] ?? null,
                    'population' => $record['pop'] ?? null,
                    'status' => 'synced',
                ]
            );

            // Forçamos a atualização do timestamp mesmo que os dados sejam idênticos
            $recordModel->touch();
            
            $inserted++;
        }

        return $inserted;
    }
}
