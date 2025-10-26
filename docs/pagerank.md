# PageRank Analyzer Guide

PageRank in Markovable helps you rank navigation paths, entities, and relationships using classic random-surfer probabilities refined for SaaS-scale graphs. The fluent builder API streams datasets into Markov-compatible adjacency matrices, while metadata snapshots keep every calculation auditable.

> Use the PageRank builder whenever you want to understand authority, influence, or stickiness across connected nodes—whether the nodes are pages, products, users, or support articles.

## Feature Overview

- **Fluent entry point**: `Markovable::pageRank()` configures the analyzer, graph source, and output options.
- **Graph flexibility**: Accept existing transition matrices, raw relationship arrays, or custom `PageRankGraphBuilder` implementations.
- **Deterministic outputs**: Receives damping, convergence threshold, and iteration caps that ensure predictable runtime.
- **Rich payloads**: Returns `PageRankResult` instances containing raw scores, normalized percentages, percentiles, and optional grouping metadata.
- **CLI parity**: `php artisan markovable:pagerank` mirrors the builder so you can schedule jobs and export insights alongside other Markovable tasks.

## Quick Start

```php
use VinkiusLabs\Markovable\Facades\Markovable;

$ranks = Markovable::pageRank()
    ->withGraph([
        '/home' => ['/pricing' => 3, '/blog' => 1],
        '/pricing' => ['/home' => 1],
        '/blog' => ['/home' => 1],
    ])
    ->dampingFactor(0.9)
    ->topNodes(5)
    ->includeMetadata()
    ->calculate();

$home = $ranks['pagerank']['/home'];
// ['raw_score' => 0.332154, 'normalized_score' => 100.00, 'percentile' => 100.0]
```

Call `result()` if you prefer the `PageRankResult` object instead of an array payload:

```php
$result = Markovable::pageRank()->withGraph($graph)->result();
$nodes = $result->nodes();           // array<string, PageRankNode>
$metadata = $result->metadata();     // includes iterations, damping, context, baseline key
```

## Builder Options

| Method | Purpose |
| --- | --- |
| `withGraph(array $graph)` | Provide an adjacency list where each edge weight is normalized during calculation. |
| `useGraphBuilder(PageRankGraphBuilder $builder)` | Resolve the graph lazily from models, analytics pipelines, or API sources. |
| `dampingFactor(float $value)` | Clamp between `0` and `1` (default `0.85`). Higher values emphasise in-graph transitions. |
| `convergenceThreshold(float $value)` | Stop iteration when max delta drops below this tolerance (default `1e-6`). |
| `maxIterations(int $value)` | Hard stop to prevent infinite loops (default `100`). |
| `topNodes(int $limit)` | Return only the highest-ranked nodes while preserving normalized scores. |
| `groupBy(callable|string $strategy)` | Group nodes by prefix, domain, custom segment, or callback result. |
| `includeMetadata(bool $flag = true)` | Embed metadata and preserve the full `PageRankResult` object in the payload. |

## Graph Sources

### 1. Direct Transition Matrices
Supply an adjacency list where weights reflect click counts, purchases, or relationship strength. The calculator normalizes each row automatically and adds dangling nodes when necessary.

### 2. Graph Builders
Implement `Contracts\PageRankGraphBuilder` to construct the graph dynamically.

```php
use VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder;
use VinkiusLabs\Markovable\MarkovableChain;

class NavigationGraphBuilder implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        return PageView::query()
            ->select('from', 'to', 'weight')
            ->get()
            ->groupBy('from')
            ->map(fn ($rows) => $rows->pluck('weight', 'to')->all())
            ->all();
    }
}
```

Attach the builder:

```php
Markovable::pageRank()->useGraphBuilder(new NavigationGraphBuilder())->calculate();
```

See [PageRank Graph Builders](./pagerank-graph-builders.md) for advanced composition patterns, including bipartite graphs and weighted relationships.

### 3. Cached Markovable Models
If you already maintain a trained Markovable model, pass its adjacency structure directly via `Markovable::chain('navigation')->cache(...)->model()` and forward that matrix to the builder.

## CLI and Automation

Run calculations without writing PHP by invoking the Artisan command:

```bash
php artisan markovable:pagerank baseline-key \
    --graph-builder="App\\Markovable\\ProductGraph" \
    --damping=0.85 \
    --iterations=150 \
    --export=storage/app/pagerank.json
```

- `baseline` argument maps to the cached model key used for context metadata.
- `--graph` accepts a JSON file path with adjacency data.
- `--graph-builder` resolves a container binding implementing `PageRankGraphBuilder`.
- `--store` persists the result via `PageRankSnapshot::capture()` for audits or diffing.
- `--export` writes the array payload, including metadata when requested.

All command options are documented in the [Artisan Command Reference](./command-reference.md#markovablerank).

## Metadata & Snapshots

The analyzer returns metadata such as iterations, convergence flag, damping factor, and baseline key. Call `PageRankSnapshot::capture($modelKey, $result)` to persist versioned outcomes and surface drift later in dashboards.

Snapshots are stored using the same storage abstraction as other Markovable models, enabling historical comparisons or regression test fixtures.

## Observability and Testing

- `tests/Unit/PageRank*Test.php` demonstrate how to assert damping bounds, grouping strategies, and builder validation.
- For regression tests, feed deterministic graphs and compare the resulting `PageRankNode` arrays to fixture expectations.
- Use Laravel's scheduling to run `markovable:pagerank` nightly and export JSON for analytics tooling.

## Next Steps

- Dive into [PageRank Graph Builders](./pagerank-graph-builders.md) to compose graphs from navigation, relational, or event data.
- Explore [PageRank SaaS Authority Blueprint](./use-cases/pagerank-saas-authority.md) and [Community Knowledge Blueprint](./use-cases/pagerank-community-knowledge.md) for end-to-end scenarios.
- Wire PageRank alongside anomaly detection by comparing current ranks with historical snapshots to spot authority regressions.
