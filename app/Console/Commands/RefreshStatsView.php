<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:refresh-stats-view')]
#[Description('Refresh the mv_uf_epidemic_stats materialized view concurrently')]
class RefreshStatsView extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $driver = \DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->warn('Skipping refresh for SQLite (using standard view).');

            return 0;
        }

        if ($driver !== 'pgsql') {
            $this->error("Database driver {$driver} not supported for materialized view refresh.");

            return 1;
        }

        $this->info('Refreshing Materialized View (CONCURRENTLY)...');

        try {
            \DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_uf_epidemic_stats');
            $this->info('Materialized View refreshed successfully (CONCURRENTLY)!');

            return 0;
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'concurrently')) {
                $this->warn('Concurrent refresh failed, attempting standard refresh...');
                try {
                    \DB::statement('REFRESH MATERIALIZED VIEW mv_uf_epidemic_stats');
                    $this->info('Materialized View refreshed successfully (Standard)!');

                    return 0;
                } catch (\Throwable $e2) {
                    $this->error('Failed to refresh Materialized View (Standard): '.$e2->getMessage());
                    \Log::error('Materialized View Refresh Failed (Standard)', ['exception' => $e2]);

                    return 1;
                }
            }

            $this->error('Failed to refresh Materialized View: '.$e->getMessage());
            \Log::error('Materialized View Refresh Failed', ['exception' => $e]);

            return 1;
        }
    }
}
