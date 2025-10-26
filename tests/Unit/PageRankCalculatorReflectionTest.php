<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use VinkiusLabs\Markovable\Analyzers\PageRankCalculator;

class PageRankCalculatorReflectionTest extends TestCase
{
    public function test_clamp_private_method_behaves_as_expected(): void
    {
        $calc = new PageRankCalculator([]);

        $ref = new ReflectionMethod(PageRankCalculator::class, 'clamp');
        $ref->setAccessible(true);

        $this->assertSame(0.0, $ref->invoke($calc, -5.0, 0.0, 1.0));
        $this->assertSame(1.0, $ref->invoke($calc, 5.0, 0.0, 1.0));
        $this->assertSame(0.5, $ref->invoke($calc, 0.5, 0.0, 1.0));
    }

    public function test_normalize_graph_private_method_creates_expected_nodes(): void
    {
        $input = [
            'A' => ['B' => 2, 'C' => 0],
            'D' => ['A' => 1],
        ];

        $ref = new ReflectionMethod(PageRankCalculator::class, 'normalizeGraph');
        $ref->setAccessible(true);

        $calc = new PageRankCalculator([]);
        $normalized = $ref->invoke($calc, $input);

        // Ensure keys are strings and targets with zero weight removed
        $this->assertArrayHasKey('A', $normalized);
        $this->assertArrayHasKey('B', $normalized);
        $this->assertArrayHasKey('D', $normalized);
        $this->assertIsArray($normalized['A']);
    }
}
