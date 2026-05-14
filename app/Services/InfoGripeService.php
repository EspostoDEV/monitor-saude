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
        $path = ($disease === 'srag') ? "/api/v1/dashboard/alertcity" : "/api/v1/alertcity";
        
        $year = now()->year;

        $response = Http::withOptions(['verify' => true]) // SSL Habilitado (PRD Compliance)
            ->connectTimeout(15)
            ->timeout(30)
            ->retry(3, 200) // Mais resiliência para a Fiocruz
            ->get("{$host}{$path}", [
                'geocode' => $ibgeCode,
                'disease' => $disease,
                'format' => 'json',
                'ew_start' => 1,
                'ey_start' => $year,
                'ew_end' => 53,
                'ey_end' => $year,
            ]);

        return $response->json() ?? [];
    }
}