# Usage Recipes

These recipes showcase how Markovable flows through everyday developer tasks. Copy, tailor, and extend them to fuel your own automations. For graph authority scenarios, cross-reference the [PageRank Analyzer Guide](./pagerank.md) and blueprint use cases for end-to-end playbooks.

## 1. Train Models From Rich Datasets

```php
use VinkiusLabs\Markovable\Facades\Markovable;

$chain = Markovable::chain('text')
    ->order(3)
    ->trainFrom([
        'Blueprints become launchpads.',
        'Launchpads become products when teams stay curious.',
        'Curious teams measure, learn, and iterate.',
    ])
    ->cache('team-mantras', ttl: 3600);
```

## 2. Generate Content With Deterministic Seeds

```php
$output = Markovable::cache('team-mantras')
    ->generate(15, [
        'seed' => 'Curious teams',
    ]);
```

## 3. Predict Next Navigation Steps

```php
$prediction = Markovable::analyze('navigation')
    ->cache('team-mantras')
    ->predict('/dashboard', 5, [
        'from' => now()->subDay()->toIso8601String(),
        'to' => now()->toIso8601String(),
    ]);
```

## 4. Queue Intensive Workloads

```php
use Illuminate\Support\Facades\Bus;

Bus::dispatch(
    Markovable::train($yourDataset)
        ->cache('heavy-lift')
        ->queue()
);
```

## 5. Export Models for Offline Analysis

```php
Markovable::cache('team-mantras')
    ->export(storage_path('app/markovable/team-mantras.json'));
```

## 6. Plug Into Eloquent Models

Make any model self-training by adding the trait:

```php
use Illuminate\Database\Eloquent\Model;
use VinkiusLabs\Markovable\Traits\TrainsMarkovable;

class Article extends Model
{
    use TrainsMarkovable;

    protected $markovableColumns = ['title', 'summary'];
}
```

Whenever you save the model, Markovable learns from it automatically.

## 7. Broadcast Predictions

```php
use VinkiusLabs\Markovable\Events\PredictionMade;
use Illuminate\Support\Facades\Event;

Event::listen(PredictionMade::class, function ($event) {
    // Stream predictions to dashboards or websocket clients.
});

Markovable::train($dataset)
    ->broadcast('markovable.predictions')
    ->predict('growth marketing', 3);
```

Bring these fragments into your stack, mix with your own flair, and Markovable will keep pace with your imagination.

## 8. Detect Anomalies From Cached Baselines

```php
$anomalies = Markovable::train($latestTraffic)
    ->detect('traffic:baseline')
    ->unseenSequences()
    ->emergingPatterns()
    ->detectSeasonality()
    ->drift()
    ->threshold(0.08)
    ->minimumFrequency(12)
    ->orderBy('severity')
    ->get();

foreach ($anomalies as $alert) {
    Notification::send($team, new UnexpectedFlowAlert($alert));
}
```

## 9. Schedule Always-On Monitoring

```php
$summary = Markovable::train($slidingWindow)
    ->monitor('traffic:baseline')
    ->detectAnomalies([
        'unseenSequences' => ['threshold' => 0.06, 'minLength' => 3],
        'emergingPatterns' => ['minFrequency' => 18, 'growth' => 0.45],
        'seasonality' => ['metrics' => ['weekday', 'hour']],
        'drift' => ['drift_threshold' => 0.2],
    ])
    ->alerts([
        'critical' => ['pagerduty' => 'markovable-critical'],
        'high' => ['slack' => '#growth-alerts'],
    ])
    ->checkInterval('15 minutes')
    ->start();

cache(['markovable:latest-monitoring' => $summary], now()->addMinutes(15));
```

## 10. Cluster Navigation Patterns

```php
$profiles = Markovable::train($sessionSnippets)
    ->cluster('traffic:baseline')
    ->algorithm('kmeans')
    ->numberOfClusters(4)
    ->features(['frequency', 'length'])
    ->analyze();

foreach ($profiles as $cluster) {
    Segment::upsert($cluster['profile'], $cluster['characteristics']);
}
```

## 11. Run Anomaly Audits From The CLI

```bash
php artisan markovable:detect-anomalies \
  --model=traffic:baseline \
  --input=storage/app/navigation.ndjson \
  --threshold=0.07 \
  --min-frequency=20
```

Pipe the table output to `--format=json` (via `jq`) or redirect to logs for downstream automation.

## 12. Map Authority With PageRank

```php
use VinkiusLabs\Markovable\Facades\Markovable;
use App\Markovable\NavigationGraphBuilder;

$ranks = Markovable::pageRank()
    ->useGraphBuilder(app(NavigationGraphBuilder::class))
    ->dampingFactor(0.9)
    ->groupBy('domain')
    ->includeMetadata()
    ->calculate();

foreach ($ranks['groups'] as $domain => $details) {
    reportAuthority($domain, $details['normalized_score']);
}
```

Schedule deep-dive snapshots with `php artisan markovable:pagerank` and review the SaaS and community playbooks in [Markovable Use Cases](./use-cases.md#pagerank-saas-authority-mapping) for roll-out patterns.
