# Training Markovable Chains

Training a Markovable chain is a CPU-first workflow. The engine relies on counting token transitions rather than matrix multiplications, so you can run every step on commodity hardware—no GPUs or specialized accelerators required.

## Before You Start

- Ensure the package is installed and configured (see [Getting Started](getting-started.md)).
- Publish and run the migrations when you plan to persist models, anomalies, or cluster profiles beyond cache storage.
- Decide where you want trained models to live: cache (default), database, or file storage. You can switch per training run.
- When preparing graph-centric workloads, review the [PageRank Analyzer Guide](./pagerank.md) so your training datasets align with the expected adjacency structures.

## How Training Works

1. Collect your source corpus as strings, arrays, collections, or Eloquent results.
2. Tokenize and build state transitions with `train()` or `trainFrom()`.
3. Optionally persist the trained model via cache, database, or file storage so it can be reused later.
4. Generate content, analyze journeys, or detect anomalies from the trained state.

Because each step manipulates PHP arrays, the heaviest cost is CPU time proportional to the number of tokens. Memory and execution time scale linearly with input size—plan batch sizes accordingly.

## Training from PHP

Minimal in-memory training with automatic caching:

```php
use VinkiusLabs\Markovable\Facades\Markovable;

Markovable::train([
    'Every release deserves a narrative arc.',
    'Markovable keeps product storytelling adaptive.',
])->cache('product-narrative');
```

Custom order and explicit storage selection:

```php
use Illuminate\Support\Collection;
use VinkiusLabs\Markovable\Facades\Markovable;

$transcripts = Collection::make($podcastEpisodes) // any iterable of strings
    ->map(fn ($episode) => $episode['summary']);

Markovable::chain('text')
    ->order(3)
    ->useStorage('database')
    ->train($transcripts)
    ->cache('podcast-story-arc', 86400);
```

- `order()` controls how many tokens are considered as context when predicting the next state.
- `useStorage()` accepts `cache`, `database`, or `file` (extendable via config).
- `cache()` persists the trained model and makes it retrievable via the same key in future requests.

## Training via Artisan

Use the bundled Artisan command when pulling data from files or Eloquent models:

```bash
php artisan markovable:train \
  --file=storage/app/corpus.ndjson \
  --order=3 \
  --cache-key=product-narrative \
  --storage=database
```

Key options:

- `--file=`: Path to a newline-delimited text file.
- `--model=` and `--field=`: Load content from an Eloquent model field.
- `--order=`: Context size (defaults to `2`).
- `--cache-key=`: Destination key for the trained model.
- `--storage=`: Persist to `cache`, `database`, or `file` backends.
- `--queue`: Dispatches the training workload onto your configured queue.

The command validates inputs and reports missing files, models, or fields before any work is done.

## Preparing Predictive Baselines

Predictive workloads (churn, LTV, seasonal forecasts, next-best actions) rely on cached baselines that you train the same way as any other chain. The difference is how you reuse the baseline across multiple predictors inside the [Predictive Intelligence flow](./predictive-intelligence.md).

1. Train an `analytics` chain with the historical journeys or telemetry that anchor your predictions.
2. Cache it with a descriptive key (for example, `analytics::predictive-enterprise`).
3. Rehydrate the baseline via `Markovable::predictive($key)` and stream fresh snapshots into `dataset()` when scoring.

```php
$baselineKey = 'analytics::predictive-retention';

Markovable::chain('analytics')
    ->cache($baselineKey)
    ->train($historicalSessions);

$builder = Markovable::predictive($baselineKey)
    ->dataset($liveSnapshots)
    ->usingOptions([
        'churn' => ['include_recommendations' => true],
        'forecast' => ['metric' => 'monthly_recurring_revenue', 'confidence' => 0.9],
        'ltv' => ['segments' => ['self_serve', 'enterprise'], 'include_historical' => true],
    ]);

$churn = $builder->churnScore()->get();
```

For scenario-specific guidance, see the [Predictive Intelligence Playbook](./use-cases/predictive-intelligence.md).

- **Multi-tenant tip**: store baseline keys per tenant or cohort (e.g., `analytics::predictive-tenant:{id}`) and reuse the same predictive builder to feed dashboards, renewal playbooks, and billing forecasts.

## Preparing PageRank Graphs

Training datasets often double as the raw material for PageRank insights. Once you have a cached baseline or a curated edge list, pass the adjacency to the PageRank analyzer and iterate with the graph builder interface documented in [PageRank Graph Builders](./pagerank-graph-builders.md).

```php
use App\Markovable\NavigationGraphBuilder;
use VinkiusLabs\Markovable\Facades\Markovable;

// Cached navigation journeys feed the graph builder that emits adjacency weights.
$authority = Markovable::pageRank()
    ->useGraphBuilder(app(NavigationGraphBuilder::class))
    ->dampingFactor(0.9)
    ->topNodes(15)
    ->includeMetadata()
    ->calculate();
```

Graph builders can ingest the same corpora you curate during training, so keep your preprocessing reusable. The SaaS and community blueprints in [Markovable Use Cases](./use-cases.md) show how to cascade trained Markovable models into PageRank snapshots and downstream reports.

## Queueing Training Jobs

For larger corpora or scheduled retraining, offload work to a queue worker:

```php
use Illuminate\Support\Facades\Bus;
use VinkiusLabs\Markovable\Facades\Markovable;

$job = Markovable::train($dailySessions)
    ->cache('navigation-daily')
    ->queue();

Bus::dispatch($job);
```

Queue connectivity and default queue names are configurable via `config/markovable.php`. Combined with Laravel Horizon or another queue manager, this approach keeps HTTP requests responsive while CPUs process training batches in the background.

## Verifying a Trained Model

Once training completes, attach to the cached model and exercise its capabilities:

```php
$teaser = Markovable::cache('product-narrative')->generate(18);

$nextSteps = Markovable::analyze('navigation')
    ->cache('product-narrative')
    ->predict('launch timeline', 3);
```

If the output feels too repetitive or noisy, adjust the order, enrich the corpus, or split training runs by segment (locale, persona, product tier).

## Troubleshooting

- **Empty outputs**: Confirm your corpus is not empty and contains at least `order + 1` tokens.
- **Large memory spikes**: Train in smaller batches and merge by reusing the same cache key (`->cache('key')`) across runs.
- **Conflicting storage drivers**: Make sure the chosen storage backend is configured and available in `markovable.storages`.
- **Slow queues**: Increase worker concurrency or configure a dedicated queue connection for training.

With these practices in place, you can retrain Markovable models regularly and confidently—no GPUs required.
