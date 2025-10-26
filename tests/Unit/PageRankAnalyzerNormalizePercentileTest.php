<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankAnalyzerNormalizePercentileTest extends TestCase
{
    private PageRankAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new PageRankAnalyzer;
    }

    public function test_equal_scores_produce_normalized_percentiles_expected(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'key',
        ]);

        // Symmetric graph should yield equal raw scores
        $model = [
            'A' => ['B' => 1.0],
            'B' => ['A' => 1.0],
        ];

        $payload = $this->analyzer->analyze($chain, $model, ['include_metadata' => true]);

        $this->assertArrayHasKey('pagerank', $payload);

        $pagerank = $payload['pagerank'];
        $this->assertCount(2, $pagerank);

        // Normalized scores should be equal and sum roughly to 100 for two nodes
        $normalized = array_column($pagerank, 'normalized_score');
        $this->assertEqualsWithDelta($normalized[0], $normalized[1], 0.0001);

        // Percentiles should include 100 and 0 (ordered by score)
        $percentiles = array_column($pagerank, 'percentile');
        $this->assertContains(100.0, $percentiles);
        $this->assertContains(0.0, $percentiles);
    }

    public function test_sanitize_threshold_and_iterations_defaults_used(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'key',
        ]);

        $model = [
            'A' => ['B' => 1.0],
            'B' => ['A' => 1.0],
        ];

        $payload = $this->analyzer->analyze($chain, $model, ['threshold' => -1.0, 'max_iterations' => 0, 'include_metadata' => true]);

        $this->assertArrayHasKey('metadata', $payload);
        $metadata = $payload['metadata'];

        $this->assertArrayHasKey('threshold', $metadata);
        $this->assertArrayHasKey('max_iterations', $metadata);

        $this->assertSame(1.0e-6, $metadata['threshold']);
        $this->assertSame(100, $metadata['max_iterations']);
    }
}
