<?php

namespace VinkiusLabs\Markovable\Support;

use Illuminate\Support\Collection;

class Statistics
{
    /**
     * @param  iterable<int, float|int>  $values
     */
    public static function mean(iterable $values): float
    {
        $collection = static::toCollection($values)->filter(static fn ($value) => is_numeric($value));

        if ($collection->isEmpty()) {
            return 0.0;
        }

        return $collection->sum() / $collection->count();
    }

    /**
     * @param  iterable<int, float|int>  $values
     */
    public static function variance(iterable $values): float
    {
        $collection = static::toCollection($values)->filter(static fn ($value) => is_numeric($value));
        $count = $collection->count();

        if ($count === 0) {
            return 0.0;
        }

        $mean = static::mean($collection);

        $sum = $collection->reduce(
            static fn (float $carry, $value) => $carry + pow((float) $value - $mean, 2),
            0.0
        );

        return $count > 1 ? $sum / $count : 0.0;
    }

    /**
     * @param  iterable<int, float|int>  $values
     */
    public static function standardDeviation(iterable $values): float
    {
        return sqrt(static::variance($values));
    }

    /**
     * @param  iterable<int, float|int>  $values
     */
    public static function percentile(iterable $values, float $percentile): float
    {
        $collection = static::toCollection($values)
            ->filter(static fn ($value) => is_numeric($value))
            ->sort();

        $count = $collection->count();

        if ($count === 0) {
            return 0.0;
        }

        $rank = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);

        $lowerValue = (float) $collection->values()->get($lower, 0.0);
        $upperValue = (float) $collection->values()->get($upper, $lowerValue);

        if ($lower === $upper) {
            return $lowerValue;
        }

        $weight = $rank - $lower;

        return $lowerValue + $weight * ($upperValue - $lowerValue);
    }

    /**
     * @param  iterable<int, float|int>  $values
     */
    public static function median(iterable $values): float
    {
        return static::percentile($values, 50);
    }

    /**
     * @param  iterable<int, float|int>  $values
     */
    public static function sum(iterable $values): float
    {
        return static::toCollection($values)
            ->filter(static fn ($value) => is_numeric($value))
            ->sum();
    }

    /**
     * @param  iterable<string, float|int>  $values
     * @return array<string, float>
     */
    public static function normalizeProbabilities(iterable $values): array
    {
        $collection = static::toCollection($values);
        $sum = $collection->filter(static fn ($value) => is_numeric($value))->sum();

        if ($sum <= 0) {
            return $collection->map(static fn () => 0.0)->all();
        }

        return $collection->map(static fn ($value) => (float) $value / $sum)->all();
    }

    /**
     * @param  iterable<int, mixed>  $values
     */
    private static function toCollection(iterable $values): Collection
    {
        return $values instanceof Collection ? $values : collect($values);
    }
}
