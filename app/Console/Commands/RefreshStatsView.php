<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('app:refresh-stats-view')]
#[Description('Refresh all analytical materialized views (UF and National)')]
class RefreshStatsView extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->warn('Skipping refresh for SQLite (using standard views).');

            return 0;
        }

        if ($driver !== 'pgsql') {
            $this->error("Database driver {$driver} not supported for materialized view refresh.");

            return 1;
        }

        $views = [
            'mv_uf_epidemic_stats',
            'mv_national_stats',
        ];

        foreach ($views as $view) {
            $this->info("Refreshing Materialized View: {$view} (CONCURRENTLY)...");

            try {
                DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$view}");
                $this->info("View {$view} refreshed successfully (CONCURRENTLY)!");
            } catch (\Throwable $e) {
                if (str_contains(strtolower($e->getMessage()), 'concurrently')) {
                    $this->warn("Concurrent refresh failed for {$view}, attempting standard refresh...");
                    try {
                        DB::statement("REFRESH MATERIALIZED VIEW {$view}");
                        $this->info("View {$view} refreshed successfully (Standard)!");
                    } catch (\Throwable $e2) {
                        $this->error("Failed to refresh View {$view} (Standard): ".$e2->getMessage());
                        Log::error("Materialized View Refresh Failed: {$view}", ['exception' => $e2]);

                        return 1;
                    }
                } else {
                    $this->error("Failed to refresh View {$view}: ".$e->getMessage());
                    Log::error("Materialized View Refresh Failed: {$view}", ['exception' => $e]);

                    return 1;
                }
            }
        }

        return 0;
    }
}
