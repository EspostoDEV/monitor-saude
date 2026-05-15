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

    public function getUrl(string $disease = 'srag'): string
    {
        $host = self::HOSTS[$disease] ?? self::HOSTS['srag'];
        $path = ($disease === 'srag' || $disease === 'gripe') ? '/api/v1/dashboard/alertcity' : '/api/v1/alertcity';

        return "{$host}{$path}";
    }

    public function getApiDiseaseName(string $disease): string
    {
        return ($disease === 'gripe') ? 'srag' : $disease;
    }

    public function fetch(int $ibgeCode, string $disease = 'srag'): array
    {
        $year = now()->year;

        $response = Http::withOptions(['verify' => true])
            ->connectTimeout(15)
            ->timeout(30)
            ->retry(3, function (int $attempt) {
                return (int) pow(2, $attempt - 1) * 200;
            }, throw: false)
            ->get($this->getUrl($disease), [
                'geocode' => $ibgeCode,
                'disease' => $this->getApiDiseaseName($disease),
                'format' => 'json',
                'ew_start' => 1,
                'ey_start' => $year,
                'ew_end' => 53,
                'ey_end' => $year,
            ]);

        return $response->json() ?? [];
    }
}
