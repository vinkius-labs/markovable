<?php

namespace VinkiusLabs\Markovable\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Facades\Markovable;

class AutoTrainObserver
{
    private ?string $field;

    public function __construct(?string $field = null)
    {
        $this->field = $field;
    }

    public function saved(Model $model): void
    {
        $values = $this->valuesFromModel($model);

        if ($values->isEmpty()) {
            return;
        }

        $chain = Markovable::chain('text')
            ->option('meta', [
                'type' => get_class($model),
                'id' => $model->getKey(),
            ])
            ->trainFrom($values->all());
        $key = $this->cacheKey($model);

        if ($key) {
            $chain->cache($key);
        }

        $queueConfig = config('markovable.queue', []);

        if (($queueConfig['enabled'] ?? false) === true) {
            $job = $chain->queue();

            if (! empty($queueConfig['connection'])) {
                $job->onConnection($queueConfig['connection']);
            }

            if (! empty($queueConfig['queue'])) {
                $job->onQueue($queueConfig['queue']);
            }

            Bus::dispatch($job);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function valuesFromModel(Model $model): Collection
    {
        $columns = $this->columns($model);

        return collect($columns)
            ->map(static fn (string $column) => data_get($model, $column))
            ->filter()
            ->map(static fn ($value) => (string) $value);
    }

    private function columns(Model $model): array
    {
        if (property_exists($model, 'markovableColumns')) {
            $columns = (array) $model->markovableColumns;

            if (! empty($columns)) {
                return $columns;
            }
        }

        if (property_exists($model, 'MarkovableColumns')) {
            $columns = (array) $model->MarkovableColumns;

            if (! empty($columns)) {
                return $columns;
            }
        }

        return $this->field ? [$this->field] : [];
    }

    private function cacheKey(Model $model): ?string
    {
        if (! $model->getKey()) {
            return null;
        }

        return sprintf('%s:%s', Str::slug((new \ReflectionClass($model))->getShortName()), $model->getKey());
    }
}
