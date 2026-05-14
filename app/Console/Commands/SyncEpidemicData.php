<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SyncSession;
use App\Jobs\SyncHealthDataJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncEpidemicData extends Command
{
    protected $signature = 'sync:epidemic-data {--disease=dengue} {--uf=}';
    protected $description = 'Sync epidemiological data using UF-based parallelization';

    public function handle()
    {
        $disease = $this->option('disease');
        $uf = $this->option('uf');
        $sessionId = Str::uuid()->toString();

        $query = City::query();
        if ($uf) {
            $query->where('uf', $uf);
        }

        $totalCities = $query->count();
        
        if ($totalCities === 0) {
            $this->error("No cities found for the specified filters.");
            return 1;
        }

        SyncSession::create([
            'session_id' => $sessionId,
            'disease' => $disease,
            'status' => 'running',
            'total_cities' => $totalCities,
            'processed_cities' => 0,
            'started_at' => now(),
        ]);

        if ($uf) {
            $this->info("Dispatching sync job for UF: {$uf}...");
            SyncHealthDataJob::dispatch($disease, null, $uf, $sessionId);
        } else {
            $ufs = City::distinct()->pluck('uf');
            foreach ($ufs as $targetUf) {
                SyncHealthDataJob::dispatch($disease, null, $targetUf, $sessionId);
            }
            $this->info("Dispatched parallel jobs for " . $ufs->count() . " UFs.");
        }

        $this->info("Session ID: {$sessionId}");
    }
}