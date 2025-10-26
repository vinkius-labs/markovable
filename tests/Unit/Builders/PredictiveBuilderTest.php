<?php

namespace VinkiusLabs\Markovable\Test\Unit\Builders;

use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Builders\PredictiveBuilder;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Predictors\ChurnScorer;
use VinkiusLabs\Markovable\Predictors\LtvPredictor;
use VinkiusLabs\Markovable\Predictors\NextBestActionEngine;
use VinkiusLabs\Markovable\Predictors\SeasonalForecaster;
use VinkiusLabs\Markovable\Support\Dataset;
use VinkiusLabs\Markovable\Test\TestCase;

class PredictiveBuilderTest extends TestCase
{
    public function test_builder_resolves_predictors_with_options(): void
    {
        $manager = app(MarkovableManager::class);
        $baselineKey = 'predictive-builder-'.Str::uuid();
        $raw = [
            [
                'customer_id' => 'cust-1',
                'days_since_signup' => 3,
                'first_purchase_value' => 320,
                'feature_adoption_rate' => 0.45,
                'journey_sequence' => 'signup onboarding'
            ],
        ];

        $manager->chain('text')
            ->order(1)
            ->cache($baselineKey)
            ->train($raw);

        $records = Dataset::normalize($raw);

        $builder = $manager->predictive($baselineKey, [
            'churn' => ['features' => ['feature_adoption_rate']],
            'ltv' => ['segments' => ['prime', 'standard', 'retain']],
            'next_best_action' => ['exclude' => ['upgrade_plan']],
            'forecast' => [
                'metric' => 'value',
                'horizon' => 2,
                'components' => ['day_of_week'],
            ],
        ])
            ->dataset($records);

        $this->assertInstanceOf(ChurnScorer::class, $builder->churnScore());
        $this->assertInstanceOf(LtvPredictor::class, $builder->ltv());
        $this->assertInstanceOf(NextBestActionEngine::class, $builder->nextBestAction());
        $this->assertInstanceOf(SeasonalForecaster::class, $builder->seasonalForecast());
    }

    public function test_context_switch_and_baseline_exposure(): void
    {
        $manager = app(MarkovableManager::class);
        $baselineKey = 'predictive-context-'.Str::uuid();

        $manager->chain('text')
            ->order(1)
            ->cache($baselineKey)
            ->train(['baseline seed']);

        $builder = new PredictiveBuilder($manager, $baselineKey);

        $result = $builder->context('analytics');

        $this->assertSame($builder, $result);
        $this->assertSame($baselineKey, $builder->baseline()->getCacheKey());
    }

    public function test_using_options_merges_and_applies_forecast_configuration(): void
    {
        $manager = app(MarkovableManager::class);
        $baselineKey = 'predictive-options-'.Str::uuid();
        $raw = [
            [
                'customer_id' => 'cust-forecast',
                'metrics' => [
                    'monthly_recurring_revenue' => 5200,
                    'history' => [
                        ['timestamp' => '2024-10-01', 'monthly_recurring_revenue' => 5000],
                        ['timestamp' => '2024-10-02', 'monthly_recurring_revenue' => 5100],
                    ],
                ],
                'timestamp' => '2024-10-03',
            ],
        ];

        $manager->chain('analytics')
            ->order(1)
            ->cache($baselineKey)
            ->train($raw);

        $builder = (new PredictiveBuilder($manager, $baselineKey))
            ->usingOptions([
                'forecast' => [
                    'metric' => 'monthly_recurring_revenue',
                    'horizon' => 2,
                    'components' => ['day_of_week'],
                ],
            ])
            ->usingOptions([
                'next_best_action' => ['exclude' => ['upgrade_plan']],
            ])
            ->dataset(Dataset::normalize($raw));

        $forecast = $builder->seasonalForecast()->get();

        $this->assertCount(2, $forecast['forecast']);
        $this->assertArrayHasKey('seasonal_patterns', $forecast);
    }
}
