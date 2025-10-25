<?php

namespace VinkiusLabs\Markovable\Testing;

use PHPUnit\Framework\Assert as PHPUnit;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\MarkovableChain;

trait MarkovableAssertions
{
    public function assertMarkovableGenerated(?string $text, int $minWords = 1): void
    {
        PHPUnit::assertNotNull($text, 'Generated text must not be null.');
        $words = preg_split('/\s+/u', trim((string) $text)) ?: [];
        PHPUnit::assertGreaterThanOrEqual($minWords, count($words), 'Generated text contains fewer words than expected.');
    }

    public function assertMarkovableTrained(MarkovableChain $chain): void
    {
        PHPUnit::assertNotEmpty($chain->toProbabilities(), 'Markovable model was not trained.');
    }

    public function markovableChain(array $data, int $order = 2): MarkovableChain
    {
        return Markovable::chain('text')->order($order)->trainFrom($data);
    }
}
