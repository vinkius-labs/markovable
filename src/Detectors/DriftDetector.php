<?php

namespace VinkiusLabs\Markovable\Detectors;

use VinkiusLabs\Markovable\Contracts\Detector;
use VinkiusLabs\Markovable\Support\DetectionContext;
use VinkiusLabs\Markovable\Support\Tokenizer;

class DriftDetector implements Detector
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function detect(DetectionContext $context, array $config = []): array
    {
        $threshold = (float) ($config['drift_threshold'] ?? 0.2);

        $currentAverage = $this->averageLength($context->getCurrentSequences());
        $baselineAverage = $this->averageLength($context->getBaselineMeta()['sequence_frequencies'] ?? []);

        if ($baselineAverage <= 0.0) {
            return [];
        }

        $difference = $currentAverage - $baselineAverage;
        $relative = $difference / $baselineAverage;

        if (abs($relative) < $threshold) {
            return [];
        }

        return [[
            'type' => 'drift',
            'direction' => $relative > 0 ? 'upward' : 'downward',
            'relative_change' => $relative,
            'baseline_average_length' => $baselineAverage,
            'current_average_length' => $currentAverage,
            'severity' => $this->severity(abs($relative)),
            'description' => sprintf(
                'Sequence length drifted by %s%s',
                $relative > 0 ? '+' : '-',
                number_format(abs($relative) * 100, 2)
            ),
        ]];
    }

    /**
     * @param  array<string, int>  $sequences
     */
    private function averageLength(array $sequences): float
    {
        if (empty($sequences)) {
            return 0.0;
        }

        $totalLength = 0;
        $totalCount = 0;

        foreach ($sequences as $sequence => $count) {
            $tokens = Tokenizer::tokenize(is_string($sequence) ? $sequence : implode(' ', (array) $sequence));
            $length = count($tokens);
            $totalLength += $length * $count;
            $totalCount += $count;
        }

        if ($totalCount <= 0) {
            return 0.0;
        }

        return $totalLength / $totalCount;
    }

    private function severity(float $relativeChange): string
    {
        if ($relativeChange >= 0.5) {
            return 'critical';
        }

        if ($relativeChange >= 0.3) {
            return 'high';
        }

        if ($relativeChange >= 0.15) {
            return 'medium';
        }

        return 'low';
    }
}
