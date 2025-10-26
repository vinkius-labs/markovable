<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Database\Eloquent\Model;

class PredictionResult extends Model
{
    protected $table = 'markovable_predictions';

    protected $fillable = [
        'prediction_type',
        'customer_id',
        'metric',
        'prediction_data',
        'confidence',
        'features',
        'generated_at',
        'actioned_at',
        'action_result',
    ];

    protected $casts = [
        'prediction_data' => 'array',
        'features' => 'array',
        'confidence' => 'float',
        'generated_at' => 'datetime',
        'actioned_at' => 'datetime',
        'action_result' => 'array',
    ];
}
