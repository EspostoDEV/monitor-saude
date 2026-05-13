<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EpidemicRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Calcula a data de início da semana epidemiológica
        $date = Carbon::now()->setISODate($this->year, $this->epi_week);
        $startDate = $date->startOfWeek(Carbon::SUNDAY)->format('d/m');
        $endDate = $date->endOfWeek(Carbon::SATURDAY)->format('d/m');
        
        $monthNames = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];

        return [
            'id' => $this->id,
            'disease' => $this->disease_type,
            'cases' => $this->cases,
            'level' => $this->level,
            'incidence' => $this->incidence,
            'population' => $this->population,
            'week' => $this->epi_week,
            'week_range' => "$startDate a $endDate",
            'month' => $monthNames[$date->month],
            'year' => $this->year,
            'status' => $this->status,
            'city_id' => $this->city_id,
            'total_cases' => (int) $this->total_cases,
            'city' => [
                'id' => $this->city_id,
                'name' => $this->city->name,
                'uf' => $this->city->uf,
                'lat' => $this->lat,
                'lng' => $this->lng,
            ],
            'trend' => $this->trend ?? 'stable',
            'alert_explanation' => $this->alert_explanation ?? null,
            'trend_explanation' => $this->trend_explanation ?? null,
        ];
    }
}