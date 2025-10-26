<?php

namespace VinkiusLabs\Markovable\Contracts;

use VinkiusLabs\Markovable\MarkovableChain;

interface PageRankGraphBuilder
{
    /**
     * Build a directed weighted graph from a trained Markov baseline.
     *
     * @param  array<string, array<string, float>>  $model
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, float>>
     */
    public function build(MarkovableChain $baseline, array $model, array $options = []): array;
}
