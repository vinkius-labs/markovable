# Predictive Intelligence

Markovable's predictive stack turns chaotic customer telemetry into actionable churn, lifetime value, next-best action, and seasonality insights. This guide breaks down the fluent builder, dataset expectations, available options, emitted events, and resilience strategies you can lean on in production.

> Train once, reuse everywhere: predictive baselines cache rich behavioural fingerprints so you can mix and match scoring engines without reprocessing raw data.

## Builder Overview

`MarkovableManager::predictive()` resolves a `Builders\PredictiveBuilder` instance that orchestrates the four predictive engines:

- **Churn scorer** (`Predictors\ChurnScorer`) – Surfaces risk levels, contributing factors, and optional mitigation actions.
- **Lifetime value predictor** (`Predictors\LtvPredictor`) – Estimates LTV, segments customers, and compares them to historical cohorts.
- **Next best action engine** (`Predictors\NextBestActionEngine`) – Ranks contextual follow-ups grounded in recent behaviour.
- **Seasonal forecaster** (`Predictors\SeasonalForecaster`) – Projects key revenue metrics and exposes confidence bands.

Every builder shares the same cached baseline and accepts ad-hoc datasets so you can score live sessions without retraining.

```php
use VinkiusLabs\Markovable\MarkovableManager;

$manager = app(MarkovableManager::class);
$baselineKey = 'analytics::predictive-onboarding';

$manager->chain('analytics')
    ->cache($baselineKey)
    ->train($historicalJourneyDataset);

$builder = $manager->predictive($baselineKey, [
    'churn' => ['include_recommendations' => true],
    'forecast' => ['metric' => 'monthly_recurring_revenue', 'confidence' => 0.9],
])->dataset($liveCustomers);
```

## Quick Start Flow

1. **Train the baseline** with historical journeys or KPI snapshots.
2. **Cache the baseline** using a deterministic key (`context::segment`).
3. **Resolve the predictive builder** via the manager or the facade (`Markovable::predictive($key)`).
4. **Inject a dataset** – any iterable, collection, or arrayable structure is automatically normalized.
5. **Call the engines you need** (`churnScore()`, `ltv()`, `nextBestAction()`, `seasonalForecast()`).

```php
$churn = $builder->churnScore()->riskThreshold('high', 0.75)->get();
$ltv = $builder->ltv()->includeHistoricalComparison()->get();
$nba = $builder->nextBestAction()->forCustomer('cust-104')->includeContext(true)->get();
$forecast = $builder->seasonalForecast()->horizon(4)->includeConfidenceIntervals(0.9)->get();
```

## LTV Deep Dive for SaaS Teams

Subscription metrics hinge on clear cohort insight. Pair the LTV predictor with historical comparisons to spotlight expansion-ready tenants and churn-prone customers in the same report.

```php
$ltv = Markovable::predictive('analytics::predictive-saas')
    ->dataset($tenantSnapshots)
    ->usingOptions([
        'ltv' => [
            'segments' => ['self_serve', 'enterprise'],
            'first_days' => 14,
            'include_historical' => true,
        ],
    ])
    ->ltv()
    ->includeHistoricalComparison()
    ->get();

foreach ($ltv as $tenant) {
    Metrics::gauge('saas.ltv.score', $tenant['ltv_score'], [
        'segment' => $tenant['ltv_segment'],
        'customer' => $tenant['customer_id'],
    ]);
}
```

- Use `segments` to align with your GTM tiers (self-serve, SMB, enterprise).
- `first_days` limits the onboarding window to evaluate early revenue signals against long-term projections.
- `cohort_comparison` in the response highlights uplift or drag relative to historic tenants—ideal for success reviews and revenue ops dashboards.

## Configuration Envelope

Call `usingOptions()` once to seed options for subsequent engines:

```php
$builder->usingOptions([
    'churn' => [
        'features' => ['support_tickets', 'days_since_last_login'],
        'include_recommendations' => true,
    ],
    'ltv' => [
        'first_days' => 7,
        'segments' => ['north_america', 'emea'],
        'include_historical' => true,
    ],
    'forecast' => [
        'metric' => 'monthly_recurring_revenue',
        'window' => 'weekly',
        'horizon' => 6,
        'components' => ['day_of_week'],
        'confidence' => 0.95,
    ],
    'next_best_action' => [
        'exclude' => ['send_generic_email'],
    ],
]);
```

### Dataset Normalization

`Support\Dataset::normalize()` flattens nested arrays, collections, Eloquent models, and `Arrayable` objects into the key paths that predictors expect. Scalars-only lists are ignored. When `dataset([])` is called, the builder falls back to the cached baseline, ensuring scoring routines always have something to work with.

### Handling Chaotic Inputs

- Partial or malformed records (e.g., timestamps with invalid formats) are ignored gracefully during normalization.
- Churn scorer filters datasets when `features([...])` is set, keeping only the customers that expose the requested attributes.
- If the incoming dataset resolves to an empty set, churn scoring synthesizes fallback customers derived from the baseline's sequence frequencies. These records surface hashed `customer_id` values and still emit actionable contributions.
- Next best action defaults to an empty array rather than throwing when no contextual match exists.

## Events & Telemetry

| Event | Trigger | Payload Highlights |
| ----- | ------- | ------------------ |
| `Events\ChurnRiskIdentified` | Churn score crosses the configured risk threshold. | `customer`, `score`, `riskLevel`, `recommendedActions`. |
| `Events\HighLtvCustomerIdentified` | LTV predictor surfaces a customer in the top tier. | `customer`, `ltv`, `segment`, `cohort_comparison`. |
| `Events\RecommendationGenerated` | Next best action produces contextual guidance. | `customer`, `recommended_action`, `context`. |
| `Events\SeasonalForecastReady` | Seasonal forecast completes with a fresh projection. | `metric`, `forecast`, `confidence_intervals`. |

Use `Event::fake()` in tests to assert the precise mix of events for a scenario, or `Event::assertNotDispatched()` to guarantee noisy inputs stay quiet.

```php
Event::fake([
    ChurnRiskIdentified::class,
    SeasonalForecastReady::class,
]);

$builder->dataset($chaoticPayload);

$forecast = $builder->seasonalForecast()->horizon(2)->get();
$churn = $builder->churnScore()->includeRecommendations()->get();

Event::assertDispatched(SeasonalForecastReady::class);
Event::assertNotDispatched(ChurnRiskIdentified::class);
```

## Testing Strategies

- **Baseline scaffolding:** Train once inside the test and re-use the key for multiple assertions to keep suites fast.
- **Chaotic datasets:** Combine malformed records with a reference customer to validate that predictors return structured results without losing resilience.
- **Fallbacks:** Explicitly reset the dataset to an empty array and confirm churn scoring still produces hashed customers—mirrors production failsafes.
- **Confidence bands:** When asserting seasonal results, check the presence of keys such as `lower_bound_100` instead of hard-coded numbers.

## Operational Tips

- Store predictive cache keys alongside your event stream so retraining jobs can rebuild the same baseline.
- Wrap builder calls in dedicated services (e.g., `PredictiveInsightsService`) to standardize option envelopes across controllers, jobs, and listeners.
- Monitor event volumes; sudden spikes in `ChurnRiskIdentified` often coincide with behaviour drift that may require retraining or detector tuning.
- Pair seasonal forecasts with anomaly detectors to compare projections versus actuals in near real time.

With these building blocks documented, you can scale predictive insights to any customer journey—confidently handling pristine analytics feeds and noisy, real-world payloads alike.
