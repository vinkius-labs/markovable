<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Database\Eloquent\Model;

class PatternAlert extends Model
{
    protected $table = 'markovable_pattern_alerts';

    protected $fillable = [
        'model_key',
        'pattern',
        'severity',
        'metadata',
        'dispatched_at',
    ];

    protected $casts = [
        'pattern' => 'array',
        'metadata' => 'array',
        'dispatched_at' => 'datetime',
    ];
}
