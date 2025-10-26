<?php

namespace VinkiusLabs\Markovable\Contracts;

interface Generator
{
    /**
     * @param  array<string, array<string, float>>  $model
     * @param  array<string, mixed>  $options
     */
    public function generate(array $model, int $length, array $options = []): string;
}
