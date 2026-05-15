<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiDiseaseDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_it_renders_chikungunya_stats_correctly()
    {
        // GIVEN
        $city = City::factory()->create(['uf' => 'RJ']);
        EpidemicRecord::factory()->create([
            'city_id' => $city->id,
            'disease_type' => 'chikungunya',
            'epi_week' => 15,
            'year' => 2026,
            'cases' => 50,
            'population' => 10000,
            'incidence' => 500.0,
        ]);

        Artisan::call('app:refresh-stats-view');

        // WHEN
        $response = $this->get('/?disease=chikungunya');

        // THEN
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.disease', 'chikungunya')
            ->has('records', 1)
            ->has('records.0', fn (Assert $record) => $record
                ->where('uf', 'RJ')
                ->where('total_cases', 50)
                ->etc()
            )
        );
    }
}
