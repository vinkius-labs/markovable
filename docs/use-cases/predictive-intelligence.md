# Predictive Intelligence Playbook

When customer signals get messy, Markovable's predictive stack keeps your retention, expansion, and forecasting motions on solid ground. Use these patterns to transform cached behavioural baselines into live guidance for your teams.

## Churn Early-Warning Radar

- **Docs**: [Predictive Builder](../predictive-intelligence.md), [ChurnScorer](../../src/Predictors/ChurnScorer.php)
- **How to use**
  1. Train an `analytics` chain with historical sessions and cache it (e.g., `analytics::predictive-retention`).
  2. Stream the latest customer snapshots into `dataset()`—even if metadata is incomplete, normalization will flatten what matters.
  3. Call `churnScore()->includeRecommendations()` and broadcast high-risk alerts into Slack or your CRM.

```php
$baseline = 'analytics::predictive-retention';

Markovable::chain('analytics')
    ->cache($baseline)
    ->train($historicalSessions);

$churnAlerts = Markovable::predictive($baseline)
    ->dataset($freshSnapshots)
    ->churnScore()
    ->includeRecommendations()
    ->get();

foreach ($churnAlerts as $alert) {
    Notification::route('slack', config('alerts.slack.retention'))
        ->notify(new ChurnRiskPing($alert));
}
```

- **Benefits**: Detects churn risk before revenue leaves the pipeline, attaches actionable guidance to every alert, and guards against missing features by falling back to baseline patterns.

## Revenue Pulse Board (SaaS LTV + Forecast)

- **Docs**: [Predictive Builder](../predictive-intelligence.md), [LtvPredictor](../../src/Predictors/LtvPredictor.php), [SeasonalForecaster](../../src/Predictors/SeasonalForecaster.php)
- **How to use**
  1. Cache a baseline per segment (e.g., `analytics::predictive-enterprise`).
  2. Merge CRM cohorts with product usage before passing them to `dataset()`.
  3. Invoke `ltv()->includeHistoricalComparison()` alongside `seasonalForecast()->horizon()` to populate dashboards.

```php
$builder = Markovable::predictive('analytics::predictive-saas')
    ->dataset($tenantSnapshot)
    ->usingOptions([
        'forecast' => ['metric' => 'monthly_recurring_revenue', 'confidence' => 0.9],
        'ltv' => ['segments' => ['self_serve', 'enterprise'], 'include_historical' => true],
    ]);

$ltvReport = $builder->ltv()->includeHistoricalComparison()->get();
$forecast = $builder->seasonalForecast()->horizon(3)->includeConfidenceIntervals(1.5)->get();

Dashboard::put('ltv_segments', $ltvReport);
Dashboard::put('mrr_forecast', $forecast);
```

- **Benefits**: Keeps finance and success teams aligned with the same subscription cohorts, surfaces expansion-ready tenants, and wraps forecasts with confidence bands so planning can account for volatility.

## Lifecycle Next-Best Actions

- **Docs**: [Predictive Builder](../predictive-intelligence.md), [NextBestActionEngine](../../src/Predictors/NextBestActionEngine.php)
- **How to use**
  1. Train the baseline with annotated journey sequences (e.g., `signup onboarding explore_reports activate_automation`).
  2. Inject real-time events for each account and target the customer with `forCustomer($id)` when context is available.
  3. Filter out actions already attempted using `usingOptions(['next_best_action' => ['exclude' => [...]]])` and feed the rest into your marketing automation platform.

```php
$actions = Markovable::predictive('analytics::predictive-onboarding')
    ->usingOptions([
        'next_best_action' => ['exclude' => ['send_generic_email']],
    ])
    ->dataset($accountEvents)
    ->nextBestAction()
    ->includeContext()
    ->forCustomer($accountId)
    ->topN(2)
    ->get();

if ($actions !== []) {
    CampaignBus::dispatch(new TriggerLifecycleNudge($accountId, $actions));
}
```

- **Benefits**: Suggests targeted nudges, avoids repetitive outreach, and adapts as soon as behaviours drift thanks to cached baselines.

## Chaos-Tolerant Scoring for Operations

- **Docs**: [Predictive Builder](../predictive-intelligence.md), [Dataset Normalization](../../src/Support/Dataset.php)
- **How to use**
  1. Collect operational exports (CS tickets, telemetry snapshots) even if fields are missing or mis-typed.
  2. Feed them directly into `dataset()`—the normalization layer skips unusable records and retains the rest.
  3. Assert on downstream outputs (e.g., empty NBA arrays, hashed churn customer IDs) to ensure fallbacks behave as expected.

```php
$results = Markovable::predictive('analytics::predictive-chaos')
    ->dataset($mixedPayload)
    ->churnScore()
    ->includeRecommendations()
    ->get();

Log::info('Predictive chaos sample', [
    'count' => count($results),
    'first_customer' => $results[0]['customer_id'] ?? null,
]);
```

- **Benefits**: Keeps operations dashboards alive even when upstream data quality dips, supplying defensible scores instead of failing silently.

Pair these blueprints with automated retraining (see [Training Guide](../training-guide.md)) and anomaly detection to keep your predictive surfaces aligned with reality.
