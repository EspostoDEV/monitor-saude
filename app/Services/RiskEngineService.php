<?php

namespace App\Services;

class RiskEngineService
{
    /**
     * Calculates the alert level (1-4) based on incidence, absolute cases, and trend.
     */
    public function getAlertLevel(float $incidence, int $cases, string $trend): int
    {
        $level = 1;
        $thresholds = config('epidemiology.thresholds', [
            'yellow' => 100,
            'orange' => 300,
            'red' => 600,
        ]);

        $sanity = config('epidemiology.sanity_check', [
            'min_cases_for_stable' => 5,
            'min_cases_for_critical' => 10,
        ]);

        if ($incidence >= $thresholds['red']) {
            $level = 4;
        } elseif ($incidence >= $thresholds['orange']) {
            $level = 3;
        } elseif ($incidence >= $thresholds['yellow']) {
            $level = 2;
        }

        // Sanity Check: Cidades pequenas com pouquíssimos casos absolutos (Issue 8)
        if ($cases < $sanity['min_cases_for_stable']) {
            $level = 1;
        } elseif ($cases < $sanity['min_cases_for_critical'] && $level === 4) {
            $level = 3;
        }

        return $level;
    }

    /**
     * Returns the human-readable label for a given alert level.
     */
    public function getAlertStatusLabel(int $level): string
    {
        return match ($level) {
            4 => 'Crítico',
            3 => 'Alerta',
            2 => 'Amarelo',
            default => 'Estável',
        };
    }

    /**
     * Generates a technical explanation for the alert level.
     */
    public function getAlertExplanation(int $level, float $incidence, int $cases): string
    {
        return match ($level) {
            4 => 'Alerta Crítico: Incidência de '.round($incidence, 1).' por 100k habitantes supera o limite de 600, indicando surto descontrolado na região.',
            3 => $cases < 10 && $incidence > 600
                ? "Alerta Moderado: Embora a incidência seja estatisticamente alta, o baixo volume absoluto de casos ($cases) sugere ruído estatístico. Vigilância preventiva recomendada."
                : 'Alerta Laranja: Transmissão sustentada detectada. Incidência acima de 300 indica necessidade de intervenção imediata.',
            2 => 'Alerta Amarelo: Atenção necessária. Incidência acima de 100 indica início de circulação viral acima do esperado.',
            default => 'Situação Estável: Incidência sob controle dentro dos padrões de segurança epidemiológica.',
        };
    }

    /**
     * Generates a technical explanation for the trend.
     */
    public function getTrendExplanation(string $trend, float $incidence): string
    {
        return match ($trend) {
            'up' => 'Tendência de Alta: A média móvel de casos cresceu mais de 15% nas últimas 3 semanas, indicando aceleração do surto.',
            'down' => 'Tendência de Queda: Redução significativa na velocidade de contágio. A curva está em declínio.',
            'uncertain' => 'Dados em Consolidação: Menos de 90% dos municípios da região reportaram dados para a última semana. A tendência atual pode ser imprecisa até a conclusão do quórum.',
            default => 'Estabilidade: O número de novos casos mantém-se constante, sem sinais de aceleração ou recuo imediato.',
        };
    }
}
