<?php

namespace VinkiusLabs\Markovable\Generators;

use RuntimeException;
use VinkiusLabs\Markovable\Contracts\Generator;
use VinkiusLabs\Markovable\Support\Tokenizer;
use VinkiusLabs\Markovable\Support\WeightedRandom;

class TextGenerator implements Generator
{
    public function generate(array $model, int $length, array $options = []): string
    {
        if (empty($model)) {
            return '';
        }

        $order = (int) ($options['order'] ?? 2);
        $initialStates = $options['initial_states'] ?? array_keys($model);

        if (empty($initialStates)) {
            $initialStates = array_keys($model);
        }

        $seed = $options['seed'] ?? null;
        $state = $this->resolveInitialState($model, $initialStates, $order, $seed);
        $output = [];
        $steps = 0;
        $prefix = $state;
        $cumulativeModel = $options['cumulative_model'] ?? [];
        $transitions = $options['transitions'] ?? [];

        while ($steps < $length) {
            $choices = $model[$prefix] ?? null;

            if (! $choices) {
                break;
            }

            $next = null;

            if (isset($cumulativeModel[$prefix])) {
                $bucket = $cumulativeModel[$prefix];
                $next = WeightedRandom::chooseCumulative($bucket['tokens'], $bucket['cumulative']);
            }

            if ($next === null) {
                $next = WeightedRandom::choose($choices);
            }

            if ($next === null || $next === '__END__') {
                break;
            }

            $output[] = $next;
            $prefix = $transitions[$prefix][$next] ?? $this->shiftPrefix($prefix, $next, $order);
            $steps++;
        }

        return implode(' ', $output);
    }

    /**
     * @param array<string, array<string, float>> $model
     * @param array<int, string> $initialStates
     */
    private function resolveInitialState(array $model, array $initialStates, int $order, ?string $seed): string
    {
        if ($seed) {
            $tokens = Tokenizer::tokenize($seed);

            if (! empty($tokens)) {
                $candidate = implode(' ', array_slice(array_merge(array_fill(0, $order, '__START__'), $tokens), -$order));

                if (isset($model[$candidate])) {
                    return $candidate;
                }
            }
        }

        return $initialStates[array_rand($initialStates)];
    }

    private function shiftPrefix(string $prefix, string $nextToken, int $order): string
    {
        if ($order <= 1) {
            return $nextToken;
        }

        $firstSpace = strpos($prefix, ' ');

        if ($firstSpace === false) {
            return $nextToken;
        }

        return substr($prefix, $firstSpace + 1) . ' ' . $nextToken;
    }
}
