<?php

namespace VinkiusLabs\Markovable\Traits;

use Illuminate\Database\Eloquent\Builder;
use VinkiusLabs\Markovable\Models\MarkovableModel;

trait HasMarkovableScopes
{
    public function scopeMarkovableTrained(Builder $query): Builder
    {
        return $query->whereHas('markovableData');
    }

    public function scopeWithMarkovableData(Builder $query): Builder
    {
        return $query->with('markovableData');
    }

    public function markovableData()
    {
        return $this->morphOne(MarkovableModel::class, 'markovable');
    }
}
