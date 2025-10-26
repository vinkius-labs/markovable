<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use VinkiusLabs\Markovable\Analyzers\PageRankCalculator;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Models\PageRankResult;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankRemainingCoverageTest extends TestCase
{
    public function test_calculate_returns_not_converged_when_iterations_exhausted(): void
    {
        $graph = [
            'A' => ['B' => 1.0],
            'B' => ['C' => 1.0],
            'C' => ['A' => 1.0],
        ];

        $calc = PageRankCalculator::fromMarkovTransitions($graph);
        $result = $calc->calculate(0.85, 1e-12, 1); // force very small iterations

    $this->assertArrayHasKey('scores', $result);
    $this->assertSame(1, $result['iterations']);
    $this->assertIsBool($result['converged']);
    }

    public function test_sanitizers_private_methods_behave(): void
    {
        $analyzer = new PageRankAnalyzer;

        $refD = new ReflectionMethod(PageRankAnalyzer::class, 'sanitizeDamping');
        $refD->setAccessible(true);

        $this->assertSame(0.0, $refD->invoke($analyzer, -2.0));
        $this->assertSame(1.0, $refD->invoke($analyzer, 2.0));
        $this->assertSame(0.85, $refD->invoke($analyzer, null));

        $refT = new ReflectionMethod(PageRankAnalyzer::class, 'sanitizeThreshold');
        $refT->setAccessible(true);
        $this->assertSame(1.0e-6, $refT->invoke($analyzer, -5.0));

        $refI = new ReflectionMethod(PageRankAnalyzer::class, 'sanitizeIterations');
        $refI->setAccessible(true);
        $this->assertSame(100, $refI->invoke($analyzer, 0));
    }

    public function test_pagerank_result_to_array_and_payload_variants(): void
    {
        $node = new PageRankNode('u', 0.3, 30.0, 70.0);
        $result = new PageRankResult(['u' => $node], ['iterations' => 2], ['g' => ['u' => $node->toArray()]]);

        $arr = $result->toArray();
        $this->assertArrayHasKey('pagerank', $arr);
        $this->assertArrayHasKey('metadata', $arr);

        $payloadNoMeta = $result->toPayload(false);
        $this->assertArrayHasKey('pagerank', $payloadNoMeta);
        $this->assertArrayNotHasKey('metadata', $payloadNoMeta);
    }
}
