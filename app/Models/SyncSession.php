<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'disease',
        'total_cities',
        'processed_cities',
        'total_records_found',
        'status',
        'last_error',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'processed_cities' => 'integer',
        'total_cities' => 'integer',
    ];

    public function getProgressAttribute()
    {
        if ($this->total_cities <= 0) return 0;
        return round(($this->processed_cities / $this->total_cities) * 100, 1);
    }
}
