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

        $threshold = lcg_value() * $total;
        $cumulative = 0.0;

        foreach ($weights as $key => $weight) {
            $cumulative += (float) $weight;

            if ($threshold <= $cumulative) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * @param array<int, string> $tokens
     * @param array<int, float> $cumulative
     */
    public static function chooseCumulative(array $tokens, array $cumulative): ?string
    {
        if (empty($tokens) || empty($cumulative)) {
            return null;
        }

        $threshold = lcg_value();
        $low = 0;
        $high = count($cumulative) - 1;

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if ($threshold <= $cumulative[$mid]) {
                $high = $mid;
            } else {
                $low = $mid + 1;
            }
        }

        return $tokens[$low] ?? end($tokens);
    }
}


