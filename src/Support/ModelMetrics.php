<?php

namespace VinkiusLabs\Markovable\Support;

use VinkiusLabs\Markovable\MarkovableChain;

class ModelMetrics
{
    private int $stateCount;

    private int $transitionCount;

    private float $averageProbability;

    private float $maxProbability;

    private float $confidenceScore;

    private int $sequenceCount;

    private int $uniqueSequences;

    private function __construct()
    {
    }

    public static function fromChain(MarkovableChain $chain): self
    {
        $instance = new self;
        $model = $chain->toProbabilities();

        $stateCount = count($model);
        $transitionCount = 0;
        $sumProbabilities = 0.0;
        $probabilitySamples = 0;
        $maxProbability = 0.0;

        foreach ($model as $distribution) {
            foreach ($distribution as $probability) {
                $transitionCount++;
                $value = (float) $probability;
                $sumProbabilities += $value;
                $probabilitySamples++;
                $maxProbability = max($maxProbability, $value);
            }
        }

        $sequenceFrequencies = $chain->getSequenceFrequencies();
        $totalSequences = array_sum($sequenceFrequencies);
        $topFrequency = $sequenceFrequencies === [] ? 0 : max($sequenceFrequencies);

        $instance->stateCount = $stateCount;
        $instance->transitionCount = $transitionCount;
        $instance->averageProbability = $probabilitySamples > 0
            ? $sumProbabilities / $probabilitySamples
            : 0.0;
        $instance->maxProbability = $maxProbability;
        $instance->confidenceScore = $totalSequences > 0
            ? round($topFrequency / $totalSequences, 4)
            : 0.0;
        $instance->sequenceCount = (int) $totalSequences;
        $instance->uniqueSequences = count($sequenceFrequencies);

        return $instance;
    }

    public function stateCount(): int
    {
        return $this->stateCount;
    }

    public function transitionCount(): int
    {
        return $this->transitionCount;
    }

    public function averageProbability(): float
    {
        return $this->averageProbability;
    }

    public function maxProbability(): float
    {
        return $this->maxProbability;
    }

    public function confidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function sequenceCount(): int
    {
        return $this->sequenceCount;
    }

    public function uniqueSequences(): int
    {
        return $this->uniqueSequences;
    }

    public function toArray(): array
    {
        return [
            'states' => $this->stateCount,
            'transitions' => $this->transitionCount,
            'average_probability' => $this->averageProbability,
            'max_probability' => $this->maxProbability,
            'confidence' => $this->confidenceScore,
            'sequence_count' => $this->sequenceCount,
            'unique_sequences' => $this->uniqueSequences,
        ];
    }
}
