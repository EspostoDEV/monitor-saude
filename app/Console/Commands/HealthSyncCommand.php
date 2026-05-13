<?php

namespace App\Console\Commands;

use App\Services\HealthDataService;
use Illuminate\Console\Command;

class HealthSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:sync 
                            {disease : A doença para sincronizar (dengue, chikungunya, zika)} 
                            {--ibge= : Código IBGE opcional de um município específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza dados epidemiológicos das APIs da Fiocruz/InfoDengue';

    /**
     * Execute the console command.
     */
    public function handle(HealthDataService $service)
    {
        $disease = $this->argument('disease');
        $ibgeCode = $this->option('ibge');

        if (!in_array($disease, ['dengue', 'chikungunya', 'zika'])) {
            $this->error("Doença inválida. Use: dengue, chikungunya ou zika.");
            return 1;
        }

        $this->info("Iniciando sincronização de {$disease}" . ($ibgeCode ? " para o município {$ibgeCode}..." : "..."));

        $count = $service->sync($disease, $ibgeCode ? (int)$ibgeCode : null);

        $this->success("Sincronização concluída! {$count} registros processados.");
        
        return 0;
    }

    protected function success(string $message): void
    {
        $this->output->writeln("<info>{$message}</info>");
    }
}
