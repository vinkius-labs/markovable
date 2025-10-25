# Feature Adoption Campaigns Blueprint

Identify friction points across product journeys and launch targeted interventions powered by probabilistic insights.

## Why Use This Pattern
- You track event data for product activation milestones but struggle to translate it into campaigns.
- Product marketing needs a feedback loop when onboarding experiments succeed or stall.
- You want to automate nudges that adapt as cohorts behave differently over time.

## Data Architecture
- Persist activation events (e.g., `import_completed`, `integration_connected`) with user cohorts and timestamps.
- Create a dedicated Eloquent model (`FeatureJourney`) to encapsulate `user_id`, `journey`, `step`, `completed_at`, and `metadata`.

```php
class FeatureJourney extends Model
{
    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function scopeForJourney($query, string $journey): Builder
    {
        return $query->where('journey', $journey);
    }
}
```

## Building the Probability Graph
- Aggregate sequences per journey and cohort.

```php
$sequences = FeatureJourney::forJourney('checkout-flow')
    ->orderBy('completed_at')
    ->get()
    ->groupBy('user_id')
    ->map(fn ($steps) => $steps->pluck('step')->all());

Markovable::analyze('navigation')
    ->train($sequences)
    ->cache('journey:checkout-flow:all');
```

- Use `AnalyticsBuilder` to compute drop-off probabilities per step.

```php
$analytics = Markovable::builder('analytics')
    ->fromCache('journey:checkout-flow:all')
    ->probabilitiesFor('billing_information');

$highestDrop = $analytics->sortByDesc('probability')->first();
```

## Campaign Orchestration
- Trigger lifecycle campaigns when probability thresholds cross notable levels.

```php
if ($highestDrop->probability > 0.35) {
    Campaign::nudge(
        audience: Audience::forStep('billing_information'),
        template: 'nudges.checkout.fill-payment',
        context: ['next_best_action' => $highestDrop->token]
    );
}
```

- Sync with marketing automation (Customer.io, Braze) by propagating `next_best_action` as a user attribute.

## Experiment Analytics
- Fire `PredictionMade` and `ContentGenerated` events into your telemetry pipeline to monitor campaign lift.
- Persist Markovable outputs for BI tools by exporting to S3 using `FileStorage` driver.

```php
Markovable::storage('file')
    ->cache('journey:checkout-flow:all')
    ->exportTo(storage_path('app/exports/checkout-flow.json'));
```

## Automation Strategy
- Schedule `TrainMarkovableJob` nightly per cohort (e.g., plan, region) to avoid stale recommendations.
- Subdivide caches when different success definitions exist (`journey:checkout-flow:enterprise`).

## Guardrails and Testing
- Assert that critical steps remain present in predictions to avoid campaign drift.

```php
$this->markovableCache('journey:checkout-flow:all')
    ->assertPredicts('billing_information', fn ($predictions) =>
        $predictions->contains('verification_email_sent')
    );
```

- Monitor `PredictionMade` counts per cohort to guarantee coverage before launching wide-reaching nudges.

## Inspiration Backlog
- Build an in-app dashboard for customer success managers showing real-time journey health.
- Power adaptive checklists that reprioritize items based on predicted completion order.
- Feed product analytics tools (Amplitude, Mixpanel) with probability scores to fine-tune segmentation.
- Inform roadmap prioritization by inspecting states with consistently low transition probabilities.
