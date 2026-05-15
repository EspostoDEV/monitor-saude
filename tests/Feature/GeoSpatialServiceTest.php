<?php

namespace Tests\Feature;

use App\Models\City;
use App\Services\GeoSpatialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GeoSpatialServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeoSpatialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeoSpatialService;
        Cache::flush();
    }

    /** @test */
    public function test_it_returns_simplified_geojson_based_on_zoom_level()
    {
        // Create a city with a complex polygon (simulated with multiple points)
        // Note: Using ST_GeomFromText for PostGIS
        $city = City::factory()->create([
            'ibge_code' => '3550308',
            'location' => \DB::raw("ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))', 4326)"),
        ]);

        // Zoom low (1-5) should use LOD_LOW
        $geoJsonLow = $this->service->getCityGeoJson($city, 3);
        $this->assertIsString($geoJsonLow);

        // Verify cache in Redis
        $this->assertTrue(Cache::has('geojson:3550308:low'));
    }

    /** @test */
    public function test_it_uses_cache_if_available()
    {
        $city = City::factory()->create(['ibge_code' => '3550308']);
        $cacheKey = 'geojson:3550308:medium';
        $mockGeoJson = '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[0,0],[0,1],[1,1],[1,0],[0,0]]]}}';

        Cache::put($cacheKey, $mockGeoJson, 3600);

        $result = $this->service->getCityGeoJson($city, 7); // Zoom 7 is Medium LOD

        $this->assertEquals($mockGeoJson, $result);
    }
}
