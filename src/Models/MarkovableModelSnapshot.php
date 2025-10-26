<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarkovableModelSnapshot extends Model
{
    protected $table = 'markovable_model_snapshots';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'compressed' => 'boolean',
        'encrypted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $snapshot) {
            if (! $snapshot->uuid) {
                $snapshot->uuid = (string) Str::uuid();
            }
        });
    }

    public function getIdentifierAttribute(): string
    {
        return $this->uuid;
    }
}
