<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCityGripeData;
use App\Models\City;
use Illuminate\Console\Command;

class SyncEpidemicData extends Command
{
    protected $signature = 'sync:gripe {disease=srag}';
    protected $description = 'Sincroniza dados do InfoGripe (srag, covid19, influenza)';

    public function handle(): void
    {
        $disease = $this->argument('disease');

        City::all()->each(
            fn($city) =>
            ProcessCityGripeData::dispatch($city, $disease)
        );

        $this->info("Jobs de sincronização enfileirados para 5.570 cidades.");
    }

    private function mapStatus(int $level): string
    {
        return match ($level) {
            1 => 'Verde',
            2 => 'Amarelo',
            3 => 'Laranja',
            4 => 'Vermelho',
            default => 'Indeterminado',
        };
    }
}