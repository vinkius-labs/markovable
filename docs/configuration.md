# Markovable Configuration Guide

This guide describes every option exposed in `config/markovable.php`, shows how to bind them to environment variables, and gives practical configuration examples. Publish the file with:

```bash
php artisan vendor:publish --provider="VinkiusLabs\\Markovable\\ServiceProvider" --tag=markovable-config
```

Once published, you can safely edit `config/markovable.php` in your application. Refer back to this guide whenever you need to tune storage backends, queue usage, or analyzer registration.

## Using Environment Variables

Laravel configuration files can call `env()` so you can override settings without committing changes. For example:

```php
'cache' => [
    'enabled' => env('MARKOVABLE_CACHE_ENABLED', true),
    'ttl' => env('MARKOVABLE_CACHE_TTL', 3600),
    'driver' => env('MARKOVABLE_CACHE_DRIVER', 'redis'),
],
```

Populate the matching keys in `.env`:

```
MARKOVABLE_CACHE_ENABLED=true
MARKOVABLE_CACHE_TTL=900
MARKOVABLE_CACHE_DRIVER=redis
```

> Tip: keep environment keys namespaced with `MARKOVABLE_` to avoid collisions with the rest of your Laravel configuration.

## Option Reference

### `default_order`
Controls the Markov order used when no explicit `order()` call is made on a chain. Higher values consider more tokens of history at the cost of memory and runtime.

### `generate_default_words`
Sets the default token count when calling `generate()` without an explicit length. Adjust when your content snippets are typically longer or shorter than 100 tokens.

### `cache`
Configuration for caching trained models:

- `enabled`: When `false`, cache lookups are skipped and models live only in memory.
- `ttl`: Time-to-live (seconds) for cache entries when you use drivers that support expiry.
- `driver`: Cache store name as registered in `config/cache.php` (e.g. `redis`, `memcached`, `file`).

Disabling cache is useful in stateless testing environments; lowering the TTL fits rapidly changing datasets.

### `storage`
Default storage backend used when persisting models. Allowed values map to the keys defined in `storages`. The shipped drivers are `cache`, `database`, and `file`.

### `storages`
Maps storage names to concrete classes implementing `Contracts\MarkovableStorage`. Extend this array when you build a custom driver (for example, S3 or an external API). Keep the default keys unless you are replacing the core implementations.

### `queue`
Controls how long-running work defers to Laravel queues:

- `enabled`: When `true`, the package prefers queued jobs for heavy tasks (e.g. `TrainMarkovableJob`).
- `connection`: Queue connection name from `config/queue.php`.
- `queue`: Specific queue to push jobs onto, allowing you to isolate workloads.

Enable this when training datasets are large or when scheduling analytics from CLI.

### `auto_train`
Registers observers that automatically retrain models when specific Eloquent models are saved:

- `enabled`: Toggles the observer registration.
- `models`: Array of model class names to observe.
- `field`: Optional attribute name to stream into the training corpus.

Leave this disabled until you are ready for automatic retraining—once on, any save event on the listed models triggers training.

### `analyzers`
Associates analyzer slugs with their concrete classes. You can register custom analyzers by adding entries, or swap built-ins by pointing the slug to a different class that implements the required interface.

### `anomaly`
Tunes anomaly detection defaults:

- `persist`: When `true`, anomaly detections are saved via Eloquent models (requires migrations).
- `dispatch_events`: Emits Laravel events whenever anomalies are detected.
- `default_threshold`: Baseline sensitivity used by detectors when you do not specify overrides.
- `seasonality.threshold`: Sensitivity for the seasonal detector.

Adjust these values to control alert volume and persistence behavior.

## Practical Configuration Examples

### Redis Cache with Queue Offloading (Default Baseline)

```php
return [
    'storage' => 'cache',
    'cache' => [
        'enabled' => true,
        'ttl' => 1800,
        'driver' => 'redis',
    ],
    'queue' => [
        'enabled' => true,
        'connection' => 'redis',
        'queue' => 'markovable',
    ],
];
```

Pair this with `.env` settings such as `QUEUE_CONNECTION=redis` to ensure workload offloading for large training jobs.

### Database Storage for Audited Environments

```php
return [
    'storage' => 'database',
    'cache' => [
        'enabled' => false,
    ],
    'queue' => [
        'enabled' => false,
    ],
];
```

Disable cache when every trained model must be persisted in the database for auditing or for multi-instance consistency.

### Multi-Tenant File Snapshots with Auto-Training

```php
return [
    'storage' => 'file',
    'storages' => [
        'file' => App\\Markovable\\Storage\\TenantFileStorage::class,
    ],
    'auto_train' => [
        'enabled' => true,
        'models' => [App\\Models\\Article::class, App\\Models\\Guide::class],
        'field' => 'body',
    ],
    'anomaly' => [
        'persist' => true,
        'dispatch_events' => true,
        'default_threshold' => 0.08,
    ],
];
```

In this scenario each tenant writes models to a tenant-aware file path while specific content models retrain automatically.

### Registering a Custom Analyzer

```php
return [
    'analyzers' => [
        'text' => VinkiusLabs\\Markovable\\Analyzers\\TextAnalyzer::class,
        'navigation' => VinkiusLabs\\Markovable\\Analyzers\\NavigationAnalyzer::class,
        'pagerank' => VinkiusLabs\\Markovable\\Analyzers\\PageRankAnalyzer::class,
        'churn' => App\\Markovable\\Analyzers\\ChurnAnalyzer::class,
    ],
];
```

Ensure the custom analyzer implements the required contract so `Markovable::analyze('churn')` resolves successfully.

---

With these reference points you can tune Markovable for local development, production clusters, and specialized workloads without hunting through the source. Combine environment-specific overrides with deployment automation to keep behaviour predictable across every environment.
