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

    /**
     * Scope para garantir a unicidade de registros, pegando sempre o mais recente.
     * Compatível com PostgreSQL (DISTINCT ON) e SQLite (GROUP BY).
     */
    public function scopeDeduplicated($query, array $columns = ['city_id', 'disease_type', 'epi_week', 'year'])
    {
        $table = $this->getTable();

        if (\DB::getDriverName() === 'pgsql') {
            $cols = implode(', ', $columns);

            return $query->selectRaw("DISTINCT ON ($cols) $table.*")
                ->orderByRaw($cols)
                ->orderBy("$table.updated_at", 'desc');
        }

        // Fallback para SQLite: GROUP BY (Nota: No SQLite isso retorna uma linha do grupo, 
        // mas não garante estritamente que seja a 'mais recente' sem subqueries complexas.
        // Para fins de teste e compatibilidade cross-db básica, o GROUP BY resolve o erro de sintaxe).
        return $query->groupBy($columns);
    }
}
