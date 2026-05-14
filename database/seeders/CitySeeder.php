<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        // Dataset local offline-first
        $path = database_path('data/municipios.json');

        if (! file_exists($path)) {
            Log::error("Arquivo de municípios não encontrado em: $path");
            $this->command->error('Arquivo de municípios não encontrado!');

            return;
        }

        $json = file_get_contents($path);
        $citiesData = json_decode($json, true);

        if (empty($citiesData)) {
            Log::error('JSON de municípios vazio ou inválido.');

            return;
        }

        $cities = collect($citiesData)
            ->map(function ($city) {
                // O código IBGE no dataset do Kelvin tem 7 dígitos, igual ao que usamos
                $ibgeCode = $city['codigo_ibge'];
                $lat = $city['latitude'];
                $lng = $city['longitude'];

                return [
                    'ibge_code' => $ibgeCode,
                    'name' => $city['nome'],
                    'uf' => $this->getUF($city['codigo_uf']),
                    'location' => DB::raw("ST_GeogFromText('POINT($lng $lat)')"),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values();

        $this->command->info('Populando '.$cities->count().' cidades com coordenadas reais...');

        foreach ($cities->chunk(500) as $chunk) {
            City::insert($chunk->toArray());
        }
    }

    private function getUF(int $code): string
    {
        $ufs = [
            11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
            21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL', 28 => 'SE', 29 => 'BA',
            31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP',
            41 => 'PR', 42 => 'SC', 43 => 'RS',
            50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF',
        ];

        return $ufs[$code] ?? '??';
    }
}
