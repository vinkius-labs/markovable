# PageRank Graph Builders

Builders let you transform raw product telemetry into the adjacency matrices consumed by the PageRank analyzer. They encapsulate the extraction, weighting, and filtering logic so the analyzer remains focused on ranking.

## Contract

Implement the `VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder` interface:

```php
namespace App\Markovable;

use VinkiusLabs\Markovable\Contracts\PageRankGraphBuilder;
use VinkiusLabs\Markovable\MarkovableChain;

class RelationshipGraph implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        // Return adjacency list formatted as ['source' => ['target' => weight]]
    }
}
```

The analyzer injects the active `MarkovableChain`, the baseline model array (if provided), and the option bag passed to `Markovable::pageRank()`.

## Composition Patterns

### Navigation Sessions

Map ordered page view sequences into weighted transitions. Favor counts or dwell time as weights to reward high-engagement paths.

```php
class NavigationGraph implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        return PageView::query()
            ->select('previous_url', 'current_url')
            ->selectRaw('count(*) as weight')
            ->groupBy('previous_url', 'current_url')
            ->get()
            ->groupBy('previous_url')
            ->map(fn ($rows) => $rows->pluck('weight', 'current_url')->all())
            ->all();
    }
}
```

### Bipartite Engagement Graphs

Blend users and content (or features) into a two-mode network when you want to rank one side by influence of the other.

```php
class EngagementGraph implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        $weights = [
            'viewed' => 1,
            'liked' => 2,
            'shared' => 3,
        ];

        return Event::query()
            ->select('user_id', 'content_id', 'action')
            ->get()
            ->groupBy('user_id')
            ->map(function ($events) use ($weights) {
                return $events->groupBy('content_id')->map(function ($rows) use ($weights) {
                    return $rows->sum(fn ($row) => $weights[$row->action] ?? 1);
                })->all();
            })
            ->all();
    }
}
```

### Weighted Relationship Graphs

Leverage relationship strength such as recurring purchases or referral counts. Normalize weights or let the calculator do it (positive numbers only).

```php
class ReferralGraph implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        return Referral::query()
            ->select('referrer_id', 'invitee_id')
            ->selectRaw('count(*) as weight')
            ->groupBy('referrer_id', 'invitee_id')
            ->get()
            ->groupBy('referrer_id')
            ->map(fn ($rows) => $rows->pluck('weight', 'invitee_id')->all())
            ->all();
    }
}
```

## Option Forwarding

Any option passed to `Markovable::pageRank()` is forwarded to the builder. Use them to switch segments, date ranges, or weighting strategies.

```php
Markovable::pageRank()
    ->useGraphBuilder(new NavigationGraph())
    ->option('segment', 'enterprise')
    ->option('window', now()->subDays(30))
    ->calculate();
```

Inside `build()`, read `$options['segment']` or `$options['window']` to adjust queries dynamically.

## Testing Builders

- Unit test builders by invoking `build()` with a mocked `MarkovableChain` and fixture data.
- When queries are involved, prefer database factories and wrap tests with `DatabaseTransactions` for rollback.
- Validate that every node present as a target is also returned as a source (the calculator will add missing nodes, but explicit entries avoid surprises).

## Next Steps

- Return to the [PageRank Analyzer Guide](./pagerank.md) for runtime and CLI configuration.
- Explore the [SaaS Authority Blueprint](./use-cases/pagerank-saas-authority.md) and [Community Knowledge Blueprint](./use-cases/pagerank-community-knowledge.md) for end-to-end examples.
