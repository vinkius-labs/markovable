<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Database\Eloquent\Model;

class MarkovableModel extends Model
{
    protected $table = 'markovable_models';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function markovable()
    {
        return $this->morphTo();
    }
}
