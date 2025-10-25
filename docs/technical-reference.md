# Markovable Technical Reference

This reference compiles every core subsystem exposed by Markovable and illustrates how to wire them together in production-grade Laravel projects. It covers chain APIs, analyzers, generators, storage drivers, builders, commands, events, testing hooks, and extension points.

> All namespaces in this document refer to the `VinkiusLabs\Markovable` root unless stated otherwise.

## Core Concepts

### Chain Lifecycle
- `Markovable::chain(string $context = 'text')` instantiates `src/MarkovableChain.php` with the requested context.
- A chain transitions through stages: configure (order, options, storage) → ingest corpus via `train()` / `trainFrom()` → optionally cache → execute `generate()` / `analyze()` / `predict()`.
- Tokenization is handled automatically via `Support/Tokenizer`. Every input is normalized to whitespace-delimited tokens; override by pre-processing data before training.

```php
$chain = Markovable::chain('text')
    ->order(3)
    ->useStorage('database')
    ->cache('docs:v1', ttl: 3600)
    ->trainFrom($documents);

$outline = $chain->generate(120, ['seed' => 'Release highlights']);
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

### Custom Analyzer Registration

```php
Markovable::extendAnalyzer('emoji-text', function ($app, $manager) {
    return new EmojiAwareAnalyzer();
});

$chain = Markovable::analyze('emoji-text')->trainFrom($payload);
```

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

## Storage Drivers

| Driver | Class | Persistence | Notes |
| ------ | ----- | ----------- | ----- |
| `cache` | `Storage/CacheStorage` | Configured cache store | Honors configured TTL; suitable for short-lived experiments. |
| `database` | `Storage/DatabaseStorage` | `markovable_models` table | Stores JSON payload plus `expires_at`; auto-garbage-collects expired rows. |
| `file` | `Storage/FileStorage` | Filesystem path | Writes JSON to disk; ensure directories exist and permissions match PHP user. |

Configure in `config/markovable.php`:

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

## Queue Integration
- `queue()` returns `Jobs/TrainMarkovableJob` pre-populated with chain state.
- `generateAsync()` returns `Jobs/GenerateContentJob` ready for dispatch.
- `analyzeAsync()` returns `Jobs/AnalyzePatternsJob` (propagates analyzer name and options).
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

### `markovable:train`
- Options: `--model`, `--field`, `--file`, `--order`, `--cache-key`, `--storage`, `--queue`.
- Queued mode persists using a random cache key when not provided.

### `markovable:generate`
- Options: `--model`, `--field`, `--file`, `--words`, `--start`, `--cache-key`, `--order`, `--output`, `--queue`.
- Accepts `start` seed to control the first prefix.

### `markovable:analyze`
- Arguments: `profile` (analyzer name).
- Options: `--model`, `--field`, `--file`, `--order`, `--predict`, `--seed`, `--cache-key`, `--from`, `--to`, `--export`, `--probabilities`, `--queue`.
- Exports CSV rows using `sequence;probability` formatting.

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
- Configuration: `config/markovable.php`
- Service Provider: `src/ServiceProvider.php` (registers bindings, publishes config, commands, migrations).
- Migration stub: `database/migrations/2024_01_01_000000_create_markovable_models_table.php` (database storage schema).
- Doctrine-style contracts: `src/Contracts/Analyzer.php`, `src/Contracts/Generator.php`, `src/Contracts/Storage.php`.

Use this document alongside the scenario-specific playbooks in `docs/use-cases/` to pinpoint implementation details tailored to your product domain.
