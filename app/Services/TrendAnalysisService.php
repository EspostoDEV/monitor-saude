<?php

namespace App\Services;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Repositories\Contracts\EpidemicRepositoryInterface;
use Illuminate\Support\Collection;

class TrendAnalysisService
{
    protected EpidemicRepositoryInterface $repository;

    public function __construct(EpidemicRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Calculates the trend for a specific city and disease.
     * Uses the database to fetch records.
     */
    public function calculateTrend(City $city, string $disease): string
    {
        $records = $this->repository->getHistoryForTrend($city->id, $disease);

        return $this->calculateTrendFromRecords($records);
    }

    /**
     * Calculates trend for an entire UF (State).
     */
    public function calculateTrendForUf(string $uf, string $disease): string
    {
        $totalCitiesInUf = City::where('uf', $uf)->count();

        if ($totalCitiesInUf === 0) {
            return 'stable';
        }

        // Captura o número de cidades que contribuíram para a semana mais recente via Repositório
        $latestRecord = $this->repository->getLatestRecordForUf($uf, $disease);

        if ($latestRecord) {
            $syncedCitiesCount = EpidemicRecord::where('disease_type', $disease)
                ->where('year', $latestRecord->year)
                ->where('epi_week', $latestRecord->epi_week)
                ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                ->where('cities.uf', $uf)
                ->count('city_id');

            // Se menos de 90% das cidades sincronizaram, a tendência é incerta (Quórum do Winston)
            if ($totalCitiesInUf > 0 && ($syncedCitiesCount / $totalCitiesInUf) < 0.9) {
                return 'uncertain';
            }
        } else {
            // Se não há nenhum registro para essa UF/Doença, a tendência é incerta
            return 'uncertain';
        }

        $records = $this->repository->getUfHistoryForTrend($uf, $disease);

        return $this->calculateTrendFromRecords($records);
    }

    /**
     * Core logic for trend calculation using a Collection of records.
     * Pure and testable.
     */
    public function calculateTrendFromRecords(Collection $records): string
    {
        // Precisamos de pelo menos 6 semanas para uma comparação de 3 vs 3
        if ($records->count() < 6) {
            return 'stable';
        }

        // Usamos janelas de 3 semanas para maior sensibilidade
        $currentPeriod = $records->slice(0, 3);
        $previousPeriod = $records->slice(3, 3);

        $currentAvg = $currentPeriod->avg('cases');
        $previousAvg = $previousPeriod->avg('cases');

        // Detecção de "Queda Brusca" na última semana (Responsividade Imediata)
        $latestWeekCases = $records->first()->cases;
        $previousWeekCases = $records->get(1)?->cases ?? $latestWeekCases;

        // Se a última semana caiu mais de 50% em relação à anterior, priorizamos o sinal de queda
        if ($latestWeekCases < ($previousWeekCases * 0.5)) {
            return 'down';
        }

        if ($previousAvg <= 0) {
            return $currentAvg > 0 ? 'up' : 'stable';
        }

        $variation = ($currentAvg - $previousAvg) / $previousAvg;

        // Limiar de 15% para mudança
        if ($variation > 0.15) {
            return 'up';
        }

        if ($variation < -0.15) {
            return 'down';
        }

        return 'stable';
    }
}
