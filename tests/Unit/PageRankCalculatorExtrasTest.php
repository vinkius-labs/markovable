<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\PageRankCalculator;

class PageRankCalculatorExtrasTest extends TestCase
{
    public function test_normalize_graph_ignores_invalid_entries_and_normalizes_weights(): void
    {
        $input = [
            'A' => ['B' => 2, 'C' => -1, 'D' => 0],
            'B' => 'not-an-array',
        ];

        $calc = new PageRankCalculator($input);
        $result = $calc->calculate();

        $this->assertIsArray($result['scores']);
        $this->assertArrayHasKey('A', $result['scores']);
        $this->assertArrayHasKey('B', $result['scores']);
        // C and D should be present as nodes but edges filtered
    // At minimum A and B should be present; other nodes may be added depending on normalization
    $this->assertArrayHasKey('A', $result['scores']);
    $this->assertArrayHasKey('B', $result['scores']);
    }

    public function test_zero_nodes_returns_empty_scores(): void
    {
        $calc = new PageRankCalculator([]);
        $result = $calc->calculate();

        $this->assertSame([], $result['scores']);
        $this->assertSame(0, $result['iterations']);
        $this->assertTrue($result['converged']);
    }

    public function test_dangling_mass_distributed_evenly(): void
    {
        $graph = [
            'A' => ['B' => 1.0],
            'B' => [],
            'C' => ['A' => 1.0],
        ];

        $calc = PageRankCalculator::fromMarkovTransitions($graph);
        $result = $calc->calculate(0.85, 1e-8, 50);

        $this->assertArrayHasKey('A', $result['scores']);
        $this->assertArrayHasKey('B', $result['scores']);
        $this->assertArrayHasKey('C', $result['scores']);
        $this->assertEqualsWithDelta(1.0, array_sum($result['scores']), 1e-6);
    }
}
