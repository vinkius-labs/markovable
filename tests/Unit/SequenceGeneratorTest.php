<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Generators\SequenceGenerator;

class SequenceGeneratorTest extends TestCase
{
    public function test_generate_returns_plain_text_by_default(): void
    {
        $generator = new SequenceGenerator();

        $model = [
            '__START__ __START__' => ['A' => 1.0],
            '__START__ A' => ['B' => 1.0],
            'A B' => ['__END__' => 1.0],
        ];

        mt_srand(1);

        $this->assertSame('A B', $generator->generate($model, 2, [
            'order' => 2,
            'initial_states' => ['__START__ __START__'],
        ]));
    }

    public function test_generate_can_return_json_array(): void
    {
        $generator = new SequenceGenerator();

        $model = [
            '__START__ __START__' => ['north' => 1.0],
            '__START__ north' => ['south' => 1.0],
            'north south' => ['__END__' => 1.0],
        ];

        mt_srand(2);

        $output = $generator->generate($model, 2, [
            'order' => 2,
            'as_array' => true,
            'initial_states' => ['__START__ __START__'],
        ]);

        $this->assertJson($output);
        $this->assertSame(['north', 'south'], json_decode($output, true));
    }
}
