<?php

namespace Tests\Unit;

use App\Services\TrendAnalysisService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TrendAnalysisServiceTest extends TestCase
{
    private TrendAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrendAnalysisService();
    }

    public function test_it_returns_up_when_moving_average_increases_more_than_15_percent()
    {
        // Janela de 3 semanas
        // Média Anterior (4,5,6): 10
        // Média Atual (1,2,3): 12
        // Aumento: 20%
        $records = collect([
            ['cases' => 12], ['cases' => 12], ['cases' => 12],
            ['cases' => 10], ['cases' => 10], ['cases' => 10],
        ])->map(fn($item) => (object) $item);

        $this->assertEquals('up', $this->service->calculateTrendFromRecords($records));
    }

    public function test_it_returns_down_when_moving_average_decreases_more_than_15_percent()
    {
        // Média Anterior: 10
        // Média Atual: 8 (Queda de 20%)
        $records = collect([
            ['cases' => 8], ['cases' => 8], ['cases' => 8],
            ['cases' => 10], ['cases' => 10], ['cases' => 10],
        ])->map(fn($item) => (object) $item);

        $this->assertEquals('down', $this->service->calculateTrendFromRecords($records));
    }

    public function test_it_returns_down_immediately_on_massive_drop()
    {
        // Mesmo que a média do bloco de 3 semanas seja alta (por causa das semanas 2 e 3)
        // se a última semana (index 0) caiu mais de 50% em relação à anterior (index 1), deve ser DOWN.
        $records = collect([
            ['cases' => 20],  // Caiu 75% em relação à anterior
            ['cases' => 80],
            ['cases' => 80],
            ['cases' => 10],
            ['cases' => 10],
            ['cases' => 10],
        ])->map(fn($item) => (object) $item);

        $this->assertEquals('down', $this->service->calculateTrendFromRecords($records));
    }

    public function test_it_returns_stable_when_variation_is_within_15_percent()
    {
        $records = collect([
            ['cases' => 11], ['cases' => 11], ['cases' => 11],
            ['cases' => 10], ['cases' => 10], ['cases' => 10],
        ])->map(fn($item) => (object) $item);

        $this->assertEquals('stable', $this->service->calculateTrendFromRecords($records));
    }

    public function test_it_returns_stable_when_insufficient_data()
    {
        $records = collect([
            ['cases' => 10], ['cases' => 10],
        ])->map(fn($item) => (object) $item);

        $this->assertEquals('stable', $this->service->calculateTrendFromRecords($records));
    }
}
