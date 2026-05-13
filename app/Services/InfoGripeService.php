<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class InfoGripeService
{
    private const HOSTS = [
        'srag' => 'https://infogripe.fiocruz.br',
        'dengue' => 'https://info.dengue.mat.br',
        'chikungunya' => 'https://info.dengue.mat.br',
        'zika' => 'https://info.dengue.mat.br',
    ];

    public function fetch(int $ibgeCode, string $disease = 'srag'): array
    {
        $host = self::HOSTS[$disease] ?? self::HOSTS['srag'];

        // Fiocruz requires /dashboard/ path for alertcity
        $path = ($disease === 'srag') ? "/api/v1/dashboard/alertcity" : "/api/v1/alertcity";

        $response = Http::withoutVerifying()
            ->connectTimeout(15)
            ->timeout(30)
            ->retry(2, 100)
            ->get("{$host}{$path}", [
                'geocode' => $ibgeCode,
                'disease' => $disease,
                'format' => 'json',
                'ew_start' => 1,
                'ey_start' => 2025,
                'ew_end' => 53,
                'ey_end' => 2025,
            ]);

        return $response->json() ?? [];
    }
}