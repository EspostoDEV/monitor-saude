<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
