<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Models\PageRankResult;
use VinkiusLabs\Markovable\Test\TestCase;

class PageRankBuilderTest extends TestCase
{
    public function test_builder_calculates_and_caches_results(): void
    {
        $chain = Markovable::chain('navigation')
            ->train([
                '/home /products /checkout',
                '/home /products /cart',
                '/landing /signup /home',
            ])
            ->cache('navigation-baseline');

        $matrix = $chain->getTransitionMatrix();
        $this->assertNotEmpty($matrix);

    $builder = $chain->pageRank()->withGraph($matrix)->topNodes(2);

        $payload = $builder->calculate();

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertCount(2, $payload['pagerank']);

        $result = $builder->result();
        $this->assertInstanceOf(PageRankResult::class, $result);
        $this->assertNotEmpty($result->nodes());
        $this->assertSame('navigation', $result->metadata()['context']);

    $builder->reset()->includeMetadata();
        $withMetadata = $builder->calculate();

        $this->assertArrayHasKey('metadata', $withMetadata);
        $this->assertArrayHasKey('total_nodes', $withMetadata['metadata']);
        $this->assertGreaterThanOrEqual(2, $withMetadata['metadata']['total_nodes']);
    }

    public function test_builder_can_use_custom_graph_builder(): void
    {
        $chain = Markovable::chain('navigation')->train(['/a /b']);

        $builder = $chain->pageRank()->useGraphBuilder(new class implements \VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder {
            public function build(\VinkiusLabs\Markovable\MarkovableChain $baseline, array $model, array $options = []): array
            {
                return [
                    'X' => ['Y' => 1.0],
                    'Y' => ['X' => 1.0],
                ];
            }
        });

        $payload = $builder->calculate();

        $this->assertArrayHasKey('pagerank', $payload);
        $this->assertArrayHasKey('X', $payload['pagerank']);
        $this->assertArrayHasKey('Y', $payload['pagerank']);
    }
}
