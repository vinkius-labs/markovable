<?php

namespace VinkiusLabs\Markovable\Builders;

use RuntimeException;
use VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Models\PageRankResult;

class PageRankBuilder
{
    private MarkovableChain $chain;

    private float $damping = 0.85;

    private float $threshold = 1.0e-6;

    private int $maxIterations = 100;

    private bool $includeMetadata = false;

    private ?int $topNodes = null;

    /** @var callable|string|null */
    private $groupBy = null;

    /** @var array<string, array<string, float>>|null */
    private ?array $graph = null;

    private ?PageRankGraphBuilder $graphBuilder = null;

    private ?PageRankResult $cachedResult = null;

    public function __construct(MarkovableChain $chain)
    {
        $this->chain = $chain;
        $this->chain->setAnalyzer('pagerank');
    }

    public function dampingFactor(float $value): self
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new RuntimeException('Damping factor must be between 0 and 1.');
        }

        $this->damping = $value;
        $this->cachedResult = null;

        return $this;
    }

    public function convergenceThreshold(float $value): self
    {
        if ($value <= 0.0) {
            throw new RuntimeException('Convergence threshold must be greater than zero.');
        }

        $this->threshold = $value;
        $this->cachedResult = null;

        return $this;
    }

    public function maxIterations(int $max): self
    {
        if ($max <= 0) {
            throw new RuntimeException('Maximum iterations must be greater than zero.');
        }

        $this->maxIterations = $max;
        $this->cachedResult = null;

        return $this;
    }

    public function includeMetadata(bool $flag = true): self
    {
        $this->includeMetadata = $flag;

        return $this;
    }

    public function topNodes(int $limit): self
    {
        if ($limit <= 0) {
            throw new RuntimeException('Top nodes limit must be greater than zero.');
        }

        $this->topNodes = $limit;
        $this->cachedResult = null;

        return $this;
    }

    /**
     * @param  callable|string  $groupBy
     */
    public function groupBy($groupBy): self
    {
        if (! is_string($groupBy) && ! is_callable($groupBy)) {
            throw new RuntimeException('Group by must be a string or callable.');
        }

        $this->groupBy = $groupBy;
        $this->cachedResult = null;

        return $this;
    }

    /**
     * @param  array<string, array<string, float>>  $graph
     */
    public function withGraph(array $graph): self
    {
        $this->graph = $graph;
        $this->cachedResult = null;

        return $this;
    }

    public function useGraphBuilder(PageRankGraphBuilder $builder): self
    {
        $this->graphBuilder = $builder;
        $this->cachedResult = null;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(): array
    {
        $result = $this->resolveResult();

        return $result->toPayload($this->includeMetadata);
    }

    public function result(): PageRankResult
    {
        return $this->resolveResult();
    }

    public function reset(): self
    {
        $this->cachedResult = null;

        return $this;
    }

    private function resolveResult(): PageRankResult
    {
        if ($this->cachedResult !== null) {
            return $this->cachedResult;
        }

        $options = array_filter([
            'damping' => $this->damping,
            'threshold' => $this->threshold,
            'max_iterations' => $this->maxIterations,
            'include_metadata' => true,
            'top' => $this->topNodes,
            'group_by' => $this->groupBy,
            'graph' => $this->graph,
            'graph_builder' => $this->graphBuilder,
        ], static function ($value) {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null;
        });

        $payload = $this->chain->analyze(null, $options);

        if (! is_array($payload) || ! isset($payload['__result']) || ! $payload['__result'] instanceof PageRankResult) {
            throw new RuntimeException('PageRank analyzer did not return the expected result payload.');
        }

        return $this->cachedResult = $payload['__result'];
    }
}
