<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Models\PageRankResult;

class PageRankResultAccessorsTest extends TestCase
{
    public function test_accessors_and_is_empty(): void
    {
        $node = new PageRankNode('n', 0.1, 10.0, 50.0);
        $result = new PageRankResult(['n' => $node], ['foo' => 'bar'], ['g' => ['n' => $node->toArray()]]);

        $this->assertSame(['n' => $node], $result->nodes());
        $this->assertSame(['foo' => 'bar'], $result->metadata());
        $this->assertSame(['g' => ['n' => $node->toArray()]], $result->groups());
        $this->assertFalse($result->isEmpty());

        $empty = new PageRankResult([]);
        $this->assertTrue($empty->isEmpty());
    }
}
