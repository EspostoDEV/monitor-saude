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
        return match($this->level) {
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

    /**
     * Scope para garantir a unicidade de registros por cidade/semana,
     * pegando sempre o mais recente (updated_at).
     */
    public function scopeDeduplicated($query)
    {
        return $query->selectRaw('DISTINCT ON (city_id, disease_type, epi_week, year) *')
            ->orderBy('city_id')
            ->orderBy('disease_type')
            ->orderBy('epi_week')
            ->orderBy('year')
            ->orderBy('updated_at', 'desc');
    }
}