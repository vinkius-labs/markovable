<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Models\PageRankResult;

class PageRankResultTest extends TestCase
{
    public function test_to_payload_includes_metadata_and_groups(): void
    {
        $nodes = [
            'A' => new PageRankNode('A', 0.4, 80.1234, 90.1234),
            'B' => new PageRankNode('B', 0.1, 20.5, 10.1),
        ];

        $groups = [
            'group-a' => ['A' => $nodes['A']->toArray()],
        ];

        $metadata = ['iterations' => 10, 'converged' => true];

        $result = new PageRankResult($nodes, $metadata, $groups);
        $payload = $result->toPayload(true);

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayHasKey('metadata', $payload);
        $this->assertArrayHasKey('groups', $payload);
        $this->assertSame(80.12, $payload['pagerank']['A']['normalized_score']);
        $this->assertSame(90.1, $payload['pagerank']['A']['percentile']);
        $this->assertSame($metadata, $payload['metadata']);
        $this->assertSame($groups, $payload['groups']);
    }

    public function test_without_metadata_returns_new_instance(): void
    {
        $node = new PageRankNode('X', 0.5, 100.0, 100.0);
        $result = new PageRankResult(['X' => $node], ['iterations' => 1]);

        $stripped = $result->withoutMetadata();

        $this->assertNotSame($result, $stripped);
        $this->assertSame([], $stripped->metadata());
        $this->assertSame(['pagerank' => ['X' => $node->toArray()]], $stripped->toPayload(false));
    }
}
