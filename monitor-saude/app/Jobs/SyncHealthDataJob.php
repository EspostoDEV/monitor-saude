<?php

namespace App\Jobs;

use App\Services\HealthDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncHealthDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [60, 300, 600]; // 1min, 5min, 10min

    protected string $disease;
    protected ?int $ibgeCode;

    /**
     * Create a new job instance.
     */
    public function __construct(string $disease, ?int $ibgeCode = null)
    {
        $this->disease = $disease;
        $this->ibgeCode = $ibgeCode;
    }

    /**
     * Execute the job.
     */
    public function handle(HealthDataService $service): void
    {
        Log::info("Job de sincronização iniciado: {$this->disease}");
        
        $count = $service->sync($this->disease, $this->ibgeCode);
        
        Log::info("Job de sincronização concluído: {$this->disease}. {$count} registros processados.");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job de sincronização falhou para {$this->disease}: " . $exception->getMessage());
    }
}
