<?php

namespace App\Services;

use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Support\Collection;

class TrendAnalysisService
{
    /**
     * Calculates the trend for a specific city and disease.
     * Uses the database to fetch records.
     */
    public function calculateTrend(City $city, string $disease): string
    {
        // Buscamos um pouco mais de dados para garantir que teremos semanas distintas após o DISTINCT
        $records = EpidemicRecord::query()
            ->selectRaw('DISTINCT ON (year, epi_week) *')
            ->where('city_id', $city->id)
            ->where('disease_type', $disease)
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->orderBy('updated_at', 'desc')
            ->limit(12) // Aumentamos o limite para garantir 8 semanas úteis
            ->get();

        return $this->calculateTrendFromRecords($records);
    }

    /**
     * Calculates trend for an entire UF (State).
     */
    public function calculateTrendForUf(string $uf, string $disease): string
    {
        $totalCitiesInUf = City::where('uf', $uf)->count();
        
        if ($totalCitiesInUf === 0) return 'stable';

        $deduplicatedSubquery = EpidemicRecord::query()
            ->selectRaw('DISTINCT ON (city_id, year, epi_week) *')
            ->where('disease_type', $disease)
            ->orderBy('city_id')
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->orderBy('updated_at', 'desc');

        // Captura o número de cidades que contribuíram para a semana mais recente
        $latestRecord = EpidemicRecord::where('disease_type', $disease)
            ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
            ->where('cities.uf', $uf)
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->first();

        if ($latestRecord) {
            $syncedCitiesCount = EpidemicRecord::where('disease_type', $disease)
                ->where('year', $latestRecord->year)
                ->where('epi_week', $latestRecord->epi_week)
                ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                ->where('cities.uf', $uf)
                ->count('city_id');

            $coverage = $syncedCitiesCount / $totalCitiesInUf;

            // Se menos de 90% das cidades sincronizaram, a tendência é incerta (Quórum do Winston)
            if ($coverage < 0.9) {
                return 'uncertain';
            }
        }

        $records = \DB::table(\DB::raw("({$deduplicatedSubquery->toSql()}) as records"))
            ->mergeBindings($deduplicatedSubquery->getQuery())
            ->selectRaw('year, epi_week, SUM(cases) as cases')
            ->join('cities', 'cities.id', '=', 'records.city_id')
            ->where('cities.uf', $uf)
            ->groupBy('year', 'epi_week')
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->limit(12)
            ->get();

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
