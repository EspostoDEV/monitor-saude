<?php

namespace App\Repositories\Eloquent;

use App\Models\EpidemicRecord;
use App\Repositories\Contracts\EpidemicRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostgisEpidemicRepository implements EpidemicRepositoryInterface
{
    public function getLatestRecordsByUf(string $uf, int $year, string $disease, ?int $latestWeek): Collection
    {
        if ($latestWeek === null) {
            return collect();
        }

        // Filter Push-down: Aplicando o filtro de UF dentro da subquery do DISTINCT ON
        $subquery = '
            SELECT DISTINCT ON (epidemic_records.city_id) epidemic_records.id 
            FROM epidemic_records 
            JOIN cities ON cities.id = epidemic_records.city_id
            WHERE cities.uf = ? AND epidemic_records.year = ? AND epidemic_records.disease_type = ? 
            ORDER BY epidemic_records.city_id, epidemic_records.updated_at DESC
        ';

        // Elite Optimization: Filtrando também os totais anuais pelo UF solicitado
        $totalsSubquery = EpidemicRecord::query()
            ->select('epidemic_records.city_id', DB::raw('SUM(epidemic_records.cases) as total_cases'))
            ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
            ->where('cities.uf', $uf)
            ->where('epidemic_records.year', $year)
            ->where('epidemic_records.disease_type', $disease)
            ->groupBy('epidemic_records.city_id');

        return EpidemicRecord::query()
            ->select('epidemic_records.*')
            ->selectRaw('COALESCE(totals.total_cases, 0) as total_cases')
            ->selectRaw('ST_X(cities.location::geometry) as lng, ST_Y(cities.location::geometry) as lat')
            ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
            ->leftJoinSub($totalsSubquery, 'totals', 'totals.city_id', '=', 'epidemic_records.city_id')
            ->whereRaw("epidemic_records.id IN ($subquery)", [$uf, $year, $disease])
            ->with('city')
            ->get();
    }

    public function getNationalStats(int $year, string $disease, ?int $latestWeek): Collection
    {
        if ($latestWeek === null) {
            return collect();
        }

        return DB::table('mv_uf_epidemic_stats')
            ->select('uf', 'total_cases', 'real_incidence as incidence')
            ->selectRaw('total_cases as new_cases')
            ->where('year', $year)
            ->where('epi_week', $latestWeek)
            ->where('disease_type', $disease)
            ->get();
    }

    public function getUfGlobalStats(string $uf, int $year, string $disease, ?int $latestWeek): array
    {
        if ($latestWeek === null) {
            return [
                'uf_total_cases' => 0,
                'uf_new_cases' => 0,
            ];
        }

        $stats = DB::selectOne('
            SELECT 
                SUM(cases) as uf_total_cases,
                SUM(CASE WHEN epi_week = ? THEN cases ELSE 0 END) as uf_new_cases
            FROM epidemic_records 
            JOIN cities ON cities.id = epidemic_records.city_id 
            WHERE cities.uf = ? AND epidemic_records.year = ? AND epidemic_records.disease_type = ?
        ', [$latestWeek, $uf, $year, $disease]);

        return [
            'uf_total_cases' => (int) ($stats->uf_total_cases ?? 0),
            'uf_new_cases' => (int) ($stats->uf_new_cases ?? 0),
        ];
    }

    public function getLatestWeek(int $year, string $disease): ?int
    {
        return EpidemicRecord::where('year', $year)
            ->where('disease_type', $disease)
            ->max('epi_week');
    }

    public function getLastSyncAt(string $disease): ?string
    {
        return EpidemicRecord::where('disease_type', $disease)->max('updated_at');
    }

    public function getHistoryForTrend(int $cityId, string $disease, int $limit = 12): Collection
    {
        return EpidemicRecord::query()
            ->select('epidemic_records.*')
            ->where('city_id', $cityId)
            ->where('disease_type', $disease)
            ->whereRaw('id IN (
                SELECT DISTINCT ON (year, epi_week) id 
                FROM epidemic_records 
                WHERE city_id = ? AND disease_type = ? 
                ORDER BY year DESC, epi_week DESC, updated_at DESC
            )', [$cityId, $disease])
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUfHistoryForTrend(string $uf, string $disease, int $limit = 12): Collection
    {
        // Simplificação: Selecionando apenas as colunas necessárias para evitar ambiguidade e sobrecarga
        $deduplicatedSubquery = '
            SELECT DISTINCT ON (epidemic_records.city_id, epidemic_records.year, epidemic_records.epi_week) 
                epidemic_records.year, epidemic_records.epi_week, epidemic_records.cases
            FROM epidemic_records 
            JOIN cities ON cities.id = epidemic_records.city_id
            WHERE cities.uf = ? AND epidemic_records.disease_type = ? 
            ORDER BY epidemic_records.city_id, epidemic_records.year DESC, epidemic_records.epi_week DESC, epidemic_records.updated_at DESC
        ';

        return DB::table(DB::raw("($deduplicatedSubquery) as records"))
            ->setBindings([$uf, $disease])
            ->selectRaw('year, epi_week, SUM(cases) as cases')
            ->groupBy('year', 'epi_week')
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLatestRecordForUf(string $uf, string $disease): ?EpidemicRecord
    {
        return EpidemicRecord::query()
            ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
            ->where('cities.uf', $uf)
            ->where('epidemic_records.disease_type', $disease)
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->select('epidemic_records.*')
            ->first();
    }
}
