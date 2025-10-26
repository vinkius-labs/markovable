<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankAnalyzerExtrasTest extends TestCase
{
    private PageRankAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new PageRankAnalyzer;
    }

    public function test_sanitize_damping_bounds_results_in_valid_scores(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'key',
        ]);

        $model = [
            'A' => ['B' => 1.0],
            'B' => ['A' => 1.0],
        ];

        // damping below 0 -> treated as 0
        $payloadLow = $this->analyzer->analyze($chain, $model, ['damping' => -1.0]);
        $this->assertArrayHasKey('pagerank', $payloadLow);

        // damping above 1 -> treated as 1
        $payloadHigh = $this->analyzer->analyze($chain, $model, ['damping' => 2.0]);
        $this->assertArrayHasKey('pagerank', $payloadHigh);

        $this->assertCount(2, $payloadLow['pagerank']);
        $this->assertCount(2, $payloadHigh['pagerank']);
    }

    public function test_group_by_domain_and_prefix_and_callable(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'key',
        ]);

        $model = [
            'https://example.com/home' => ['https://example.com/a' => 1.0],
            'https://other.test/page' => ['https://example.com/home' => 1.0],
        ];

        $payloadDomain = $this->analyzer->analyze($chain, $model, ['group_by' => 'domain']);
        $this->assertArrayHasKey('groups', $payloadDomain);

        $payloadPrefix = $this->analyzer->analyze($chain, $model, ['group_by' => 'prefix']);
        $this->assertArrayHasKey('groups', $payloadPrefix);

        $payloadCallable = $this->analyzer->analyze($chain, $model, ['group_by' => function (string $id) {
            return strtoupper($id);
        }]);

        $this->assertArrayHasKey('groups', $payloadCallable);
    }

    public function test_empty_identifier_segment_returns_unknown(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'key',
        ]);

        $model = [
            '' => [],
        ];

        $payload = $this->analyzer->analyze($chain, $model, ['group_by' => 'prefix']);

        $this->assertArrayHasKey('groups', $payload);
        $this->assertArrayHasKey('unknown', $payload['groups']);
    }
}
