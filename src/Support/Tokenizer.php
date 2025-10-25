<?php

namespace VinkiusLabs\Markovable\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Tokenizer
{
    /**
     * @param mixed $input
     * @return array<int, string>
     */
    public static function corpus($input): array
    {
        if (is_iterable($input) && ! is_string($input)) {
            $strings = [];

            foreach ($input as $value) {
                $strings = array_merge($strings, static::extractStrings($value));
            }

            return array_values(array_filter($strings, static fn($value) => $value !== ''));
        }

        return array_values(array_filter(static::extractStrings($input), static fn($value) => $value !== ''));
    }

    /**
     * @return array<int, string>
     */
    public static function tokenize(string $value): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($normalized === '') {
            return [];
        }

        return preg_split('/\s+/u', $normalized) ?: [];
    }

    /**
     * @return array<int, string>
     */
    private static function extractStrings($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        if ($value instanceof Collection) {
            return static::corpus($value->all());
        }

        if ($value instanceof Arrayable) {
            return static::corpus($value->toArray());
        }

        if ($value instanceof Model) {
            $columns = [];

            if (property_exists($value, 'markovableColumns')) {
                $columns = (array) $value->markovableColumns;
            } elseif (property_exists($value, 'MarkovableColumns')) {
                $columns = (array) $value->MarkovableColumns;
            }

            if (! empty($columns)) {
                return collect($columns)
                    ->map(static fn(string $column) => data_get($value, $column))
                    ->filter()
                    ->map(static fn($item) => (string) $item)
                    ->all();
            }

            return static::corpus($value->getAttributes());
        }

        if (is_array($value)) {
            $results = [];

            foreach ($value as $item) {
                $results = array_merge($results, static::extractStrings($item));
            }

            return $results;
        }

        if (is_iterable($value)) {
            return static::corpus($value);
        }

        if ($value instanceof \Stringable || method_exists($value, '__toString')) {
            return [(string) $value];
        }

        if (method_exists($value, 'toArray')) {
            return static::corpus($value->toArray());
        }

        return [(string) $value];
    }
}
