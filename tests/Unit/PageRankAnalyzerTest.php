<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankAnalyzerTest extends TestCase
{
    private PageRankAnalyzer $analyzer;

    /** @var MockObject&MarkovableChain */
    private $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new PageRankAnalyzer;
        $this->chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'navigation',
            'getCacheKey' => 'baseline-key',
        ]);
    }

    public function test_analyze_returns_payload_with_result_object(): void
    {
        $model = [
            'A' => ['B' => 0.5, 'C' => 0.5],
            'B' => ['C' => 1.0],
            'C' => ['A' => 1.0],
        ];

        $payload = $this->analyzer->analyze($this->chain, $model, [
            'top' => 2,
            'include_metadata' => true,
        ]);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayHasKey('__result', $payload);
        $this->assertArrayHasKey('metadata', $payload);
        $this->assertCount(2, $payload['pagerank']);
        $this->assertSame('navigation', $payload['metadata']['context']);
        $this->assertSame('baseline-key', $payload['metadata']['baseline_key']);
        $this->assertInstanceOf(\VinkiusLabs\Markovable\Models\PageRankResult::class, $payload['__result']);
    }

    public function test_grouping_uses_default_segment_logic(): void
    {
        $model = [
            '/home' => ['/products' => 1.0],
            '/products' => ['/checkout' => 1.0],
            '/checkout' => ['/home' => 1.0],
        ];

        $payload = $this->analyzer->analyze($this->chain, $model, [
            'group_by' => 'segment:0',
        ]);

        $this->assertArrayHasKey('groups', $payload);
        $this->assertArrayHasKey('home', $payload['groups']);
        $this->assertArrayHasKey('/home', $payload['groups']['home']);
    }

    public function test_graph_builder_can_override_graph(): void
    {
        $model = [
            'ignored' => ['A' => 1.0],
        ];

        $builder = new class implements PageRankGraphBuilder {
            public function build(MarkovableChain $baseline, array $model, array $options = []): array
            {
                return [
                    'X' => ['Y' => 1.0],
                    'Y' => ['X' => 1.0],
                ];
            }
        };

        $payload = $this->analyzer->analyze($this->chain, $model, [
            'graph_builder' => $builder,
            'include_metadata' => false,
        ]);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertArrayHasKey('X', $payload['pagerank']);
        $this->assertArrayHasKey('Y', $payload['pagerank']);
    }
}
