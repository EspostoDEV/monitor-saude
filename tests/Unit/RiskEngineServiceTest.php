<?php

namespace Tests\Unit;

use App\Services\RiskEngineService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RiskEngineServiceTest extends TestCase
{
    private RiskEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RiskEngineService;
    }

    #[DataProvider('alertLevelDataProvider')]
    public function test_it_calculates_correct_alert_level(float $incidence, int $cases, string $trend, int $expectedLevel)
    {
        $level = $this->service->getAlertLevel($incidence, $cases, $trend);
        $this->assertEquals($expectedLevel, $level);
    }

    public static function alertLevelDataProvider(): array
    {
        return [
            'level 1 - low incidence' => [50.0, 20, 'stable', 1],
            'level 2 - yellow' => [150.0, 20, 'stable', 2],
            'level 3 - orange' => [350.0, 20, 'stable', 3],
            'level 4 - red' => [650.0, 20, 'stable', 4],

            // Sanity Check: < 5 cases -> always level 1
            'sanity check - high incidence but < 5 cases' => [700.0, 4, 'up', 1],
            'sanity check - orange incidence but < 5 cases' => [350.0, 3, 'up', 1],

            // Sanity Check: < 10 cases and level 4 -> downgrade to 3
            'sanity check - red incidence but < 10 cases' => [700.0, 8, 'up', 3],
            'no downgrade - red incidence and 10 cases' => [700.0, 10, 'up', 4],
        ];
    }

    public function test_it_returns_correct_labels()
    {
        $this->assertEquals('Crítico', $this->service->getAlertStatusLabel(4));
        $this->assertEquals('Alerta', $this->service->getAlertStatusLabel(3));
        $this->assertEquals('Amarelo', $this->service->getAlertStatusLabel(2));
        $this->assertEquals('Estável', $this->service->getAlertStatusLabel(1));
        $this->assertEquals('Estável', $this->service->getAlertStatusLabel(0));
    }

    public function test_it_generates_correct_explanations()
    {
        // Level 4
        $explanation4 = $this->service->getAlertExplanation(4, 700.0, 50);
        $this->assertStringContainsString('Alerta Crítico', $explanation4);
        $this->assertStringContainsString('700', $explanation4);

        // Level 3 (Downgraded)
        $explanation3Downgraded = $this->service->getAlertExplanation(3, 700.0, 8);
        $this->assertStringContainsString('Alerta Moderado', $explanation3Downgraded);
        $this->assertStringContainsString('baixo volume absoluto', $explanation3Downgraded);

        // Level 3 (Normal)
        $explanation3Normal = $this->service->getAlertExplanation(3, 350.0, 20);
        $this->assertStringContainsString('Alerta Laranja', $explanation3Normal);

        // Level 2
        $explanation2 = $this->service->getAlertExplanation(2, 150.0, 20);
        $this->assertStringContainsString('Alerta Amarelo', $explanation2);

        // Level 1
        $explanation1 = $this->service->getAlertExplanation(1, 50.0, 20);
        $this->assertStringContainsString('Situação Estável', $explanation1);
    }

    public function test_it_generates_correct_trend_explanations()
    {
        $this->assertStringContainsString('Tendência de Alta', $this->service->getTrendExplanation('up', 100));
        $this->assertStringContainsString('Tendência de Queda', $this->service->getTrendExplanation('down', 100));
        $this->assertStringContainsString('Dados em Consolidação', $this->service->getTrendExplanation('uncertain', 100));
        $this->assertStringContainsString('Estabilidade', $this->service->getTrendExplanation('stable', 100));
    }
}
