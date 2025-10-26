<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\NavigationAnalyzer;
use VinkiusLabs\Markovable\MarkovableChain;

class NavigationAnalyzerTest extends TestCase
{
    private NavigationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new NavigationAnalyzer;
    }

    public function test_analyze_returns_predictions_with_filters(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getOrder' => 2,
        ]);

        $model = [
            '__START__ __START__' => ['/home' => 0.6, '/about' => 0.4],
            '__START__ /home' => ['/products' => 0.8, '__END__' => 0.2],
        ];

        $result = $this->analyzer->analyze($chain, $model, [
            'seed' => '/home',
            'order' => 2,
            'limit' => 1,
            'initial_states' => array_keys($model),
            'from' => '2024-01-01T00:00:00Z',
            'to' => '2024-01-31T23:59:59Z',
            'label' => 'homepage',
        ]);

        $this->assertSame('__START__ /home', $result['prefix']);
        $this->assertSame('/home', $result['seed']);
        $this->assertSame([
            'from' => '2024-01-01T00:00:00Z',
            'to' => '2024-01-31T23:59:59Z',
            'label' => 'homepage',
        ], $result['filters']);
        $this->assertSame('/products', $result['predictions'][0]['path']);
        $this->assertSame(0.8, $result['predictions'][0]['probability']);
        $this->assertSame(80.0, $result['predictions'][0]['confidence']);
    }

    public function test_analyze_uses_first_initial_state_when_seed_not_found(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getOrder' => 2,
        ]);

        $model = [
            '__START__ __START__' => ['/landing' => 1.0],
            '__START__ /landing' => ['/pricing' => 1.0],
        ];

        $result = $this->analyzer->analyze($chain, $model, [
            'seed' => '/missing',
            'initial_states' => array_keys($model),
        ]);

        $this->assertSame('__START__ __START__', $result['prefix']);
        $this->assertSame('/landing', $result['predictions'][0]['path']);
    }
}
