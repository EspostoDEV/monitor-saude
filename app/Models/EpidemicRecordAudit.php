<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpidemicRecordAudit extends Model
{
    protected $fillable = [
        'epidemic_record_id',
        'old_cases',
        'new_cases',
        'reason',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(EpidemicRecord::class, 'epidemic_record_id');
    }
}
