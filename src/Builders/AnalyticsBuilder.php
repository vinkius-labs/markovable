<?php

namespace VinkiusLabs\Markovable\Builders;

use DateTimeInterface;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\MarkovableManager;

class AnalyticsBuilder extends MarkovableChain
{
    /** @var array<string, mixed> */
    private array $filters = [];

    public function __construct(MarkovableManager $manager, string $context = 'navigation')
    {
        parent::__construct($manager, $context);
        $this->setAnalyzer('navigation');
    }

    public function fromDateRange(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): self
    {
        if ($from) {
            $this->filters['from'] = $from->format(DateTimeInterface::ATOM);
        }

        if ($to) {
            $this->filters['to'] = $to->format(DateTimeInterface::ATOM);
        }

        return $this;
    }

    public function forLabel(string $label): self
    {
        $this->filters['label'] = $label;

        return $this;
    }

    public function analyze($subject = null, array $options = [])
    {
        return parent::analyze($subject, array_merge($options, $this->filters));
    }

    public function predict(string $seed, int $limit = 3, array $options = [])
    {
        return parent::predict($seed, $limit, array_merge($options, $this->filters));
    }
}




