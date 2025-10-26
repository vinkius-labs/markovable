<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterProfile extends Model
{
    protected $table = 'markovable_cluster_profiles';

    protected $fillable = [
        'model_key',
        'cluster_id',
        'profile',
        'size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
