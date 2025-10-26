<?php

namespace VinkiusLabs\Markovable\Analyzers;

class PageRankCalculator
{
    private float $defaultDamping = 0.85;

    private float $defaultThreshold = 1.0e-6;

    private int $defaultMaxIterations = 100;

    /** @var array<string, array<string, float>> */
    private array $graph;

    /** @var array<int, string> */
    private array $nodes = [];

    /**
     * @param  array<string, array<string, float>>  $graph
     */
    public function __construct(array $graph = [])
    {
        $this->graph = $this->normalizeGraph($graph);
        $this->nodes = array_keys($this->graph);
    }

    /**
     * @param  array<string, array<string, float>>  $transitions
     */
    public static function fromMarkovTransitions(array $transitions): self
    {
        return new self($transitions);
    }

    /**
     * @return array{scores: array<string, float>, iterations: int, converged: bool}
     */
    public function calculate(?float $damping = null, ?float $threshold = null, ?int $maxIterations = null): array
    {
        $damping ??= $this->defaultDamping;
        $threshold ??= $this->defaultThreshold;
        $maxIterations ??= $this->defaultMaxIterations;

        $damping = $this->clamp($damping, 0.0, 1.0);
        $threshold = $threshold > 0.0 ? $threshold : $this->defaultThreshold;
        $maxIterations = $maxIterations > 0 ? $maxIterations : $this->defaultMaxIterations;

        $nodeCount = count($this->nodes);

        if ($nodeCount === 0) {
            return [
                'scores' => [],
                'iterations' => 0,
                'converged' => true,
            ];
        }

        $ranks = array_fill_keys($this->nodes, 1.0 / $nodeCount);
        $baseScore = (1.0 - $damping) / $nodeCount;

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $newRanks = array_fill_keys($this->nodes, $baseScore);
            $danglingMass = 0.0;

            foreach ($this->nodes as $source) {
                $edges = $this->graph[$source];
                $rank = $ranks[$source];

                if ($edges === []) {
                    $danglingMass += $rank;
                    continue;
                }

                $share = $damping * $rank;

                foreach ($edges as $target => $weight) {
                    $newRanks[$target] += $share * $weight;
                }
            }

            if ($danglingMass > 0.0) {
                $danglingContribution = $damping * $danglingMass / $nodeCount;

                foreach ($this->nodes as $node) {
                    $newRanks[$node] += $danglingContribution;
                }
            }

            $delta = 0.0;

            foreach ($this->nodes as $node) {
                $delta = max($delta, abs($newRanks[$node] - $ranks[$node]));
            }

            $ranks = $newRanks;

            if ($delta <= $threshold) {
                return [
                    'scores' => $ranks,
                    'iterations' => $iteration,
                    'converged' => true,
                ];
            }
        }

        return [
            'scores' => $ranks,
            'iterations' => $maxIterations,
            'converged' => false,
        ];
    }

    /**
     * @param  array<string, array<string, float>>  $graph
     * @return array<string, array<string, float>>
     */
    private function normalizeGraph(array $graph): array
    {
        $normalized = [];

        foreach ($graph as $source => $targets) {
            if (! is_array($targets)) {
                continue;
            }

            $sourceKey = (string) $source;
            $filtered = [];
            $sum = 0.0;

            foreach ($targets as $target => $weight) {
                $value = (float) $weight;

                if ($value <= 0.0) {
                    continue;
                }

                $targetKey = (string) $target;
                $filtered[$targetKey] = $value;
                $sum += $value;
            }

            if ($sum > 0.0) {
                $inv = 1.0 / $sum;

                foreach ($filtered as $target => $value) {
                    $filtered[$target] = $value * $inv;
                }
            }

            $normalized[$sourceKey] = $filtered;
        }

        foreach ($normalized as $targets) {
            foreach ($targets as $target => $_) {
                if (! isset($normalized[$target])) {
                    $normalized[$target] = [];
                }
            }
        }

        ksort($normalized);

        return $normalized;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
