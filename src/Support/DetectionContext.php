<?php

namespace VinkiusLabs\Markovable\Support;

use VinkiusLabs\Markovable\MarkovableChain;

class DetectionContext
{
    private MarkovableChain $chain;

    /** @var array<string, array<string, float>> */
    private array $baselineModel;

    /** @var array<string, mixed> */
    private array $baselineMeta;

    /** @var array<string, int> */
    private array $currentSequences;

    private int $order;

    private string $baselineKey;

    private ?string $storageName;

    public function __construct(MarkovableChain $chain, array $baselinePayload, string $baselineKey, ?string $storageName)
    {
        $this->chain = $chain;
        $this->baselineModel = $baselinePayload['model'] ?? [];
        $this->baselineMeta = $baselinePayload['meta'] ?? [];
        $this->order = (int) ($baselinePayload['order'] ?? $chain->getOrder());
        $this->baselineKey = $baselineKey;
        $this->storageName = $storageName;
        $this->currentSequences = $this->buildCurrentSequences($chain->getCorpus());
    }

    public function getChain(): MarkovableChain
    {
        return $this->chain;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getBaselineModel(): array
    {
        return $this->baselineModel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBaselineMeta(): array
    {
        return $this->baselineMeta;
    }

    /**
     * @return array<string, int>
     */
    public function getCurrentSequences(): array
    {
        return $this->currentSequences;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getBaselineKey(): string
    {
        return $this->baselineKey;
    }

    public function getStorageName(): ?string
    {
        return $this->storageName;
    }

    public function totalCurrentSequences(): int
    {
        return array_sum($this->currentSequences);
    }

    public function totalBaselineSequences(): int
    {
        return (int) ($this->baselineMeta['total_sequences'] ?? 0);
    }

    public function countOccurrences(string $sequence): int
    {
        return $this->currentSequences[$sequence] ?? 0;
    }

    public function baselineFrequency(string $sequence): int
    {
        $frequencies = $this->baselineMeta['sequence_frequencies'] ?? [];

        return (int) ($frequencies[$sequence] ?? 0);
    }

    public function probabilityOf(string $sequence): float
    {
        $tokens = Tokenizer::tokenize($sequence);

        if (empty($tokens)) {
            return 0.0;
        }

        return $this->probabilityFromTokens($tokens);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function probabilityFromTokens(array $tokens): float
    {
        if (empty($tokens)) {
            return 0.0;
        }

        $order = $this->order;
        $startTokens = array_fill(0, $order, '__START__');
        $sequence = array_merge($startTokens, $tokens, ['__END__']);
        $probability = 1.0;

        $prefix = implode(' ', array_slice($sequence, 0, $order));
        $totalTokens = count($sequence);

        for ($i = $order; $i < $totalTokens; $i++) {
            $next = $sequence[$i];
            $distribution = $this->baselineModel[$prefix] ?? [];
            $stepProbability = $distribution[$next] ?? 0.0;
            $probability *= $stepProbability;

            if ($probability === 0.0 || $next === '__END__') {
                break;
            }

            $prefix = $this->nextPrefixFrom($prefix, $next, $order);
        }

        return $probability;
    }

    public function patternHistory(string $sequence): array
    {
        $history = $this->baselineMeta['pattern_history'][$sequence] ?? [];

        return is_array($history) ? $history : [];
    }

    public function seasonalityProfile(): array
    {
        $profile = $this->baselineMeta['seasonality_profile'] ?? [];

        return is_array($profile) ? $profile : [];
    }

    /**
     * @param  array<int, string>  $corpus
     * @return array<string, int>
     */
    private function buildCurrentSequences(array $corpus): array
    {
        $sequences = [];

        foreach ($corpus as $record) {
            $tokens = Tokenizer::tokenize($record);

            if (empty($tokens)) {
                continue;
            }

            $normalized = implode(' ', $tokens);
            $sequences[$normalized] = ($sequences[$normalized] ?? 0) + 1;
        }

        return $sequences;
    }

    private function nextPrefixFrom(string $currentPrefix, string $nextToken, int $order): string
    {
        if ($order <= 1) {
            return $nextToken;
        }

        $firstSpace = strpos($currentPrefix, ' ');

        if ($firstSpace === false) {
            return $nextToken;
        }

        return substr($currentPrefix, $firstSpace + 1).' '.$nextToken;
    }
}
