<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeduplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Teste que valida se o scope deduplicated retorna apenas a versão mais recente.
     */
    public function test_scope_deduplicated_returns_latest_record_per_week(): void
    {
        // 1. Setup: Criar uma cidade
        $city = City::create([
            'ibge_code' => 3304557,
            'name' => 'Rio de Janeiro',
            'uf' => 'RJ',
        ]);

        // 2. Criar registro antigo para a Semana 10
        $oldRecord = EpidemicRecord::create([
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'epi_week' => 10,
            'year' => 2026,
            'cases' => 100,
            'updated_at' => now()->subDay(),
        ]);

        // 3. Criar registro novo para a mesma Semana 10 (simulando re-ingestão corrigida)
        $newRecord = EpidemicRecord::create([
            'city_id' => $city->id,
            'disease_type' => 'dengue',
            'epi_week' => 10,
            'year' => 2026,
            'cases' => 150, // Dado corrigido
            'updated_at' => now(),
        ]);

        // 4. Executar a query com o scope deduplicated
        $results = EpidemicRecord::deduplicated()->get();

        // 5. Asserções
        $this->assertCount(1, $results, "Deveria retornar apenas 1 registro para o par (cidade, semana)");
        $this->assertEquals(150, $results->first()->cases, "Deveria retornar o valor do registro mais recente (updated_at DESC)");
        $this->assertEquals($newRecord->id, $results->first()->id, "O ID retornado deve ser o do registro mais novo");
    }
}
