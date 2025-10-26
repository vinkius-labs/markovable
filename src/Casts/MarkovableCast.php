<?php

namespace VinkiusLabs\Markovable\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\MarkovableChain;

class MarkovableCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?MarkovableChain
    {
        if ($value === null) {
            return null;
        }

        return Markovable::chain('text')->trainFrom([(string) $value]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof MarkovableChain) {
            if ($value->getLastGenerated()) {
                return $value->getLastGenerated();
            }

            $tokens = $value->toArray();

            return empty($tokens) ? '' : implode(' ', $tokens);
        }

        return (string) $value;
    }
}
