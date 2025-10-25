<?php

namespace VinkiusLabs\Markovable\Contracts;

use VinkiusLabs\Markovable\MarkovableChain;

interface Analyzer
{
    /**
     * @param array<string, array<string, float>> $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function analyze(MarkovableChain $chain, array $model, array $options = []): array;
}



