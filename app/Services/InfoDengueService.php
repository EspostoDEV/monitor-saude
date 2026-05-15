<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfoDengueService
{
    private const BASE_URL = 'https://info.dengue.mat.br/api/alertcity';

    public function getUrl(): string
    {
        return self::BASE_URL;
    }

    public function getApiDiseaseName(string $disease): string
    {
        return $disease; // Dengue, Zika, Chikungunya usam o mesmo nome na API
    }

    /**
     * Fetch epidemic data for a specific city and disease.
     */
    public function fetch(
        int $ibgeCode,
        string $disease = 'dengue',
        ?int $yearStart = null,
        ?int $weekStart = 1,
        ?int $yearEnd = null,
        ?int $weekEnd = null
    ): array {
        $yearStart = $yearStart ?? now()->year;
        $yearEnd = $yearEnd ?? now()->year;
        $weekEnd = $weekEnd ?? 53;

        try {
            $response = Http::withOptions(['verify' => true])
                ->timeout(30)
                ->retry(3, function (int $attempt) {
                    return (int) pow(2, $attempt - 1) * 100;
                }, throw: false)
                ->get($this->getUrl(), [
                    'geocode' => $ibgeCode,
                    'disease' => $this->getApiDiseaseName($disease),
                    'format' => 'json',
                    'ew_start' => $weekStart,
                    'ey_start' => $yearStart,
                    'ew_end' => $weekEnd,
                    'ey_end' => $yearEnd,
                ]);

            if ($response->failed()) {
                Log::error("InfoDengue API failed for city {$ibgeCode}: ".$response->body());

                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Error fetching InfoDengue data: '.$e->getMessage());

            return [];
        }
    }
}
