<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'session_id',
        'disease',
        'uf',
        'level',
        'message',
    ];
}
