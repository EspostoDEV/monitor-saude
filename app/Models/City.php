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

    public function epidemicRecords(): HasMany
    {
        return $this->hasMany(EpidemicRecord::class);
    }

    public function getLatAttribute(): ?float
    {
        return DB::selectOne('SELECT ST_Y(ST_Centroid(location)) as y FROM cities WHERE id = ?', [$this->id])->y ?? null;
    }

    public function getLngAttribute(): ?float
    {
        return DB::selectOne('SELECT ST_X(ST_Centroid(location)) as x FROM cities WHERE id = ?', [$this->id])->x ?? null;
    }
}
