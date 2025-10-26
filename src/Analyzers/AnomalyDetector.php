<?php

namespace VinkiusLabs\Markovable\Analyzers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use VinkiusLabs\Markovable\Contracts\Detector as DetectorContract;
use VinkiusLabs\Markovable\Detectors\DriftDetector;
use VinkiusLabs\Markovable\Detectors\EmergingPatternDetector;
use VinkiusLabs\Markovable\Detectors\UnseenSequenceDetector;
use VinkiusLabs\Markovable\Events\AnomalyDetected;
use VinkiusLabs\Markovable\Events\PatternEmerged;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Models\AnomalyRecord;
use VinkiusLabs\Markovable\Support\DetectionContext;
use VinkiusLabs\Markovable\Support\Tokenizer;

class AnomalyDetector
{
    private MarkovableChain $chain;

    private DetectionContext $context;

    /** @var array<string, DetectorContract> */
    private array $detectors = [];

    /** @var array<string, mixed> */
    private array $config = [];

    private bool $persist;

    private bool $dispatchEvents;

    private string $baselineKey;

    private ?string $storageName;

    private float $defaultSeasonalityThreshold;

    public function __construct(MarkovableChain $chain, string $baselineKey, ?string $storageName = null)
    {
        $this->chain = $chain;
        $this->baselineKey = $baselineKey;
        $this->storageName = $storageName;

        $manager = $chain->getManager();
        $storage = $manager->storage($storageName);
        $baselinePayload = $storage->get($baselineKey);

        if (! $baselinePayload) {
            throw new RuntimeException("Baseline [{$baselineKey}] could not be found in storage.");
        }

        $this->context = new DetectionContext($chain, $baselinePayload, $baselineKey, $storageName);

        $configuration = (array) $manager->config('anomaly', []);
        $this->persist = (bool) ($configuration['persist'] ?? false);
        $this->dispatchEvents = (bool) ($configuration['dispatch_events'] ?? true);
        $this->defaultSeasonalityThreshold = (float) ($configuration['seasonality']['threshold'] ?? 0.3);

        $this->config = [
            'threshold' => (float) ($configuration['default_threshold'] ?? 0.05),
            'seasonality_threshold' => $this->defaultSeasonalityThreshold,
        ];
    }

    public function unseenSequences(): self
    {
        return $this->addDetector('unseenSequence', new UnseenSequenceDetector());
    }

    public function emergingPatterns(): self
    {
        return $this->addDetector('emergingPattern', new EmergingPatternDetector());
    }

    public function detectSeasonality(): self
    {
        return $this->addDetector('seasonality', new SeasonalAnalyzer());
    }

    public function drift(): self
    {
        return $this->addDetector('drift', new DriftDetector());
    }

    public function minLength(int $length): self
    {
        $this->config['minLength'] = max(1, $length);

        return $this;
    }

    public function threshold(float $value): self
    {
        $value = max(0.0, $value);
        $this->config['threshold'] = $value;

        if (($this->config['seasonality_threshold'] ?? null) === $this->defaultSeasonalityThreshold) {
            $this->config['seasonality_threshold'] = $value;
        }

        return $this;
    }

    public function seasonalityThreshold(float $value): self
    {
        $this->config['seasonality_threshold'] = max(0.0, $value);

        return $this;
    }

    public function minimumFrequency(int $value): self
    {
        $this->config['minFrequency'] = max(1, $value);

        return $this;
    }

    public function comparedTo(string $label): self
    {
        $this->config['comparison'] = $label;

        return $this;
    }

    public function confidenceLevel(float $value): self
    {
        $this->config['confidence'] = max(0.0, min(1.0, $value));

        return $this;
    }

    public function orderBy(string $field): self
    {
        $this->config['order_by'] = $field;

        return $this;
    }

    public function window(string $value): self
    {
        $this->config['window'] = $value;

        return $this;
    }

    public function metrics(array $metrics): self
    {
        $this->config['metrics'] = array_values($metrics);

        return $this;
    }

    public function seasonalityData(array $data): self
    {
        $this->config['seasonality_data'] = $data;

        return $this;
    }

    public function withoutPersistence(): self
    {
        $this->persist = false;

        return $this;
    }

    public function withoutEvents(): self
    {
        $this->dispatchEvents = false;

        return $this;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<int, array<string, mixed>>
     */
    public function get(array $overrides = []): array
    {
        $config = array_merge($this->config, $overrides);
        $results = [];

        foreach ($this->detectors as $type => $detector) {
            $detections = $detector->detect($this->context, $config);

            foreach ($detections as $detection) {
                $results[] = $detection + ['type' => $detection['type'] ?? $type];
            }
        }

        if (! empty($config['order_by'])) {
            usort($results, function ($left, $right) use ($config) {
                $field = $config['order_by'];
                $leftValue = $left[$field] ?? null;
                $rightValue = $right[$field] ?? null;

                if (is_numeric($leftValue) && is_numeric($rightValue)) {
                    return $rightValue <=> $leftValue;
                }

                return $leftValue <=> $rightValue;
            });
        }

        $this->persistIfNeeded($results);
        $this->dispatchEventsIfNeeded($results);

        return $results;
    }

    public function getContext(): DetectionContext
    {
        return $this->context;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    private function persistIfNeeded(array $results): void
    {
        if (! $this->persist || empty($results) || ! class_exists(AnomalyRecord::class)) {
            return;
        }

        foreach ($results as $result) {
            AnomalyRecord::create([
                'model_key' => $this->baselineKey,
                'type' => $result['type'] ?? 'anomaly',
                'sequence' => $this->normalizeSequence($result),
                'score' => $result['score'] ?? ($result['probability'] ?? 0.0),
                'count' => $result['count'] ?? 1,
                'metadata' => $result,
                'detected_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    private function dispatchEventsIfNeeded(array $results): void
    {
        if (! $this->dispatchEvents || empty($results)) {
            return;
        }

        foreach ($results as $result) {
            $type = $result['type'] ?? 'anomaly';

            if ($type === 'emergingPattern') {
                $pattern = $result['pattern'] ?? [];
                $growth = isset($result['growth_rate']) ? (float) $result['growth_rate'] : (float) ($result['growth'] ?? 0.0);

                Event::dispatch(new PatternEmerged(
                    is_array($pattern) ? $pattern : (array) $pattern,
                    $growth,
                    $result
                ));

                continue;
            }

            Event::dispatch(new AnomalyDetected($this->baselineKey, $result));
        }
    }

    private function addDetector(string $type, DetectorContract $detector): self
    {
        $this->detectors[$type] = $detector;

        return $this;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<int, string>
     */
    private function normalizeSequence(array $result): array
    {
        $sequence = $result['sequence'] ?? ($result['pattern'] ?? []);

        if (is_string($sequence)) {
            return Tokenizer::tokenize($sequence);
        }

        if (is_array($sequence)) {
            return array_values($sequence);
        }

        return [];
    }
}
