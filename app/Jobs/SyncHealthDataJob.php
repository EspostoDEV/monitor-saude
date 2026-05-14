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

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hora

    public string $disease;
    public ?int $ibgeCode = null;
    public ?string $uf = null;
    public ?string $sessionId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(string $disease, ?int $ibgeCode = null, ?string $uf = null, ?string $sessionId = null)
    {
        $this->disease = $disease;
        $this->ibgeCode = $ibgeCode;
        $this->uf = $uf;
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job.
     */
    public function handle(HealthDataService $service): void
    {
        Log::info("JOB DEBUG: Iniciando Handle. Doença: {$this->disease}, UF: {$this->uf}, Session: {$this->sessionId}");
        
        if (!$this->sessionId) {
            Log::warning("JOB WARNING: SessionId está vazio!");
        }

        $count = $service->sync($this->disease, $this->ibgeCode, $this->uf, $this->sessionId);
        
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
