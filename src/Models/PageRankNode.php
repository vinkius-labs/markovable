<?php

namespace VinkiusLabs\Markovable\Models;

class PageRankNode
{
    private string $identifier;

    private float $rawScore;

    private float $normalizedScore;

    private float $percentile;

    /**
     * @param  float  $rawScore
     * @param  float  $normalizedScore
     * @param  float  $percentile
     */
    public function __construct(string $identifier, float $rawScore, float $normalizedScore, float $percentile)
    {
        $this->identifier = $identifier;
        $this->rawScore = $rawScore;
        $this->normalizedScore = $normalizedScore;
        $this->percentile = $percentile;
    }

    public function id(): string
    {
        return $this->identifier;
    }

    public function rawScore(): float
    {
        return $this->rawScore;
    }

    public function normalizedScore(): float
    {
        return $this->normalizedScore;
    }

    public function percentile(): float
    {
        return $this->percentile;
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'raw_score' => $this->round($this->rawScore),
            'normalized_score' => $this->round($this->normalizedScore, 2),
            'percentile' => $this->round($this->percentile, 1),
        ];
    }

    private function round(float $value, int $precision = 6): float
    {
        return round($value, $precision);
    }
}
