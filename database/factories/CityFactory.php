<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'ibge_code' => $this->faker->unique()->numberBetween(1100000, 5399999),
            'name' => $this->faker->city(),
            'uf' => $this->faker->regexify('[A-Z]{2}'),
            // Restringindo coordenadas ao range do Brasil para maior fidelidade nos mapas/filtros espaciais
            'location' => DB::raw(sprintf(
                "ST_GeomFromText('POINT(%f %f)', 4326)",
                $this->faker->longitude(-73.9, -34.8),
                $this->faker->latitude(-33.7, 5.2)
            )),
        ];
    }
}
