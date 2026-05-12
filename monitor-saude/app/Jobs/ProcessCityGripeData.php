<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\EpidemicRecord;
use App\Services\InfoGripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCityGripeData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected City $city,
        protected string $disease
    ) {
    }

    public function handle(InfoGripeService $service): void
    {
        $data = $service->fetch($this->city->ibge_code, $this->disease);

        if (!is_array($data))
            return;

        foreach ($data as $report) {
            if (!is_array($report) || !isset($report['data_iniSE']))
                continue;

            EpidemicRecord::updateOrCreate(
                [
                    'city_id' => $this->city->id,
                    'disease_type' => $this->disease,
                    'epi_week' => $report['data_iniSE'] % 100,
                    'year' => (int) ($report['data_iniSE'] / 100),
                ],
                [
                    'cases' => $report['casos'] ?? 0,
                    'status' => $this->mapLevel($report['nivel'] ?? 0),
                ]
            );
            \Illuminate\Support\Facades\Log::info("Processando cidade: " . $this->city->name);
        }

    }

    private function mapLevel(int $level): string
    {
        return match ($level) {
            1 => 'Verde', 2 => 'Amarelo', 3 => 'Laranja', 4 => 'Vermelho', default => 'Indeterminado'
        };
    }
}