<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Generators\TextGenerator;

class TextGeneratorTest extends TestCase
{
    private TextGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new TextGenerator;
    }

    public function test_generate_returns_empty_string_when_model_is_empty(): void
    {
        $this->assertSame('', $this->generator->generate([], 5));
    }

    public function test_generate_uses_seed_when_available(): void
    {
        $model = [
            '__START__ __START__' => ['hello' => 1.0],
            '__START__ hello' => ['world' => 1.0],
            'hello world' => ['__END__' => 1.0],
        ];

        $text = $this->generator->generate($model, 3, [
            'order' => 2,
            'seed' => 'hello',
            'initial_states' => array_keys($model),
        ]);

        $this->assertSame('world', $text);
    }

    public function test_generate_falls_back_to_initial_state_when_seed_not_found(): void
    {
        $model = [
            '__START__ __START__' => ['welcome' => 1.0],
            '__START__ welcome' => ['friend' => 1.0],
            'welcome friend' => ['__END__' => 1.0],
        ];

        $text = $this->generator->generate($model, 2, [
            'order' => 2,
            'seed' => 'unknown seed',
        ]);

        $this->assertSame('welcome friend', $text);
    }
}
