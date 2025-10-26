<?php

namespace VinkiusLabs\Markovable\Builders;

use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Predictors\ChurnScorer;
use VinkiusLabs\Markovable\Predictors\LtvPredictor;
use VinkiusLabs\Markovable\Predictors\NextBestActionEngine;
use VinkiusLabs\Markovable\Predictors\SeasonalForecaster;
use VinkiusLabs\Markovable\Support\Dataset;

class PredictiveBuilder
{
    private MarkovableManager $manager;

    private MarkovableChain $baseline;

    private string $baselineKey;

    private ?string $storage;

    private string $context;

    /** @var array<int, array<string, mixed>> */
    private array $dataset = [];

    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(MarkovableManager $manager, string $baselineKey, ?string $storage = null, ?string $context = null)
    {
        $this->manager = $manager;
        $this->baselineKey = $baselineKey;
        $this->storage = $storage;
        $this->context = $context ?? 'text';

        $this->baseline = $this->manager->chain($this->context)->cache($baselineKey, storage: $storage);
        $this->dataset = $this->baseline->getRecords();
    }

    public function context(string $context): self
    {
        $this->context = $context;
        $this->baseline = $this->manager->chain($context)->cache($this->baselineKey, storage: $this->storage);
        $this->dataset = $this->baseline->getRecords();

        return $this;
    }

    public function usingOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $records
     */
    public function dataset(iterable $records): self
    {
        $this->dataset = Dataset::normalize($records);
        $this->baseline->withRecords($this->dataset);

        return $this;
    }

    public function churnScore(): ChurnScorer
    {
        $predictor = new ChurnScorer($this->baseline, $this->resolveDataset());

        if (! empty($this->options['churn']['features'] ?? [])) {
            $predictor->features($this->options['churn']['features']);
        }

        if (! empty($this->options['churn']['include_recommendations'] ?? false)) {
            $predictor->includeRecommendations();
        }

        return $predictor;
    }

    public function ltv(): LtvPredictor
    {
        $predictor = new LtvPredictor($this->baseline, $this->resolveDataset());

        if (isset($this->options['ltv']['first_days'])) {
            $predictor->fromFirstDays((int) $this->options['ltv']['first_days']);
        }

        if (isset($this->options['ltv']['segments']) && is_array($this->options['ltv']['segments'])) {
            $predictor->segments($this->options['ltv']['segments']);
        }

        if (! empty($this->options['ltv']['include_historical'])) {
            $predictor->includeHistoricalComparison();
        }

        return $predictor;
    }

    public function nextBestAction(): NextBestActionEngine
    {
        $engine = new NextBestActionEngine($this->baseline, $this->resolveDataset());

        if (! empty($this->options['next_best_action']['exclude'] ?? [])) {
            $engine->excludeActions($this->options['next_best_action']['exclude']);
        }

        return $engine;
    }

    public function seasonalForecast(): SeasonalForecaster
    {
        $forecaster = new SeasonalForecaster($this->baseline, $this->resolveDataset());

        if (isset($this->options['forecast']['series'])) {
            $forecaster->series($this->options['forecast']['series']);
        }

        if (! empty($this->options['forecast']['metric'])) {
            $forecaster->metric($this->options['forecast']['metric']);
        }

        if (! empty($this->options['forecast']['window'])) {
            $forecaster->window($this->options['forecast']['window']);
        }

        if (! empty($this->options['forecast']['horizon'])) {
            $forecaster->horizon((int) $this->options['forecast']['horizon']);
        }

        if (! empty($this->options['forecast']['components']) && is_array($this->options['forecast']['components'])) {
            $forecaster->decompose($this->options['forecast']['components']);
        }

        if (! empty($this->options['forecast']['confidence'])) {
            $forecaster->includeConfidenceIntervals((float) $this->options['forecast']['confidence']);
        }

        return $forecaster;
    }

    public function baseline(): MarkovableChain
    {
        return $this->baseline;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveDataset(): array
    {
        if (empty($this->dataset)) {
            $this->dataset = $this->baseline->getRecords();
        }

        return $this->dataset;
    }
}
