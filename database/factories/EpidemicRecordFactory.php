<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class EpidemicRecordFactory extends Factory
{
    protected $model = EpidemicRecord::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'disease_type' => $this->faker->randomElement(['dengue', 'chikungunya', 'zika']),
            'cases' => $this->faker->numberBetween(0, 500),
            'level' => $this->faker->numberBetween(1, 4),
            'incidence' => $this->faker->randomFloat(2, 0, 1000),
            'population' => $this->faker->numberBetween(10000, 1000000),
            'epi_week' => $this->faker->numberBetween(1, 52),
            'year' => 2026,
            'status' => 'synced',
        ];
    }
}
