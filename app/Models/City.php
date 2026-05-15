<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'ibge_code',
        'name',
        'uf',
        'location',
    ];

    /**
     * Scope para incluir latitude e longitude na query de forma performática.
     * Use este scope em dashboards para evitar N+1.
     */
    public function scopeWithCoordinates($query)
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->addSelect([
                DB::raw('ST_Y(ST_Centroid(location)) as lat'),
                DB::raw('ST_X(ST_Centroid(location)) as lng'),
            ]);
        }

        return $query;
    }

    public function epidemicRecords(): HasMany
    {
        return $this->hasMany(EpidemicRecord::class);
    }

    /**
     * Accessor para Latitude.
     * Prioriza o atributo carregado via scope, mas possui fallback para compatibilidade.
     */
    public function getLatAttribute(): ?float
    {
        if (array_key_exists('lat', $this->attributes)) {
            return (float) $this->attributes['lat'];
        }

        if (DB::getDriverName() === 'pgsql' && $this->id) {
            return DB::selectOne('SELECT ST_Y(ST_Centroid(location)) as y FROM cities WHERE id = ?', [$this->id])->y ?? null;
        }

        return null;
    }

    /**
     * Accessor para Longitude.
     * Prioriza o atributo carregado via scope, mas possui fallback para compatibilidade.
     */
    public function getLngAttribute(): ?float
    {
        if (array_key_exists('lng', $this->attributes)) {
            return (float) $this->attributes['lng'];
        }

        if (DB::getDriverName() === 'pgsql' && $this->id) {
            return DB::selectOne('SELECT ST_X(ST_Centroid(location)) as x FROM cities WHERE id = ?', [$this->id])->x ?? null;
        }

        return null;
    }
}
