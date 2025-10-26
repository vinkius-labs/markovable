# PageRank SaaS Authority Blueprint

Identify which features, help articles, and personas exert the most influence inside your SaaS product. PageRank surfaces the real hubs driving activation, renewals, and expansion.

## When to Reach for It
- Activation cohorts stall after completing a few steps and you cannot pinpoint the blocker.
- Success managers want proof that in-app guides or help-center content actually drives expansion.
- Product growth teams need a ranked list of the nodes most worth optimizing each sprint.

## Data Model and Capture
- Log feature interactions and knowledge-base visits with `account_id`, `user_id`, `node_key`, `action`, `occurred_at`.
- Normalize keys across surfaces (`settings.billing`, `guide.onboarding.step3`, `help.jwt-rotation`).
- Optionally enrich events with plan, lifecycle stage, and ARR band.

### Example Migration
```php
Schema::create('engagement_events', function (Blueprint $table) {
    $table->id();
    $table->uuid('account_id');
    $table->foreignId('user_id')->nullable();
    $table->string('node_key');
    $table->string('next_node_key')->nullable();
    $table->string('action')->default('viewed');
    $table->json('context')->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();
});
```

## Graph Builder
Transform path sequences into weighted edges. Include stronger weights for sticky actions (e.g. `completed`, `upgraded`).

```php
class SaaSAuthorityGraph implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        $weights = ['viewed' => 1, 'completed' => 3, 'upgraded' => 5];

        return EngagementEvent::query()
            ->where('occurred_at', '>=', $options['from'] ?? now()->subMonths(3))
            ->select('node_key', 'next_node_key', 'action')
            ->get()
            ->groupBy('node_key')
            ->map(function ($rows) use ($weights) {
                return $rows->groupBy('next_node_key')
                    ->map(fn ($edge) => $edge->sum(fn ($row) => $weights[$row->action] ?? 1))
                    ->all();
            })
            ->all();
    }
}
```

## Training Pipeline

```php
$result = Markovable::pageRank()
    ->useGraphBuilder(new SaaSAuthorityGraph())
    ->option('from', now()->subMonths(3))
    ->dampingFactor(0.85)
    ->groupBy('prefix')
    ->includeMetadata()
    ->calculate();
```

Cache the result metadata to compare across quarters:

```php
PageRankSnapshot::capture('saas-authority:q1', $result['__result']);
```

## Insight Delivery
- Publish the top nodes to a product analytics dashboard.
- Flag nodes whose percentile dropped more than 20 points compared to the last snapshot.
- Export the grouped payload to marketing automation so lifecycle emails spotlight the highest-ranked features per segment.

## Alerting Playbook

```php
$latest = Markovable::pageRank()->useGraphBuilder(new SaaSAuthorityGraph())->result();
$previous = PageRankSnapshot::latestFor('saas-authority')?
    ->payload['pagerank'] ?? [];

detectAuthorityDrop($latest->toArray(), $previous);
```

Alert owners when a core feature falls below the 70th percentile. Pair with `Markovable::detect('activation')` to cross-check anomaly spikes.

## Outcomes
- Prioritized roadmap backed by actual influence of features and content.
- Automated alerts when critical activation steps lose authority.
- Shared language between product, marketing, and success teams around what truly drives expansion.
