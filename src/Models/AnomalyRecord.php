<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Database\Eloquent\Model;

class AnomalyRecord extends Model
{
    protected $table = 'markovable_anomalies';

    protected $fillable = [
        'model_key',
        'type',
        'sequence',
        'score',
        'count',
        'metadata',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'sequence' => 'array',
        'metadata' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
