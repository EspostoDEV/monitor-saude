<?php

namespace App\Repositories\Eloquent;

use App\Models\EpidemicRecord;
use App\Repositories\Contracts\EpidemicRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SqliteEpidemicRepository implements EpidemicRepositoryInterface
{
    public function getLatestRecordsByUf(string $uf, int $year, string $disease, int $latestWeek): Collection
    {
        $totalsSubquery = EpidemicRecord::query()
            ->select('city_id', DB::raw('SUM(cases) as total_cases'))
            ->where('year', $year)
            ->where('disease_type', $disease)
            ->groupBy('city_id');

        return EpidemicRecord::query()
            ->select('epidemic_records.*')
            ->selectRaw('COALESCE(totals.total_cases, 0) as total_cases')
            ->selectRaw('NULL as lng, NULL as lat') // SQLite handles location as text or null
            ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
            ->leftJoinSub($totalsSubquery, 'totals', 'totals.city_id', '=', 'epidemic_records.city_id')
            ->where('cities.uf', $uf)
            ->where('epidemic_records.year', $year)
            ->where('epidemic_records.disease_type', $disease)
            ->groupBy('epidemic_records.city_id') // Basic deduplication for SQLite
            ->with('city')
            ->get();
    }

    public function getNationalStats(int $year, string $disease, ?int $latestWeek): Collection
    {
        if ($latestWeek === null) {
            return collect();
        }

        // In SQLite, mv_uf_epidemic_stats is a standard VIEW
        return DB::table('mv_uf_epidemic_stats')
            ->select('uf', 'total_cases', 'real_incidence as incidence')
            ->selectRaw('total_cases as new_cases')
            ->where('year', $year)
            ->where('epi_week', $latestWeek)
            ->where('disease_type', $disease)
            ->get();
    }

    public function getUfGlobalStats(string $uf, int $year, string $disease, int $latestWeek): array
    {
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
            ->where('city_id', $cityId)
            ->where('disease_type', $disease)
            ->groupBy('year', 'epi_week')
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUfHistoryForTrend(string $uf, string $disease, int $limit = 12): Collection
    {
        return DB::table('epidemic_records')
            ->selectRaw('year, epi_week, SUM(cases) as cases')
            ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
            ->where('cities.uf', $uf)
            ->where('disease_type', $disease)
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
