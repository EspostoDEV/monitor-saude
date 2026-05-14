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
        $this->info('Refreshing Materialized View (CONCURRENTLY)...');
        
        \DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY mv_uf_epidemic_stats");
        
        $this->info("Materialized View refreshed successfully!");
        
        return 0;
    }
}
