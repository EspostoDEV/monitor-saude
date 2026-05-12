<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpidemicRecord extends Model
{
    protected $fillable = [
        'city_id',
        'disease_type',
        'cases',
        'epi_week',
        'year',
        'status',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}