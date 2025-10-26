<?php

namespace VinkiusLabs\Markovable\Detectors;

use Illuminate\Support\Carbon;
use VinkiusLabs\Markovable\Analyzers\PatternMatcher;
use VinkiusLabs\Markovable\Contracts\Detector;
use VinkiusLabs\Markovable\Support\DetectionContext;
use VinkiusLabs\Markovable\Support\Tokenizer;

class EmergingPatternDetector implements Detector
{
    private PatternMatcher $matcher;

    public function __construct(?PatternMatcher $matcher = null)
    {
        $this->matcher = $matcher ?? new PatternMatcher;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function detect(DetectionContext $context, array $config = []): array
    {
        $minFrequency = (int) ($config['minFrequency'] ?? 10);
        $growthThreshold = (float) ($config['growth'] ?? 0.5);
        $confidenceFloor = (float) ($config['confidence'] ?? 0.5);

        $results = [];

        foreach ($this->matcher->frequent($context, $minFrequency) as $sequence => $count) {

            $tokens = Tokenizer::tokenize($sequence);
            $baselineCount = $context->baselineFrequency($sequence);
            $growthRate = $this->computeGrowthRate($baselineCount, $count);

            if ($baselineCount > 0 && $growthRate < $growthThreshold) {
                continue;
            }

            $history = $context->patternHistory($sequence);
            $results[] = [
                'pattern' => $tokens,
                'frequency' => $count,
                'baselineFrequency' => $baselineCount,
                'growth_rate' => $growthRate,
                'growth' => $this->formatGrowth($growthRate),
                'trend' => $this->resolveTrend($history),
                'firstSeen' => $this->resolveFirstSeen($history),
                'daysActive' => $this->resolveDaysActive($history),
                'confidence' => $this->confidence($growthRate, $confidenceFloor),
                'type' => 'emergingPattern',
                'count' => $count,
            ];
        }

        return $results;
    }

    private function computeGrowthRate(int $baseline, int $current): float
    {
        if ($baseline <= 0) {
            return 1.0;
        }

        return ($current - $baseline) / max(1, $baseline);
    }

    private function formatGrowth(float $growth): string
    {
        $percent = $growth * 100;

        return sprintf('%+.0f%%', $percent);
    }

    /**
     * @param  array<int, mixed>  $history
     */
    private function resolveTrend(array $history): string
    {
        if (count($history) < 3) {
            return 'stable';
        }

        $values = array_values(array_slice($history, -3));

        if ($values[0] < $values[1] && $values[1] < $values[2]) {
            return 'accelerating';
        }

        if ($values[0] > $values[1] && $values[1] > $values[2]) {
            return 'decelerating';
        }

        return 'stable';
    }

    /**
     * @param  array<string|int, mixed>  $history
     */
    private function resolveFirstSeen(array $history): ?string
    {
        if (empty($history)) {
            return Carbon::now()->toDateString();
        }

        $firstKey = array_key_first($history);

        if ($firstKey === null) {
            return Carbon::now()->toDateString();
        }

        return is_numeric($firstKey)
            ? Carbon::now()->subDays((int) $firstKey)->toDateString()
            : (string) $firstKey;
    }

    /**
     * @param  array<int, mixed>  $history
     */
    private function resolveDaysActive(array $history): ?int
    {
        if (empty($history)) {
            return null;
        }

        return count(array_filter($history, static fn ($value) => (int) $value > 0));
    }

    private function confidence(float $growthRate, float $floor): float
    {
        $confidence = 1 - exp(-abs($growthRate));

        return max($floor, min(1.0, round($confidence, 4)));
    }
}
