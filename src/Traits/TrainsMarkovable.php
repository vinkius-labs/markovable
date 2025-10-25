<?php

namespace VinkiusLabs\Markovable\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Observers\AutoTrainObserver;

trait TrainsMarkovable
{
    public static function bootTrainsMarkovable(): void
    {
        static::observe(new AutoTrainObserver());
    }

    public function trainMarkovable(?array $columns = null): void
    {
        $values = $this->markovableValues($columns);

        if ($values->isEmpty()) {
            return;
        }

        $chain = Markovable::chain('text')->trainFrom($values->all());
        $chain->cache($this->markovableCacheKey());
    }

    public function markovableQueue(?array $columns = null): void
    {
        $values = $this->markovableValues($columns);

        if ($values->isEmpty()) {
            return;
        }

        $chain = Markovable::chain('text')->trainFrom($values->all())->cache($this->markovableCacheKey());
        Bus::dispatch($chain->queue());
    }

    protected function markovableCacheKey(): string
    {
        return sprintf('%s:%s', Str::slug(class_basename(static::class)), $this->getKey());
    }

    protected function markovableValues(?array $columns = null): Collection
    {
        $columns ??= property_exists($this, 'markovableColumns') ? (array) $this->markovableColumns : [];

        return collect($columns)
            ->map(fn(string $column) => data_get($this, $column))
            ->filter()
            ->map(static fn($value) => (string) $value);
    }
}
