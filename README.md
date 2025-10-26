# Markovable

Markovable is a Laravel-native engine for building adaptive Markov chains that learn from your product’s voice and user journeys. It turns familiar Eloquent patterns into powerful prediction, generation, anomaly detection, and analysis flows so you can ship intelligent experiences without leaving PHP.

> "Give your data a memory and it will return with stories you never thought to ask." – The Markovable Manifesto ✨

## Why Markovable?

- **Human DX** – API-first design, fluent builders, and sensible defaults keep developers in flow.
- **Production Ready** – Cache, database, and file storage drivers out of the box, plus queue-friendly jobs.
- **SaaS Native** – Multi-tenant caching, predictive scoring, and lifecycle workflows keep subscription products informed in real time.
- **Composable** – Extend analyzers, generators, and builders to fit any domain-specific language or dataset.
- **Observable** – Built-in events, anomaly alerts, and exports make analytics, monitoring, and audits effortless.

## Table of Contents

1. [Getting Started](docs/getting-started.md)
2. [Training Guide](docs/training-guide.md)
3. [Usage Recipes](docs/usage-recipes.md)
4. [Use Cases](docs/use-cases.md)
5. [Predictive Intelligence](docs/predictive-intelligence.md)
6. [Architecture](docs/architecture.md)
7. [Contributing](docs/contributing.md)
8. [Technical Reference](docs/technical-reference.md)

## Quick Peek

```php
use VinkiusLabs\Markovable\Facades\Markovable;

$baseline = 'analytics::predictive-saas';

Markovable::chain('analytics')
    ->cache($baseline)
    ->train($historicalSessions);

$insights = Markovable::predictive($baseline)
    ->dataset($latestTenantSnapshots)
    ->usingOptions([
        'churn' => ['include_recommendations' => true],
        'ltv' => ['segments' => ['self_serve', 'enterprise'], 'include_historical' => true],
    ]);

$churnAlerts = $insights->churnScore()->get();
$ltvReport = $insights->ltv()->get();
```

## What’s Inside

- Feature-rich `MarkovableChain` for training, caching, generating, and analyzing sequences.
- Generators tuned for natural language and navigation flows.
- Analyzer strategies to surface probabilities, detect anomalies, and predict next-best actions.
- Detectors and monitoring pipelines to surface unseen sequences, emerging patterns, seasonality shifts, and behaviour drift.
- Traits and observers that keep Eloquent models self-training.
- Artisan commands to orchestrate training, generation, and analysis from the CLI.

## Ready to Explore?

Head over to the docs linked above to dive into setup, recipes, and architecture deep dives. Pair Markovable with your favorite Laravel tools, automate the mundane, and let curiosity lead the roadmap.

If you build something brilliant, we want to hear about it—open an issue or PR, or share your story. Happy modelling! 🚀
