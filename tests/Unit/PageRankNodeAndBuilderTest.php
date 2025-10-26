<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Builders\PageRankBuilder;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankNodeAndBuilderTest extends TestCase
{
    public function test_node_getters_and_to_array(): void
    {
        $node = new PageRankNode('node-1', 0.123456789, 50.9876, 88.8888);

        $this->assertSame('node-1', $node->id());
    $this->assertEqualsWithDelta(0.123457, $node->rawScore(), 1e-6);
    $this->assertEqualsWithDelta(50.99, $node->normalizedScore(), 0.01);
    $this->assertEqualsWithDelta(88.9, $node->percentile(), 0.1);

        $arr = $node->toArray();
        $this->assertArrayHasKey('raw_score', $arr);
        $this->assertArrayHasKey('normalized_score', $arr);
        $this->assertArrayHasKey('percentile', $arr);
    }

    public function test_builder_validation_methods_throw(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, []);
        $builder = new PageRankBuilder($chain);

        $this->expectException(\RuntimeException::class);
        $builder->dampingFactor(2.0);
    }
}
