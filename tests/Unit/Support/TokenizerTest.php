<?php

namespace VinkiusLabs\Markovable\Test\Unit\Support;

use VinkiusLabs\Markovable\Support\Tokenizer;
use VinkiusLabs\Markovable\Test\TestCase;

class TokenizerTest extends TestCase
{
    public function test_corpus_handles_scalar_data_safely(): void
    {
        $corpus = Tokenizer::corpus([123, 'alpha', ['nested' => 456]]);

        $this->assertContains('alpha', $corpus);
        $this->assertContains('456', $corpus);
    }

    public function test_corpus_processes_objects_with_to_array(): void
    {
        $object = new class
        {
            public function toArray(): array
            {
                return ['message' => 'hello world'];
            }
        };

    $corpus = Tokenizer::corpus([$object]);

    $this->assertStringContainsString('hello', implode(' ', $corpus));
    }
}
