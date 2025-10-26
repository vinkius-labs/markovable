# Markovable Technical Reference

This reference compiles every core subsystem exposed by Markovable and illustrates how to wire them together in production-grade Laravel projects. It covers chain APIs, analyzers, generators, storage drivers, builders, commands, events, testing hooks, and extension points.

> All namespaces in this document refer to the `VinkiusLabs\Markovable` root unless stated otherwise.

## Core Concepts

### Chain Lifecycle
- `Markovable::chain(string $context = 'text')` instantiates `src/MarkovableChain.php` with the requested context.
- A chain transitions through stages: configure (order, options, storage) → ingest corpus via `train()` / `trainFrom()` → optionally cache → execute `generate()` / `analyze()` / `predict()`.
- Call `incremental()` before `train()` to merge a fresh corpus into the cached model keyed via `cache()`, keeping long-running models updated without rebuilding from scratch.
- Tokenization is handled automatically via `Support/Tokenizer`. Every input is normalized to whitespace-delimited tokens; override by pre-processing data before training.

```php
$chain = Markovable::chain('text')
    ->order(3)
    ->useStorage('database')
    ->cache('docs:v1', ttl: 3600)
    ->trainFrom($documents);

$outline = $chain->generate(120, ['seed' => 'Release highlights']);

$chain = Markovable::chain('text')
    ->order(3)
    ->cache('docs:v1')
    ->incremental()
    ->trainFrom($newDocuments);
```

### Contexts and Builders
- The manager resolves context-specific builders (see `MarkovableManager::defaultBuilders()`):
  - `text` → `Builders/TextBuilder`.
  - `navigation` and `analytics` → `Builders/AnalyticsBuilder`.
- Custom builders can be registered with `Markovable::extendBuilder('context', Resolver::class)` to map new contexts.

### Order and State
- `order(int $order)` defines the Markov window size. Values below 1 raise a `RuntimeException`.
- Initial states are derived from the corpus; inspect with `toProbabilities()` for debugging.
- `withProbabilities()` toggles analyzer output from just sequences to full payload maps.

### Options Envelope
- `option($key, $value)` or `options(array $options)` (via macro) store arbitrary config forwarded to generators/analyzers.
- `when()` and `unless()` helpers allow conditional configuration without breaking the fluent chain.

## Tokenization and Corpus Handling
- `Tokenizer::corpus($input)` accepts strings, arrays, iterables, Eloquent models (reading `$markovableColumns`), `Arrayable`, and `Collection` values.
- `Tokenizer::tokenize(string $value)` returns normalized whitespace tokens suitable for seeding analyzers.
- Provide domain-specific preprocessing (lemmas, translation) before invoking `trainFrom()` when required.

## Generators

### TextGenerator (`src/Generators/TextGenerator.php`)
- Default generator for `context=text`.
- Options:
  - `seed`: string of tokens to bootstrap the first prefix.
  - `temperature`: float (0-1) weighting randomness (default 1.0).
  - `fallback`: fallback seed when the provided one does not exist in the model.
  - `initial_states`: auto-populated; override to constrain starting points.
  - `order`: forwarded to ensure generator aligns with the trained order.
- Usage:

```php
Markovable::generator('text')->generate($model, 80, [
    'seed' => 'Campaign announcement',
    'temperature' => 0.6,
]);
```

### SequenceGenerator (`src/Generators/SequenceGenerator.php`)
- Emits structured sequences (arrays) rather than free-form text.
- Ideal for support macros, navigation steps, or category predictions.

```php
$sequence = Markovable::generator('sequence')->generate($model, 5, [
    'seed' => 'integration-setup',
    'return_sequence' => true,
]);
```

## Analyzers

### TextAnalyzer (`Analyzers/TextAnalyzer.php`)
- Produces ranked predictions with `{ seed, prefix, predictions[] }` payload.
- Predictions include `sequence` and `probability` keys.

### NavigationAnalyzer (`Analyzers/NavigationAnalyzer.php`)
- Reports `path` tokens with `probability` and `confidence` (percentage).
- Accepts filter metadata (`from`, `to`, `label`) which is preserved in response for auditing.

### AnomalyDetector (`Analyzers/AnomalyDetector.php`)
- Wraps a collection of `Contracts\Detector` implementations to contrast live data with cached baselines.
- Fluent helpers: `unseenSequences()`, `emergingPatterns()`, `detectSeasonality()`, `drift()`, `threshold()`, `minimumFrequency()`, `seasonalityThreshold()`, `orderBy()`, `metrics()`, `seasonalityData()`, `withoutPersistence()`, `withoutEvents()`.
- Persists anomalies to `Models\AnomalyRecord` and dispatches `Events\AnomalyDetected` / `Events\PatternEmerged` when configured.
- Inject your own detectors by extending the class and registering a macro on `Markovable::detect()` or via service container binding.

### PageRankAnalyzer (`Analyzers/PageRankAnalyzer.php`)
- Calculates PageRank scores from navigation graphs, relationship matrices, or custom graph builders.
- Options include `damping`, `threshold`, `iterations`, `top`, `group_by`, and `include_metadata`.
- Returns a `PageRankResult` wrapping `PageRankNode` instances (raw score, normalized percentage, percentile) plus optional grouping metadata.
- Accepts inline graphs via the `graph` option or resolves `Contracts\PageRankGraphBuilder` instances passed through `graph_builder`.
- Paired with `PageRankCalculator` for optimized power-iteration, automatically handling dangling nodes and normalization.

### Custom Analyzer Registration

```php
Markovable::extendAnalyzer('emoji-text', function ($app, $manager) {
    return new EmojiAwareAnalyzer();
});

$chain = Markovable::analyze('emoji-text')->trainFrom($payload);
```

## Detectors & Monitoring

### Detector Interface (`Contracts/Detector.php`)
- Method signature: `detect(DetectionContext $context, array $config = []): array`.
- Return payloads should include a `type` key plus detector-specific metadata.
- Bundle reusable detectors in your own namespace and inject them via macros or subclasses of `Analyzers\AnomalyDetector`.

### DetectionContext (`Support/DetectionContext.php`)
- Provides access to the trained `MarkovableChain`, baseline probabilities, historical metadata, and current sequence frequencies.
- Utility helpers: `probabilityOf()`, `probabilityFromTokens()`, `baselineFrequency()`, `countOccurrences()`, `totalCurrentSequences()`, `patternHistory()`, `seasonalityProfile()`.

### Built-in Detectors
- **UnseenSequenceDetector** – Flags sequences whose baseline probability falls beneath a configurable threshold.
- **EmergingPatternDetector** – Highlights patterns whose frequency growth exceeds expectations compared to the baseline, enriched with trend analysis and confidence scoring.
- **SeasonalAnalyzer** – Computes KL divergence across configurable temporal metrics to detect seasonality shifts.
- **DriftDetector** – Monitors average sequence length changes to surface behaviour drift.

### MonitorPipeline (`Support/MonitorPipeline.php`)
- Fluent orchestrator that configures detectors, alert channels, and cadence: `detectAnomalies()`, `alerts()`, `checkInterval()`, `start()`.
- Returns a structured summary with anomalies, resolved alert channels, and timestamps—ideal for cron jobs or scheduled commands.

### ClusterAnalyzer (`Detectors/ClusterAnalyzer.php`)
- Segments navigation sequences into behavioural clusters (`kmeans`, `dbscan`, or round-robin fallback).
- Emits summary profiles (`cluster_id`, percentage, heuristically named segment, computed characteristics).
- Dispatches `Events\ClusterShifted` when baseline and current profiles diverge.

## Builders

### TextBuilder (`Builders/TextBuilder.php`)
- Fluent helper to hydrate and cache text-focused chains.
- Supports `fromModel()`, `fromFile()`, `cache()`, `generate()`, `export()` patterns.

### AnalyticsBuilder (`Builders/AnalyticsBuilder.php`)
- Aggregates transitions and emits analytics matrices for navigation or probability reporting.
- Provides methods like `probabilitiesFor($seed)` and `topTransitions($limit)`.

Example pipeline:

```php
$report = Markovable::builder('analytics')
    ->source(NavigationEvent::class)
    ->filters(['from' => now()->subWeek()])
    ->train('navigation')
    ->fromCache('journey:checkout')
    ->probabilitiesFor('billing_information');
```

### PredictiveBuilder (`Builders/PredictiveBuilder.php`)
- Coordinates churn scoring, LTV forecasting, seasonal projections, and next-best action recommendations.
- Inherits cached datasets from `MarkovableManager::predictive($baselineKey)` and accepts ad-hoc overrides via `dataset()`.
- Use `usingOptions()` to configure features, horizons, exclusion lists, and confidence bands before invoking each predictor.
- Reference: [Predictive Intelligence Guide](./predictive-intelligence.md) and [Predictive Use Cases](./use-cases/predictive-intelligence.md).

```php
$builder = Markovable::predictive('analytics::predictive-retention')
    ->dataset($liveSnapshots)
    ->usingOptions([
        'churn' => ['include_recommendations' => true],
        'forecast' => ['metric' => 'monthly_recurring_revenue', 'confidence' => 0.9],
    ]);

$churn = $builder->churnScore()->get();
$ltv = $builder->ltv()->includeHistoricalComparison()->get();
```

### PageRankBuilder (`Builders/PageRankBuilder.php`)
- Fluent facade entry point for PageRank calculations via `Markovable::pageRank()`.
- Supports `withGraph()`, `useGraphBuilder()`, `dampingFactor()`, `convergenceThreshold()`, `maxIterations()`, `topNodes()`, `groupBy()`, and `includeMetadata()`.
- Caches the last result internally and returns either the analyzer payload (`calculate()`) or the full `PageRankResult` object (`result()`).

```php
$result = Markovable::pageRank()
    ->useGraphBuilder(new App\Markovable\SaaSAuthorityGraph())
    ->dampingFactor(0.9)
    ->groupBy('prefix')
    ->includeMetadata()
    ->result();

PageRankSnapshot::capture('saas-authority:q2', $result);
```

## Storage Drivers

| Driver | Class | Persistence | Notes |
| ------ | ----- | ----------- | ----- |
| `cache` | `Storage/CacheStorage` | Configured cache store | Honors configured TTL; suitable for short-lived experiments. |
| `database` | `Storage/DatabaseStorage` | `markovable_models` table | Stores JSON payload plus `expires_at`; auto-garbage-collects expired rows. |
| `file` | `Storage/FileStorage` | Filesystem path | Writes JSON to disk; ensure directories exist and permissions match PHP user. |

Configure in `config/markovable.php` (see [Configuration Guide](./configuration.md) for full context):

```php
return [
    'storage' => 'cache',
    'storages' => [
        'cache' => CacheStorage::class,
        'database' => DatabaseStorage::class,
        'file' => FileStorage::class,
    ],
];
```

Override per chain via `useStorage('database')` or per cache call `cache($key, ttl: 600, storage: 'file')`.

## Caching and Persistence
- `cache(string $key, ?int $ttl = null, ?string $storage = null)` persists the trained model. When invoked pre-training, persistence is deferred until `train()` completes.
- `toProbabilities()` returns the in-memory matrix for inspection; use `export($path)` to write to disk.
- Storage payload schema contains `model`, `order`, `initial_states`, `context`, and optional `meta` fields.
- When anomaly migrations are published, detections land in `markovable_anomalies`, pattern alerts in `markovable_pattern_alerts`, and cluster summaries in `markovable_cluster_profiles`.

## Snapshots and Versioning
- Persist long-term model checkpoints with `markovable:snapshot`, supporting database rows or filesystem artifacts (including arbitrary Laravel disks via `--storage=disk:name`).
- Compression (`--compress`) applies `gzencode` before persistence and can be layered with Laravel encryption (`--encrypt`) for sensitive models.
- Snapshot metadata records metric summaries, storage sizes, and context inside `markovable_model_snapshots`.
- Provide `--from-storage` to snapshot models stored away from the default driver.

## Scheduling Automation
- Manage recurring jobs through `markovable:schedule`. Supported actions include `train`, `detect`, `report`, and `snapshot`.
- Frequencies accept `daily`, `hourly`, `weekly`, `monthly`, or a custom cron expression (`--frequency=cron --time="*/15 * * * *"`).
- Schedules are persisted in `markovable_schedules` with UUID identifiers and track next-run timestamps for dashboarding.
- Attach callbacks via `--callback=` to trigger follow-up Artisan commands or webhooks after a schedule completes and toggle availability with `--enable` / `--disable`.

## Reporting
- `markovable:report` assembles summaries from cached models and the anomaly log, rendering via DOMPDF for PDF output alongside HTML, Markdown, JSON, or CSV payloads.
- Filter sections with `--sections=summary,predictions` or narrow the observation window using human-friendly periods (`--period=24h`, `--period=4w`).
- Deliver reports automatically through `--email` (PDFs are attached) or `--webhook` (PDFs ship as base64 payloads) and persist exports with `--save=storage/path/report.pdf|.json|...`.
- Tailor presentation with `--template`: `default` mirrors raw section data, whereas `summary` produces executive-friendly highlights and recommendations.
- Report construction reuses `ModelMetrics` statistics and `MarkovableChain::getSequenceFrequencies()` to surface top transitions.

## Queue Integration
- `queue()` returns `Jobs/TrainMarkovableJob` pre-populated with chain state.
- `generateAsync()` returns `Jobs/GenerateContentJob` ready for dispatch.
- `analyzeAsync()` returns `Jobs/AnalyzePatternsJob` (propagates analyzer name and options).
- `TrainMarkovableJob` honours the `incremental` flag and model metadata, enabling queued pipelines to append fresh data without manual cache hydration.
- When `config('markovable.queue.enabled')` is true, you can centralize queue connection and queue name.

```php
Bus::dispatch(
    Markovable::chain('text')
        ->order(3)
        ->trainFrom($payload)
        ->cache('emails:v2')
        ->generateAsync(120, ['seed' => 'Q4 campaign'])
);
```

## Events
- `Events/ModelTrained` fires after successful `train()`.
- `Events/ContentGenerated` fires with generated string payload.
- `Events/PredictionMade` dispatches when `predict()` is called on a broadcasting chain (`broadcast($channel)`).

Subscribe via Laravel event listeners to record analytics or trigger workflows.

```php
Event::listen(PredictionMade::class, function (PredictionMade $event) {
    Metrics::counter('markovable.prediction')->increment([
        'context' => $event->chain->getContext(),
        'cache' => $event->cache,
    ]);
});
```

## Commands

- CLI usage details live in `docs/command-reference.md`; below are high-level summaries.

### `markovable:train`
- Ingest datasets from `--source=eloquent|csv|json|api|database` using `--data=` to locate the payload.
- Persist the chain with `--cache`, choose storage via `--storage=`, and version releases through `--tag`.
- `--incremental` merges the incoming corpus into the cached model referenced by the cache key or tag.
- Queue workloads with `--async` and surface progress through `--notify=log|email:addr|webhook:url`.
- Attach contextual `--meta key=value` pairs that flow into cache metadata and downstream reports.

### `markovable:generate`
- Train ad-hoc from `--model` / `--field` or text files via `--file` before generating.
- Reuse cached models with `--cache-key` and adjust `--order` to match the stored chain.
- Control output using `--start` seeds and `--words` length, persisting responses with `--output`.
- Dispatch generation asynchronously through `--queue`, returning a `GenerateContentJob` instance.

### `markovable:analyze`
- Provide the analyzer profile argument (e.g., `markovable:analyze churn`) to select the pipeline.
- Load corpora from models, fields, or files using the same options as `markovable:generate`.
- Use `--predict`, `--seed`, and `--probabilities` to tailor analyzer outputs to your workflow.
- Export findings with `--export=path.csv` while `--from` and `--to` bound the evaluation window.
- Append `--queue` when delegating analysis to the asynchronous job runner.

### `markovable:snapshot`
- Capture cached models to the database or filesystem with `--storage=database|file|disk:name`.
- Layer `--compress`, `--encrypt`, `--tag`, and `--description` to control payload shape and metadata.
- Read from alternate cache backends via `--from-storage` and customise disk locations with `--output-path`.
- Snapshot entries, including metrics, live in `markovable_model_snapshots` for future restores or audits.

### `markovable:schedule`
- List existing definitions with `--list` or create/update by providing the action (`train|detect|report|snapshot`).
- Configure cadence through `--frequency` and `--time`, supporting cron expressions when `--frequency=cron`.
- Associate scopes via `--model` and downstream hooks via `--callback=artisan-command|https://webhook`.
- Toggle availability with `--enable` / `--disable`; schedules persist in `markovable_schedules`.

### `markovable:report`
- Build analytics summaries from cached models and anomaly records across the requested period.
- Select delivery format using `--format=pdf|html|json|csv|markdown` and restrict content with `--sections=`.
- Adjust observation windows via human-readable `--period` values (`24h`, `7d`, `4w`, ...).
- Deliver or store reports using `--email`, `--webhook`, and `--save`, with `--from-storage` targeting alternate caches.

### `markovable:pagerank`
- Execute PageRank calculations from the CLI using cached baselines, JSON adjacency files, or registered graph builders.
- Options mirror the builder: `--graph`, `--graph-builder`, `--damping`, `--threshold`, `--iterations`, `--top`, `--group-by`, `--include-metadata`.
- `--store` persists snapshots via `PageRankSnapshot::capture()`; `--export` writes payloads to disk for BI tooling.
- Recommended when automating nightly ranking jobs or exporting scores to downstream systems without writing PHP glue.

## Facade Shortcuts and Macros
- `Facades/Markovable` proxies to `MarkovableManager` and supports macro registration for custom conveniences.
- Example macro:

```php
Markovable::macro('cacheAndGenerate', function ($dataset, $key, $length = 50) {
    return $this->chain('text')
        ->trainFrom($dataset)
        ->cache($key)
        ->generate($length);
});
```

## Traits and Observers
- `Traits/TrainsMarkovable` encapsulates automatic training hooks for Eloquent models.
- `Observers/AutoTrainObserver` listens to model events and dispatches jobs based on config (`config('markovable.auto_train')`).

## Storage Meta Tagging
- When persisting caches, include metadata by setting `option('meta', [...])` before `cache()`; storage drivers propagate it.
- `DatabaseStorage` expects `markovable_models` schema with columns: `name`, `context`, `markovable_type`, `markovable_id`, `payload`, `ttl`, `expires_at`, timestamps.

## Support Utilities

### Tokenizer
- Extend by wrapping `Tokenizer::tokenize()` with custom segmentation (ngrams, sentence-level) before training.

### WeightedRandom (`Support/WeightedRandom.php`)
- Accepts an associative array of token probabilities and yields randomized picks respecting the distribution.
- Generators rely on this helper; reuse for auxiliary sampling needs.

### ModelMetrics (`Support/ModelMetrics.php`)
- Snapshot chain health after training with `ModelMetrics::fromChain($chain)`.
- Exposes counts for states, transitions, unique sequences, and probabilities plus an overall confidence score.
- Commands leverage these metrics for reporting; reuse them when instrumenting dashboards or notifications.

## Testing
- Import `Testing/MarkovableAssertions` trait inside PHPUnit test cases.
- Assertions:
  - `assertMarkovableGenerated($text, $minWords)` ensures outputs have enough tokens.
  - `assertMarkovableTrained($chain)` checks the probability matrix is populated.
  - `markovableChain($data, $order)` quickly bootstraps a trained chain for assertions.

Example:

```php
class SequenceGeneratorTest extends TestCase
{
    use MarkovableAssertions;

    public function testSuggestedSequencesAreReasonable(): void
    {
        $chain = $this->markovableChain([
            'alpha beta gamma',
            'alpha beta delta',
        ], order: 2);

        $result = $chain->withProbabilities()->predict('alpha beta', 2);

        $this->assertMarkovableTrained($chain);
        $this->assertNotEmpty($result['predictions']);
    }
}
```

## Extending Markovable
- Register new analyzers, generators, storages, and builders via `Markovable::extend*` methods.
- Implement `Contracts\Analyzer|Generator|Storage` interfaces to guarantee compatibility.
- Leverage `Macroable` trait on both `MarkovableChain` and `MarkovableManager` for DSL-style helpers.

## Deployment Considerations
- Ensure the configured cache or queue backends are available (`redis` default).
- For file storage, set `config('markovable.storages.file.path')` if customizing the default location.
- Monitor TTL expirations; database driver purges expired rows on access but consider scheduled cleanup.
- Avoid blocking queues by chunking large corpora before calling `trainFrom()`; iterative training keeps memory bounded.

## Integration Patterns
- **HTTP Controllers**: Inject predictions into API responses or Blade views using cached chains.
- **Jobs**: Use async job classes for scheduled re-training (`TrainMarkovableJob`) or background generation tasks.
- **Events**: Fan out predictions to telemetry systems or websockets via `broadcast()`.
- **CLI Tooling**: Combine Artisan commands with cron or Git hooks to re-train models from fresh datasets.

## Reference Paths
- Configuration: [`config/markovable.php`](../config/markovable.php) — explained in the [Configuration Guide](./configuration.md)
- Service Provider: `src/ServiceProvider.php` (registers bindings, publishes config, commands, migrations).
- Migration stubs: `database/migrations/2024_01_01_000000_create_markovable_models_table.php` (cached models), `database/migrations/2024_01_01_020000_create_markovable_snapshot_and_schedule_tables.php` (snapshots and schedules).
- Doctrine-style contracts: `src/Contracts/Analyzer.php`, `src/Contracts/Generator.php`, `src/Contracts/Storage.php`.

Use this document alongside the scenario-specific playbooks in `docs/use-cases/` to pinpoint implementation details tailored to your product domain.
