<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpidemicRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'disease_type',
        'cases',
        'level',
        'incidence',
        're_inferior',
        're_superior',
        'population',
        'epi_week',
        'year',
        'status',
    ];

    protected $appends = ['status_label'];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->level) {
            4 => 'Crítico',
            3 => 'Alerta',
            2 => 'Amarelo',
            default => 'Estável',
        };
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
