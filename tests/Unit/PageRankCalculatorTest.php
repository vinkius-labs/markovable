<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\PageRankCalculator;

class PageRankCalculatorTest extends TestCase
{
    public function test_calculate_returns_uniform_for_single_node(): void
    {
        $calculator = new PageRankCalculator(['A' => []]);
        $result = $calculator->calculate();

        $this->assertSame(['A' => 1.0], $result['scores']);
        $this->assertSame(1, $result['iterations']);
        $this->assertTrue($result['converged']);
    }

    public function test_calculate_converges_for_simple_graph(): void
    {
        $graph = [
            'A' => ['B' => 3, 'C' => 1],
            'B' => ['C' => 2],
            'C' => ['A' => 1],
        ];

        $calculator = PageRankCalculator::fromMarkovTransitions($graph);
        $result = $calculator->calculate(0.9, 1e-8, 200);

        $scores = $result['scores'];

        $this->assertArrayHasKey('A', $scores);
        $this->assertArrayHasKey('B', $scores);
        $this->assertArrayHasKey('C', $scores);
        $this->assertGreaterThan(0, $scores['A']);
        $this->assertGreaterThan(0, $scores['B']);
        $this->assertGreaterThan(0, $scores['C']);
        $this->assertGreaterThan($scores['B'], $scores['C']);
        $this->assertTrue($result['converged']);
    }

    public function test_calculate_handles_dangling_nodes(): void
    {
        $calculator = new PageRankCalculator([
            'A' => ['B' => 1],
            'B' => [],
        ]);

        $result = $calculator->calculate(1.2, -5.0, 0);

    $scores = $result['scores'];
    $this->assertCount(2, $scores);
    $this->assertGreaterThan(0, $scores['A']);
    $this->assertGreaterThan(0, $scores['B']);
    $this->assertEqualsWithDelta(1.0, array_sum($scores), 1e-6);
    }

    public function test_from_markov_transitions_adds_missing_nodes(): void
    {
        $calculator = PageRankCalculator::fromMarkovTransitions([
            'A' => ['B' => 1.0],
        ]);

        $result = $calculator->calculate();

        $this->assertArrayHasKey('B', $result['scores']);
        $this->assertArrayHasKey('A', $result['scores']);
    }
}
