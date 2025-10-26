<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Analyzers\TextAnalyzer;
use VinkiusLabs\Markovable\MarkovableChain;

class TextAnalyzerTest extends TestCase
{
    private TextAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new TextAnalyzer;
    }

    public function test_analyze_returns_ranked_predictions(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getOrder' => 2,
        ]);

        $model = [
            '__START__ __START__' => ['hello' => 0.6, 'hi' => 0.4],
            '__START__ hello' => ['world' => 0.7, 'friend' => 0.3],
        ];

        $result = $this->analyzer->analyze($chain, $model, [
            'seed' => 'hello',
            'order' => 2,
            'initial_states' => array_keys($model),
            'limit' => 1,
        ]);

        $this->assertSame('__START__ hello', $result['prefix']);
        $this->assertSame('hello', $result['seed']);
        $this->assertCount(1, $result['predictions']);
        $this->assertSame('world', $result['predictions'][0]['sequence']);
        $this->assertSame(0.7, $result['predictions'][0]['probability']);
    }

    public function test_analyze_falls_back_to_first_initial_state_when_seed_unknown(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getOrder' => 2,
        ]);

        $model = [
            '__START__ __START__' => ['start' => 1.0],
            '__START__ start' => ['next' => 0.5, '__END__' => 0.5],
        ];

        $result = $this->analyzer->analyze($chain, $model, [
            'seed' => 'unknown seed',
            'initial_states' => array_keys($model),
        ]);

        $this->assertSame('__START__ __START__', $result['prefix']);
        $this->assertSame('unknown seed', $result['seed']);
        $this->assertSame('start', $result['predictions'][0]['sequence']);
    }
}
