<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfoDengueService
{
    private const BASE_URL = 'https://info.dengue.mat.br/api/alertcity';

    /**
     * Fetch epidemic data for a specific city and disease.
     *
     * @param int $ibgeCode 7-digit IBGE code
     * @param string $disease dengue, zika, or chikungunya
     * @param int|null $yearStart
     * @param int|null $weekStart
     * @param int|null $yearEnd
     * @param int|null $weekEnd
     * @return array
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
        $weekEnd = $weekEnd ?? 53; // Covers the whole year by default

        try {
            $response = Http::timeout(30)
                ->retry(3, 100)
                ->get(self::BASE_URL, [
                    'geocode' => $ibgeCode,
                    'disease' => $disease,
                    'format' => 'json',
                    'ew_start' => $weekStart,
                    'ey_start' => $yearStart,
                    'ew_end' => $weekEnd,
                    'ey_end' => $yearEnd,
                ]);

            if ($response->failed()) {
                Log::error("InfoDengue API failed for city {$ibgeCode}: " . $response->body());
                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("Error fetching InfoDengue data: " . $e->getMessage());
            return [];
        }
    }
}
