<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Services\TrendAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendAnalysisFeatureTest extends TestCase
{
    use RefreshDatabase;

    private TrendAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrendAnalysisService::class);
    }

    public function test_it_returns_uncertain_when_quorum_is_not_met()
    {
        // GIVEN: 10 cities in SP
        $cities = City::factory()->count(10)->create(['uf' => 'SP']);
        
        // Only 8 cities have records for the latest week (80% < 90%)
        foreach ($cities->take(8) as $city) {
            EpidemicRecord::factory()->create([
                'city_id' => $city->id,
                'disease_type' => 'dengue',
                'epi_week' => 20,
                'year' => 2026,
            ]);
        }

        // WHEN
        $trend = $this->service->calculateTrendForUf('SP', 'dengue');

        // THEN
        $this->assertEquals('uncertain', $trend);
    }

    public function test_it_calculates_trend_when_quorum_is_met()
    {
        // GIVEN: 10 cities in SP
        $cities = City::factory()->count(10)->create(['uf' => 'SP']);
        
        // 9 cities have records for the latest week (90% >= 90%)
        // We also need historical data to avoid 'stable' due to lack of weeks
        for ($w = 15; $w <= 20; $w++) {
            foreach ($cities->take(9) as $city) {
                EpidemicRecord::factory()->create([
                    'city_id' => $city->id,
                    'disease_type' => 'dengue',
                    'epi_week' => $w,
                    'year' => 2026,
                    'cases' => 10, // Stable cases
                ]);
            }
        }

        // WHEN
        $trend = $this->service->calculateTrendForUf('SP', 'dengue');

        // THEN
        $this->assertEquals('stable', $trend);
    }

    public function test_it_handles_uf_with_no_cities_gracefully()
    {
        // WHEN
        $trend = $this->service->calculateTrendForUf('UNKNOWN', 'dengue');

        // THEN
        $this->assertEquals('stable', $trend);
    }
}
