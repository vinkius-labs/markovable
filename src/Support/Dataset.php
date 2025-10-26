<?php

namespace VinkiusLabs\Markovable\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Dataset
{
    /**
     * Normalize a dataset into a flat array structure.
     *
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    public static function normalize($value): array
    {
        if ($value === null) {
            return [];
        }

        if ($value instanceof Collection) {
            return static::normalize($value->all());
        }

        if ($value instanceof Arrayable) {
            return static::normalize($value->toArray());
        }

        if ($value instanceof Model) {
            return [static::flattenRecord($value->toArray())];
        }

        if (is_array($value)) {
            if (Arr::isAssoc($value)) {
                return [static::flattenRecord($value)];
            }

            if (static::isScalarList($value)) {
                return [];
            }
        }

        if (is_iterable($value) && ! is_string($value)) {
            $records = [];

            foreach ($value as $item) {
                $record = static::normalizeRecord($item);

                if ($record !== null) {
                    $records[] = $record;
                }
            }

            return $records;
        }

        return [];
    }

    /**
     * @param  mixed  $item
     * @return array<string, mixed>|null
     */
    private static function normalizeRecord($item): ?array
    {
        if ($item === null) {
            return null;
        }

        if ($item instanceof Collection) {
            return static::normalizeRecord($item->all());
        }

        if ($item instanceof Arrayable) {
            return static::normalizeRecord($item->toArray());
        }

        if ($item instanceof Model) {
            return static::flattenRecord($item->toArray());
        }

        if (is_array($item)) {
            if (! Arr::isAssoc($item) && static::isScalarList($item)) {
                return null;
            }

            return static::flattenRecord($item);
        }

        if (is_iterable($item) && ! is_string($item)) {
            return static::flattenRecord(iterator_to_array($item));
        }

        return null;
    }

    /**
     * @param  array<string|int, mixed>  $record
     * @param  string  $prefix
     * @return array<string, mixed>
     */
    public static function flattenRecord(array $record, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($record as $key => $value) {
            $key = is_int($key) ? (string) $key : (string) $key;
            $path = $prefix === '' ? $key : $prefix.'.'.$key;

            if ($value instanceof Collection) {
                $value = $value->all();
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if ($value instanceof Model) {
                $value = $value->toArray();
            }

            if ($value instanceof \DateTimeInterface) {
                $flattened[$path] = $value->format(\DateTimeInterface::ATOM);
                continue;
            }

            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }

                if (static::isScalarList($value)) {
                    $flattened[$path] = array_values($value);
                    continue;
                }

                $flattened += static::flattenRecord($value, $path);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flattened[$path] = $value;
                continue;
            }

            if ($value instanceof \Stringable || method_exists($value, '__toString')) {
                $flattened[$path] = (string) $value;
                continue;
            }

            $flattened[$path] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $flattened;
    }

    private static function isScalarList(array $items): bool
    {
        if (! array_is_list($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                return false;
            }
        }

        return true;
    }
}
