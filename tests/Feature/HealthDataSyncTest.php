<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Models\SyncSession;
use App\Services\HealthDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthDataSyncTest extends TestCase
{
    use RefreshDatabase;

    private HealthDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HealthDataService::class);
    }

    public function test_it_syncs_data_from_external_api_successfully()
    {
        // GIVEN
        $city = City::factory()->create([
            'ibge_code' => 3550308, // São Paulo
            'name' => 'São Paulo'
        ]);

        $mockResponse = [
            [
                'epi_week' => 10,
                'epi_year' => 2026,
                'casos' => 150,
                'incidencia' => 350.5,
                'pop' => 12000000,
            ]
        ];

        // Restringindo o mock para evitar falsos positivos
        Http::fake([
            'https://info.dengue.mat.br/api/alert/*' => Http::response($mockResponse, 200)
        ]);

        // WHEN
        $count = $this->service->sync('dengue', $city->ibge_code);

        // THEN
        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('epidemic_records', [
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'epi_week' => 10,
            'year' => 2026,
            'cases' => 150,
            'level' => 3,
        ]);
    }

    public function test_it_handles_api_failures_gracefully()
    {
        // GIVEN
        $city = City::factory()->create();

        Http::fake([
            'https://info.dengue.mat.br/api/alert/*' => Http::response([], 500)
        ]);

        // WHEN
        $count = $this->service->sync('dengue', $city->ibge_code);

        // THEN
        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('epidemic_records', 0);
    }

    public function test_it_updates_existing_records_instead_of_duplicating()
    {
        // GIVEN
        $city = City::factory()->create(['ibge_code' => 1234567]);
        
        // Record already exists
        EpidemicRecord::factory()->create([
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'epi_week' => 10,
            'year' => 2026,
            'cases' => 100
        ]);

        $mockResponse = [
            [
                'epi_week' => 10,
                'epi_year' => 2026,
                'casos' => 200,
                'incidencia' => 400.0,
            ]
        ];

        Http::fake([
            'https://info.dengue.mat.br/api/alert/*' => Http::response($mockResponse, 200)
        ]);

        // WHEN
        $this->service->sync('dengue', $city->ibge_code);

        // THEN
        $this->assertDatabaseCount('epidemic_records', 1);
        $this->assertDatabaseHas('epidemic_records', [
            'city_id' => $city->id,
            'cases' => 200
        ]);
    }

    public function test_it_updates_session_progress()
    {
        // GIVEN
        $city = City::factory()->create(['ibge_code' => 1234567, 'uf' => 'SP']);
        $session = SyncSession::create([
            'session_id' => 'test-session',
            'disease' => 'dengue',
            'total_cities' => 1,
            'processed_cities' => 0,
            'status' => 'running',
        ]);

        Http::fake([
            'https://info.dengue.mat.br/api/alert/*' => Http::response([['epi_week' => 1, 'epi_year' => 2026, 'casos' => 10]], 200)
        ]);

        // WHEN
        $this->service->sync('dengue', $city->ibge_code, 'SP', 'test-session');

        // THEN
        $session->refresh();
        $this->assertEquals(1, $session->processed_cities);
        $this->assertEquals('finished', $session->status);
        $this->assertNotNull($session->completed_at); // Adicionado conforme revisão
    }
}
