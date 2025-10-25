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
2. Run the migration for the Markovable cache table if you plan to persist models beyond memory:
   ```bash
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

## Dream Bigger

- Explore the [Usage Recipes](usage-recipes.md) for deeper scenarios.
- Visit the [Use Cases](use-cases.md) page to discover real-world inspiration.
- Peek behind the curtain in [Architecture](architecture.md).

You now have a working Markovable chain—push it further! 🎯
