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

        arsort($distribution);

        $predictions = [];
        $count = 0;

        foreach ($distribution as $token => $probability) {
            if ($token === '__END__') {
                continue;
            }

            $predictions[] = [
                'sequence' => $token,
                'probability' => $probability,
            ];

            if (++$count >= $limit) {
                break;
            }
        }

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
}



