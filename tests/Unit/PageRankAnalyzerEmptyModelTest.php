<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankAnalyzerEmptyModelTest extends TestCase
{
    public function test_analyze_with_empty_model_returns_empty_result_metadata(): void
    {
        $analyzer = new PageRankAnalyzer;

        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'k',
        ]);

        $payload = $analyzer->analyze($chain, [], ['include_metadata' => true]);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertSame([], $payload['pagerank']);

        $this->assertArrayHasKey('__result', $payload);
        $result = $payload['__result'];
        $this->assertSame(0, $result->metadata()['total_nodes'] ?? 0);
    }
}
