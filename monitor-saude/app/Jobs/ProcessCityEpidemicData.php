<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Services\InfoDengueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCityEpidemicData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    public function __construct(
        protected City $city,
        protected string $disease,
        protected ?int $year = null
    ) {
    }

    public function handle(InfoDengueService $service): void
    {
        $year = $this->year ?? now()->year;
        $data = $service->fetch($this->city->ibge_code, $this->disease, $year);

        if (empty($data)) {
            return;
        }

        foreach ($data as $record) {
            $epiWeekStr = (string) $record['SE'];
            $yearRecord = (int) substr($epiWeekStr, 0, 4);
            $weekRecord = (int) substr($epiWeekStr, 4, 2);

            EpidemicRecord::updateOrCreate(
                [
                    'city_id' => $this->city->id,
                    'disease_type' => $this->disease,
                    'epi_week' => $weekRecord,
                    'year' => $yearRecord,
                ],
                [
                    'cases' => $record['casos'] ?? 0,
                    'level' => $record['nivel'] ?? null,
                    'incidence' => $record['p_inc100k'] ?? null,
                    're_inferior' => $record['Rt'] ?? null,
                    're_superior' => $record['Rt'] ?? null,
                    'population' => $record['pop'] ?? null,
                    'status' => $this->mapLevel($record['nivel'] ?? 0),
                ]
            );
        }

        Log::info("Synced {$this->disease} data for {$this->city->name} ({$this->city->ibge_code})");
    }

    private function mapLevel(int $level): string
    {
        return match ($level) {
            1 => 'Verde',
            2 => 'Amarelo',
            3 => 'Laranja',
            4 => 'Vermelho',
            default => 'Indeterminado'
        };
    }
}
