<?php

namespace VinkiusLabs\Markovable\Analyzers;

use VinkiusLabs\Markovable\Contracts\Detector;
use VinkiusLabs\Markovable\Support\DetectionContext;

class SeasonalAnalyzer implements Detector
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function detect(DetectionContext $context, array $config = []): array
    {
        $threshold = (float) ($config['seasonality_threshold'] ?? ($config['threshold'] ?? 0.3));
        $metrics = (array) ($config['metrics'] ?? ['weekday']);
        $profiles = $config['seasonality_data'] ?? $context->seasonalityProfile();

        if (! is_array($profiles) || empty($profiles)) {
            return [];
        }

        $results = [];

        foreach ($metrics as $metric) {
            if (! isset($profiles[$metric]) || ! is_array($profiles[$metric])) {
                continue;
            }

            $profile = $profiles[$metric];
            $baseline = $profile['baseline'] ?? null;
            $current = $profile['current'] ?? null;

            if (! is_array($baseline) || ! is_array($current)) {
                continue;
            }

            $divergence = $this->klDivergence($baseline, $current);

            if ($divergence < $threshold) {
                continue;
            }

            $results[] = [
                'pattern' => $metric.'_effect',
                'type' => 'seasonality',
                'divergence' => $divergence,
                'metrics' => $profile,
                'significance' => min(1.0, $divergence),
                'severity' => $this->severity($divergence),
                'description' => $profile['description'] ?? sprintf('Seasonality shift detected on %s', $metric),
            ];
        }

        return $results;
    }

    /**
     * @param  array<string|int, float|int>  $baseline
     * @param  array<string|int, float|int>  $current
     */
    private function klDivergence(array $baseline, array $current): float
    {
        $epsilon = 1e-9;
        $aligned = array_unique(array_merge(array_keys($baseline), array_keys($current)));

        $divergence = 0.0;

        foreach ($aligned as $key) {
            $p = (float) ($baseline[$key] ?? 0.0);
            $q = (float) ($current[$key] ?? 0.0);

            $p = max($epsilon, $p);
            $q = max($epsilon, $q);

            $divergence += $p * log($p / $q);
        }

        return max(0.0, $divergence);
    }

    private function severity(float $divergence): string
    {
        if ($divergence >= 1.2) {
            return 'critical';
        }

        if ($divergence >= 0.8) {
            return 'high';
        }

        if ($divergence >= 0.4) {
            return 'medium';
        }

        return 'low';
    }
}
