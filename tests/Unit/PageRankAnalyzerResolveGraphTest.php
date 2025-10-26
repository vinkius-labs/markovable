<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankAnalyzerResolveGraphTest extends TestCase
{
    private PageRankAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new PageRankAnalyzer;
    }

    public function test_graph_option_overrides_model(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'k',
        ]);

        $model = ['ignored' => ['A' => 1.0]];
        $graph = ['X' => ['Y' => 1.0], 'Y' => ['X' => 1.0]];

        $payload = $this->analyzer->analyze($chain, $model, ['graph' => $graph]);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayHasKey('X', $payload['pagerank']);
        $this->assertArrayHasKey('Y', $payload['pagerank']);
    }

    public function test_graph_builder_closure_is_used(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'k',
        ]);

        $model = ['ignored' => ['A' => 1.0]];

        $closure = function ($c, $m, $opts) {
            return ['M' => ['N' => 1.0], 'N' => ['M' => 1.0]];
        };

        $payload = $this->analyzer->analyze($chain, $model, ['graph_builder' => $closure]);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayHasKey('M', $payload['pagerank']);
        $this->assertArrayHasKey('N', $payload['pagerank']);
    }

    public function test_invalid_graph_builder_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'k',
        ]);

        $model = ['A' => ['B' => 1.0]];

        $this->analyzer->analyze($chain, $model, ['graph_builder' => 123]);
    }
}
