<?php

namespace VinkiusLabs\Markovable\Analyzers;

use Closure;
use RuntimeException;
use VinkiusLabs\Markovable\Contracts\Analyzer;
use VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Models\PageRankResult;
use VinkiusLabs\Markovable\Support\Statistics;

class PageRankAnalyzer implements Analyzer
{
    private const DEFAULT_DAMPING = 0.85;

    private const DEFAULT_THRESHOLD = 1.0e-6;

    private const DEFAULT_MAX_ITERATIONS = 100;

    /**
     * @param  array<string, array<string, float>>  $model
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function analyze(MarkovableChain $chain, array $model, array $options = []): array
    {
        $graph = $this->resolveGraph($chain, $model, $options);
        $calculator = new PageRankCalculator($graph);

        $damping = $this->sanitizeDamping($options['damping'] ?? $options['damping_factor'] ?? null);
        $threshold = $this->sanitizeThreshold($options['threshold'] ?? $options['convergence_threshold'] ?? null);
        $maxIterations = $this->sanitizeIterations($options['max_iterations'] ?? $options['iterations'] ?? null);

        $calculation = $calculator->calculate($damping, $threshold, $maxIterations);
        $scores = $calculation['scores'];

        if ($scores === []) {
            $result = new PageRankResult([], [
                'iterations' => $calculation['iterations'],
                'converged' => $calculation['converged'],
                'damping_factor' => $damping,
                'threshold' => $threshold,
                'max_iterations' => $maxIterations,
                'total_nodes' => 0,
                'context' => $chain->getContext(),
                'baseline_key' => $chain->getCacheKey(),
            ]);

            return $this->finalizePayload($result, (bool) ($options['include_metadata'] ?? false));
        }

        arsort($scores);

        $top = isset($options['top']) ? (int) $options['top'] : null;
        $orderedScores = $top !== null && $top > 0
            ? array_slice($scores, 0, $top, true)
            : $scores;

        $normalized = $this->normalizeScores($scores);
        $percentiles = $this->percentiles($scores);

        $nodes = [];

        foreach ($orderedScores as $node => $score) {
            $nodes[$node] = new PageRankNode(
                $node,
                (float) $score,
                $normalized[$node] ?? 0.0,
                $percentiles[$node] ?? 0.0
            );
        }

        $groups = [];

        if (isset($options['group_by'])) {
            $groups = $this->groupNodes($nodes, $options['group_by']);
        }

        $result = new PageRankResult($nodes, [
            'iterations' => $calculation['iterations'],
            'converged' => $calculation['converged'],
            'damping_factor' => $damping,
            'threshold' => $threshold,
            'max_iterations' => $maxIterations,
            'total_nodes' => count($scores),
            'context' => $chain->getContext(),
            'baseline_key' => $chain->getCacheKey(),
        ], $groups);

        return $this->finalizePayload($result, (bool) ($options['include_metadata'] ?? false));
    }

    /**
     * @param  array<string, array<string, float>>  $model
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, float>>
     */
    private function resolveGraph(MarkovableChain $chain, array $model, array $options): array
    {
        if (isset($options['graph']) && is_array($options['graph'])) {
            return $options['graph'];
        }

        if (isset($options['graph_builder'])) {
            $builder = $options['graph_builder'];

            if ($builder instanceof PageRankGraphBuilder) {
                return $builder->build($chain, $model, $options);
            }

            if ($builder instanceof Closure) {
                $graph = $builder($chain, $model, $options);

                if (is_array($graph)) {
                    return $graph;
                }
            }

            throw new RuntimeException('Invalid PageRank graph builder provided.');
        }

        return $model;
    }

    /**
     * @param  array<string, float>  $scores
     * @return array<string, float>
     */
    private function normalizeScores(array $scores): array
    {
        $min = min($scores);
        $max = max($scores);
        $range = $max - $min;

        if ($range <= 0.0) {
            $probabilities = Statistics::normalizeProbabilities($scores);

            return array_map(static fn ($value) => (float) $value * 100.0, $probabilities);
        }

        $normalized = [];

        foreach ($scores as $node => $score) {
            $normalized[$node] = ($score - $min) / $range * 100.0;
        }

        return $normalized;
    }

    /**
     * @param  array<string, float>  $scores
     * @return array<string, float>
     */
    private function percentiles(array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        arsort($scores);
        $nodes = array_keys($scores);
        $count = count($nodes);
        $scale = max(1, $count - 1);

        $percentiles = [];

        foreach ($nodes as $index => $node) {
            $percentiles[$node] = 100.0 - ($index / $scale * 100.0);
        }

        return $percentiles;
    }

    /**
     * @param  array<string, PageRankNode>  $nodes
     * @param  callable|string  $groupBy
     * @return array<string, array<string, array<string, float>>>
     */
    private function groupNodes(array $nodes, $groupBy): array
    {
        $groups = [];

        foreach ($nodes as $identifier => $node) {
            $groupKey = $this->resolveGroupKey($identifier, $groupBy);
            $groups[$groupKey][$identifier] = $node->toArray();
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param  callable|string  $groupBy
     */
    private function resolveGroupKey(string $identifier, $groupBy): string
    {
        if (is_callable($groupBy)) {
            return (string) $groupBy($identifier);
        }

        if (is_string($groupBy)) {
            if (preg_match('/^segment:(\d+)$/', $groupBy, $matches)) {
                return $this->segment($identifier, (int) $matches[1]);
            }

            if ($groupBy === 'domain') {
                return parse_url($identifier, PHP_URL_HOST) ?: $this->segment($identifier, 0);
            }

            if ($groupBy === 'prefix') {
                return $this->segment($identifier, 0);
            }
        }

        return $this->segment($identifier, 0);
    }

    private function segment(string $identifier, int $index): string
    {
        $trimmed = trim($identifier);

        if ($trimmed === '') {
            return 'unknown';
        }

        $parts = preg_split('/[\s>]+|\//', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts[$index] ?? ($parts[0] ?? $trimmed);
    }

    private function sanitizeDamping(?float $value): float
    {
        if ($value === null) {
            return self::DEFAULT_DAMPING;
        }

        if ($value < 0.0) {
            return 0.0;
        }

        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }

    private function sanitizeThreshold(?float $value): float
    {
        if ($value === null || $value <= 0.0) {
            return self::DEFAULT_THRESHOLD;
        }

        return $value;
    }

    private function sanitizeIterations(?int $value): int
    {
        if ($value === null || $value <= 0) {
            return self::DEFAULT_MAX_ITERATIONS;
        }

        return $value;
    }

    private function finalizePayload(PageRankResult $result, bool $includeMetadata): array
    {
        $payload = $result->toPayload($includeMetadata);
        $payload['__result'] = $includeMetadata ? $result : $result->withoutMetadata();

        return $payload;
    }
}
