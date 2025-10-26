<?php

namespace VinkiusLabs\Markovable\Detectors;

use VinkiusLabs\Markovable\Contracts\Detector;
use VinkiusLabs\Markovable\Support\DetectionContext;
use VinkiusLabs\Markovable\Support\Tokenizer;

class UnseenSequenceDetector implements Detector
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function detect(DetectionContext $context, array $config = []): array
    {
        $threshold = (float) ($config['threshold'] ?? 0.05);
        $minLength = (int) ($config['minLength'] ?? 2);

        $results = [];

        foreach ($context->getCurrentSequences() as $sequence => $count) {
            $tokens = Tokenizer::tokenize($sequence);

            if (count($tokens) < $minLength) {
                continue;
            }

            $probability = $context->probabilityFromTokens($tokens);

            if ($probability >= $threshold) {
                continue;
            }

            $results[] = [
                'sequence' => $tokens,
                'probability' => $probability,
                'severity' => $this->severityFor($probability),
                'type' => 'unseenSequence',
                'count' => $count,
                'score' => 1 - $probability,
            ];
        }

        return $results;
    }

    private function severityFor(float $probability): string
    {
        if ($probability <= 0.0) {
            return 'critical';
        }

        if ($probability < 0.01) {
            return 'high';
        }

        if ($probability < 0.05) {
            return 'medium';
        }

        return 'low';
    }
}
