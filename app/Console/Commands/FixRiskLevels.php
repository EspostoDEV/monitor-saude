<?php

namespace App\Console\Commands;

use App\Models\EpidemicRecord;
use App\Services\RiskEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FixRiskLevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-risk-levels {--disease=} {--uf=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculates risk levels for existing records based on current configuration';

    /**
     * Execute the console command.
     */
    public function handle(RiskEngineService $riskService)
    {
        $query = EpidemicRecord::query();

        if ($disease = $this->option('disease')) {
            $query->where('disease_type', $disease);
        }

        if ($uf = $this->option('uf')) {
            $query->whereHas('city', function ($q) use ($uf) {
                $q->where('uf', $uf);
            });
        }

        $total = $query->count();
        $this->info("Starting normalization of {$total} records...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(1000, function ($records) use ($riskService, $bar) {
            foreach ($records as $record) {
                $newLevel = $riskService->getAlertLevel(
                    $record->incidence,
                    $record->cases,
                    'stable' // We don't have trend context here, using stable
                );

                if ($record->level !== $newLevel) {
                    $record->update(['level' => $newLevel]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Risk levels normalized successfully.');

        // Refresh materialized view to reflect changes in national view
        $this->info('Refreshing materialized view...');
        Artisan::call('app:refresh-stats-view');
        $this->info('Done.');
    }
}
