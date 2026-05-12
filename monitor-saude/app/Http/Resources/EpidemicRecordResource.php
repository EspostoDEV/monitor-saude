<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpidemicRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disease' => $this->disease_type,
            'cases' => $this->cases,
            'week' => $this->epi_week,
            'year' => $this->year,
            'status' => $this->status,
            'city' => [
                'name' => $this->city->name,
                'uf' => $this->city->uf,
            ],
        ];
    }
}