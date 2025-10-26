# PageRank Community Knowledge Blueprint

Rank community articles, forum answers, and contributors so customers find authoritative guidance quickly.

## When to Reach for It
- Support ticket load is heavy because community answers are hard to surface.
- You host a public developer portal with thousands of cross-linked tutorials and want automatic relevance scoring.
- Product marketing wants to identify influential community members for advocacy programs.

## Data Model and Capture
- Store interactions with `actor_id`, `content_id`, `target_id`, `action`, `weight`, and timestamps.
- Map internal knowledge-base links (`article -> related_article`) and user engagements (`member -> article`).
- Capture tags or categories to group ranked results.

### Example Migration
```php
Schema::create('knowledge_graph_edges', function (Blueprint $table) {
    $table->id();
    $table->uuid('source_id');
    $table->uuid('target_id');
    $table->string('source_type');
    $table->string('target_type');
    $table->string('action')->default('referenced');
    $table->unsignedInteger('weight')->default(1);
    $table->json('context')->nullable();
    $table->timestamps();
});
```

## Graph Builder
Support bipartite graphs by joining authors and content. Weight links by action or accepted-answer status.

```php
class KnowledgeGraph implements PageRankGraphBuilder
{
    public function build(MarkovableChain $chain, array $model, array $options = []): array
    {
        $window = $options['window'] ?? now()->subMonths(6);

        return KnowledgeGraphEdge::query()
            ->where('created_at', '>=', $window)
            ->get()
            ->groupBy('source_id')
            ->map(function ($edges) {
                return $edges->groupBy('target_id')
                    ->map(fn ($list) => $list->sum('weight'))
                    ->all();
            })
            ->all();
    }
}
```

## Training Pipeline

```php
$result = Markovable::pageRank()
    ->useGraphBuilder(new KnowledgeGraph())
    ->option('window', now()->subMonths(6))
    ->groupBy(fn (string $id) => Str::before($id, ':'))
    ->includeMetadata()
    ->calculate();
```

Segment by node type to highlight article vs. contributor authority. Persist snapshots for quarter-over-quarter comparisons.

## Operationalizing
- Feed the top-ranked articles into search autocomplete and documentation landing pages.
- Notify community managers when contributor percentile ranks surpass thresholds to trigger rewards.
- Compare the latest snapshot with a baseline to detect rising topics or declining guides.

## Integration Tips
- Combine with `Markovable::detect('support')` to flag anomaly spikes, then inspect PageRank movement to see which article lost influence.
- Export results to BI warehouses to blend with ticket deflection metrics.

## Outcomes
- Faster self-service resolution as authoritative articles surface automatically.
- Clear visibility into which contributors drive the most impact.
- Continuous feedback loop between support, documentation, and community teams.
