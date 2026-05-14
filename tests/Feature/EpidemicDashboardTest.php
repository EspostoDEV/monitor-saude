<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EpidemicDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Desativado em ambiente de teste para evitar dependência de compilação de assets (JS)
        Config::set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_it_renders_national_dashboard_with_correct_props()
    {
        // GIVEN
        $city = City::factory()->create(['uf' => 'SP']);
        EpidemicRecord::factory()->create([
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'epi_week' => 10,
            'year' => 2026,
            'cases' => 100,
            'incidence' => 100.0, // Ajustado para corresponder ao cálculo da MV (100 casos / 100k pop = 100 inc)
            'population' => 100000,
        ]);

        // Refresh Materialized View for National View
        Artisan::call('app:refresh-stats-view');

        // WHEN
        $response = $this->get('/');

        // THEN
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('filters')
            ->has('stats', fn (Assert $stats) => $stats
                ->where('total_cases', 100)
                ->where('new_cases', 100)
                ->where('latest_week', 10)
                ->etc()
            )
            ->has('records', 1)
            ->has('records.0', fn (Assert $record) => $record
                ->where('uf', 'SP')
                ->where('total_cases', 100)
                ->where('incidence', 100)
                ->where('level', 2)
                ->etc()
            )
        );
    }

    public function test_it_renders_state_dashboard_when_uf_filter_is_applied()
    {
        // GIVEN
        $city = City::factory()->create(['uf' => 'RJ', 'name' => 'Rio de Janeiro']);
        EpidemicRecord::factory()->create([
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'epi_week' => 10,
            'year' => 2026,
            'cases' => 50,
            'incidence' => 50.0,
            'population' => 100000,
        ]);

        // WHEN
        $response = $this->get('/?uf=RJ');

        // THEN
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('filters.uf', 'RJ')
            ->has('records', 1)
            ->has('records.0', fn (Assert $record) => $record
                ->where('city.name', 'Rio de Janeiro')
                ->where('cases', 50)
                ->etc()
            )
        );
    }

    public function test_it_returns_city_history_as_json()
    {
        // GIVEN
        $city = City::factory()->create();
        EpidemicRecord::factory()->count(5)->create([
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'year' => 2026,
        ]);

        // WHEN
        $response = $this->getJson("/api/history/{$city->id}?disease=dengue");

        // THEN
        $response->assertStatus(200);
        $response->assertJsonCount(5);
        $response->assertJsonStructure([
            '*' => ['week', 'year', 'cases', 'level', 'incidence']
        ]);
    }
}
