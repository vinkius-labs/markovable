<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use VinkiusLabs\Markovable\Analyzers\PageRankAnalyzer;
use VinkiusLabs\Markovable\Models\PageRankNode;
use VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder;
use VinkiusLabs\Markovable\MarkovableChain;

class PageRankAnalyzerPrivateInvokeTest extends TestCase
{
    public function test_group_nodes_private_method_groups_correctly(): void
    {
        $analyzer = new PageRankAnalyzer;

        $nodes = [
            '/products/1' => new PageRankNode('/products/1', 0.2, 20.0, 50.0),
            '/products/2' => new PageRankNode('/products/2', 0.1, 10.0, 30.0),
            '/about' => new PageRankNode('/about', 0.05, 5.0, 10.0),
        ];

        $ref = new ReflectionMethod(PageRankAnalyzer::class, 'groupNodes');
        $ref->setAccessible(true);

        $groups = $ref->invoke($analyzer, $nodes, 'prefix');

        $this->assertArrayHasKey('products', $groups);
        $this->assertArrayHasKey('about', $groups);
    }

    public function test_resolve_graph_with_graph_builder_instance(): void
    {
        $analyzer = new PageRankAnalyzer;

        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'ctx',
            'getCacheKey' => 'k',
        ]);

        $builder = new class implements PageRankGraphBuilder {
            public function build(MarkovableChain $chain, array $model, array $options = []): array
            {
                return ['X' => ['Y' => 1.0], 'Y' => ['X' => 1.0]];
            }
        };

        $ref = new ReflectionMethod(PageRankAnalyzer::class, 'resolveGraph');
        $ref->setAccessible(true);

        $graph = $ref->invoke($analyzer, $chain, ['ignored' => []], ['graph_builder' => $builder]);

        $this->assertArrayHasKey('X', $graph);
        $this->assertArrayHasKey('Y', $graph);
    }
}
