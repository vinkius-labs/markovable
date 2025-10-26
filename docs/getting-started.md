# Getting Started

Welcome to **Markovable**, a Laravel-friendly engine for crafting adaptive Markov chains. This guide gets you from installation to your first generated insight in a few minutes.

## Installation

```bash
composer require vinkius-labs/markovable
```

Once installed, publish the configuration if you want to customize defaults:

```bash
php artisan vendor:publish --provider="VinkiusLabs\\Markovable\\ServiceProvider"
```

Review every option and environment override in the [Configuration Guide](configuration.md) before rolling changes into staging or production.

## Requirements

- PHP 8.2 or 8.3
- Laravel 10, 11, or 12
- Composer 2+

## Bootstrapping the Package

1. Add the service provider if you are not using package auto-discovery:
   ```php
   // config/app.php
   'providers' => [
       // ...
       VinkiusLabs\Markovable\ServiceProvider::class,
   ],
   ```
2. Publish the migrations and run them if you plan to persist models, anomalies, or cluster profiles beyond memory:
   ```bash
    php artisan vendor:publish --provider="VinkiusLabs\\Markovable\\ServiceProvider" --tag=markovable-migrations
    php artisan migrate
   ```

## First Training Session

```php
use VinkiusLabs\Markovable\Facades\Markovable;

Markovable::train([
    'Laravel automations feel almost magical.',
    'Markovable helps the magic stay predictable.',
])->cache('welcome-sequence');
```

## Generate Content

```php
$text = Markovable::cache('welcome-sequence')->generate(12);
```

## Analyze Paths

```php
$predictions = Markovable::analyze('navigation')
    ->cache('welcome-sequence')
    ->predict('Laravel', 5);
```

## Detect Anomalies Early

```php
// 1. Establish your baseline once
Markovable::train($historicalNavigation)
    ->option('meta', ['pattern_history' => $historyByDay])
    ->cache('navigation-baseline');

// 2. Compare live behaviour against the baseline
$signals = Markovable::train($latestSessions)
    ->detect('navigation-baseline')
    ->unseenSequences()
    ->emergingPatterns()
    ->detectSeasonality()
    ->drift()
    ->threshold(0.1)
    ->minimumFrequency(10)
    ->get();

// 3. Wire continuous monitoring (optional)
$summary = Markovable::train($rollingWindow)
    ->monitor('navigation-baseline')
    ->detectAnomalies([
        'unseenSequences' => ['threshold' => 0.05],
        'emergingPatterns' => ['minFrequency' => 15, 'growth' => 0.4],
        'seasonality' => ['metrics' => ['weekday']],
    ])
    ->alerts([
        'critical' => ['email' => 'ops@company.com'],
        'high' => ['slack' => '#ops-alerts'],
    ])
    ->checkInterval('5 minutes')
    ->start();
```

Prefer the CLI? Run the dedicated Artisan command after syncing your baseline model:

```bash
php artisan markovable:detect-anomalies --model=navigation-baseline --input=storage/app/live-sessions.ndjson
```

Detected anomalies are stored via Eloquent models (when migrations are published) and broadcast through rich events you can listen to for Slack, PagerDuty, or custom dashboards.

## Dream Bigger

- Explore the [Usage Recipes](usage-recipes.md) for deeper scenarios.
- Visit the [Use Cases](use-cases.md) page to discover real-world inspiration.
- Peek behind the curtain in [Architecture](architecture.md).
- Learn every detector, command, and event inside the [Technical Reference](technical-reference.md).

You now have a working Markovable chain—push it further! 🎯
