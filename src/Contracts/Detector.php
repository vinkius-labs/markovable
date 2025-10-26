<?php

namespace VinkiusLabs\Markovable\Contracts;

use VinkiusLabs\Markovable\Support\DetectionContext;

interface Detector
{
    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    public function detect(DetectionContext $context, array $config = []): array;
}
