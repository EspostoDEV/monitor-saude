<?php

namespace App\Repositories\Contracts;

use App\Models\EpidemicRecord;
use Illuminate\Support\Collection;

interface EpidemicRepositoryInterface
{
    /**
     * Get the latest records for a given UF, including spatial coordinates.
     */
    public function getLatestRecordsByUf(string $uf, int $year, string $disease, ?int $latestWeek): Collection;

    /**
     * Get national statistics from the summary view.
     */
    public function getNationalStats(int $year, string $disease, ?int $latestWeek): Collection;

    /**
     * Get the latest epidemiological week for a given year and disease.
     */
    public function getLatestWeek(int $year, string $disease): ?int;

    /**
     * Get the timestamp of the last synchronization for a disease.
     */
    public function getLastSyncAt(string $disease): ?string;

    /**
     * Get global stats for a UF (Total and New Cases).
     */
    public function getUfGlobalStats(string $uf, int $year, string $disease, ?int $latestWeek): array;

    /**
     * Get history for trend analysis of a city.
     */
    public function getHistoryForTrend(int $cityId, string $disease, int $limit = 12): Collection;

    /**
     * Get history for trend analysis of a UF.
     */
    public function getUfHistoryForTrend(string $uf, string $disease, int $limit = 12): Collection;

    /**
     * Get the most recent record for a UF and disease.
     */
    public function getLatestRecordForUf(string $uf, string $disease): ?EpidemicRecord;
}
