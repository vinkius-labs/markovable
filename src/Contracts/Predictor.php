<?php

namespace VinkiusLabs\Markovable\Contracts;

interface Predictor
{
    /**
     * Execute the prediction pipeline and return the computed payload.
     *
     * @return array<int, mixed>
     */
    public function get(): array;
}
