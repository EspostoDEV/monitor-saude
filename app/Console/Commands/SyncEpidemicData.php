<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Services\InfoDengueService;
use Illuminate\Console\Command;

class SyncEpidemicData extends Command
{
    protected $signature = 'sync:epidemic-data {--disease=dengue} {--year=}';
    protected $description = 'Sync epidemiological data from InfoDengue API';

    public function handle(InfoDengueService $service)
    {
        $disease = $this->option('disease');
        $year = $this->option('year') ?? now()->year;

        $cities = City::all();
        $this->info("Starting sync for {$cities->count()} cities...");

        $bar = $this->output->createProgressBar($cities->count());

        foreach ($cities as $city) {
            \App\Jobs\ProcessCityEpidemicData::dispatch($city, $disease, $year);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Jobs dispatched to queue successfully!');
    }
}