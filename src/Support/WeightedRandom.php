<?php

namespace VinkiusLabs\Markovable\Support;

class WeightedRandom
{
    /**
     * @param array<string, float|int> $weights
     */
    public static function choose(array $weights): ?string
    {
        if (empty($weights)) {
            return null;
        }

        $total = array_sum($weights);

        if ($total <= 0) {
            $uniform = array_keys($weights);

            return $uniform[array_rand($uniform)];
        }

        $threshold = mt_rand() / mt_getrandmax() * $total;
        $cumulative = 0.0;

        foreach ($weights as $key => $weight) {
            $cumulative += (float) $weight;

            if ($threshold <= $cumulative) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}


