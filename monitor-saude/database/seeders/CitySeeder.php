<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $response = Http::get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios');

        if ($response->failed()) {
            return;
        }

        $cities = collect($response->json())
            ->map(function ($city) {
                $uf = data_get($city, 'microrregiao.mesorregiao.UF.sigla');

                if (!$uf) {
                    return null;
                }

                return [
                    'ibge_code' => $city['id'],
                    'name' => $city['nome'],
                    'uf' => $uf,
                    'location' => DB::raw("ST_GeogFromText('POINT(0 0)')"),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->filter()
            ->values();

        foreach ($cities->chunk(500) as $chunk) {
            City::insert($chunk->toArray());
        }
    }
}