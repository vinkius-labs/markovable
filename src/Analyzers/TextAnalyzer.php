<?php

namespace VinkiusLabs\Markovable\Analyzers;

use VinkiusLabs\Markovable\Contracts\Analyzer;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Support\Tokenizer;

class TextAnalyzer implements Analyzer
{
    public function analyze(MarkovableChain $chain, array $model, array $options = []): array
    {
        $order = (int) ($options['order'] ?? $chain->getOrder());
        $seed = $options['seed'] ?? null;
        $initialStates = $options['initial_states'] ?? array_keys($model);
        $limit = (int) ($options['limit'] ?? 3);

        $prefix = $this->resolvePrefix($model, $order, $seed, $initialStates);
        $distribution = $model[$prefix] ?? [];
        $top = $this->selectTop($distribution, $limit);

        $predictions = array_map(static function ($token, $probability) {
            return [
                'sequence' => $token,
                'probability' => $probability,
            ];
        }, array_keys($top), $top);

        return [
            'seed' => $seed,
            'prefix' => $prefix,
            'predictions' => $predictions,
        ];
    }

    /**
     * @param array<string, array<string, float>> $model
     * @param array<int, string> $initialStates
     */
    private function resolvePrefix(array $model, int $order, ?string $seed, array $initialStates): string
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

        return $initialStates[0] ?? array_key_first($model);
    }

    /**
     * @param array<string, float> $distribution
     * @return array<string, float>
     */
    private function selectTop(array $distribution, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $top = [];

        foreach ($distribution as $token => $probability) {
            if ($token === '__END__') {
                continue;
            }

            if (count($top) < $limit) {
                $top[$token] = $probability;

                if (count($top) === $limit) {
                    asort($top);
                }

                continue;
            }

            $lowestProbability = reset($top);

            if ($probability <= $lowestProbability) {
                continue;
            }

            $top[$token] = $probability;
            asort($top);
            array_shift($top);
        }

        arsort($top);

        return $top;
    }
}



