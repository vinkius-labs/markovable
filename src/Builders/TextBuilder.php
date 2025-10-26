<?php

namespace VinkiusLabs\Markovable\Builders;

use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\MarkovableManager;

class TextBuilder extends MarkovableChain
{
    private ?string $seed = null;

    public function __construct(MarkovableManager $manager, string $context = 'text')
    {
        parent::__construct($manager, $context);
    }

    public function seed(?string $seed): self
    {
        $this->seed = $seed;

        return $this;
    }

    public function startWith(string $seed): self
    {
        return $this->seed($seed);
    }

    public function generate(?int $words = null, array $options = []): string
    {
        if ($this->seed) {
            $options['seed'] = $this->seed;
        }

        return parent::generate($words, $options);
    }
}
