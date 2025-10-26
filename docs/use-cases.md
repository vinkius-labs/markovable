# Use Cases

Markovable thrives wherever adaptive journeys matter. Here are a few battle-tested scenarios to spark ideas.

## Product Guidance Flows

Build navigation hints from behavioral data so new users discover value faster.

- **Docs**: [Navigation Analyzer](../src/Analyzers/NavigationAnalyzer.php), [Usage Recipes](./usage-recipes.md)
- **Playbook**: [Product Guidance Blueprint](./use-cases/product-guidance-flows.md)
- **How to use**
  1. Log page transitions alongside metadata such as segment or device.
  2. Train a navigation chain and cache it for fast lookups.
  3. Generate next-step hints inside onboarding flows.

```php
$knowledge = Markovable::pageRank()
  ->useGraphBuilder(new App\Markovable\KnowledgeGraph())
  ->option('window', now()->subMonths(6))
  ->groupBy(fn ($id) => Str::before($id, ':'))
  ->calculate();
```

- **Benefits**: Faster self-service resolution, clear contributor recognition, and proactive documentation updates driven by rank shifts.
 
Markovable is intentionally composable. Start with a single experiment, then let curiosity and iteration guide the roadmap.
$knowledge = Markovable::pageRank()
  ->useGraphBuilder(new App\Markovable\KnowledgeGraph())
  ->option('window', now()->subMonths(6))
  ->groupBy(fn ($id) => Str::before($id, ':'))
  ->calculate();
```

- **Benefits**: Faster self-service resolution, clear contributor recognition, and proactive documentation updates driven by rank shifts.

Markovable is intentionally composable. Start with a single experiment, then let curiosity and iteration guide the roadmap.
Markovable::analyze('navigation')
    ->train($historicalTransitions)
    ->cache('onboarding-map')
    ->predict('welcome-screen', 3);
```

- **Benefits**: Personalized onboarding paths, reduced drop-off on key funnels, measurable uplift as journeys evolve.

## Content Ideation Assistants

Keep editorial teams stocked with on-brand phrasing and outline starters.

- **Docs**: [Text Generator](../src/Generators/TextGenerator.php), [Tokenizer](../src/Support/Tokenizer.php)
- **Playbook**: [Content Ideation Blueprint](./use-cases/content-ideation-assistants.md)
- **How to use**
  1. Ingest prior releases, newsletters, and style guides into a text chain.
  2. Seed the generator with a prompt and optional constraints like length or tone.
  3. Review the draft, tweak, and re-train with approved copy for continuous learning.

```php
Markovable::cache('editorial-voice')
    ->generate(60, ['seed' => 'Launch announcement talking points']);
```

- **Benefits**: Faster content iteration, consistent brand voice, living knowledge base that sharpens with every publish.

## Support Response Acceleration

Surface predicted replies so agents stay consistent while shaving precious minutes off ticket handling.

- **Docs**: [Sequence Generator](../src/Generators/SequenceGenerator.php), [Jobs](../src/Jobs/AnalyzePatternsJob.php)
- **Playbook**: [Support Response Blueprint](./use-cases/support-response-acceleration.md)
- **How to use**
  1. Train on resolved ticket transcripts and tag them by category.
  2. Queue `AnalyzePatternsJob` after each resolution to keep probabilities fresh.
  3. Suggest the top replies in the help desk UI and capture agent edits for feedback.

```php
Markovable::analyze('support-sequence')
    ->cache('support-replies')
    ->predict('integration-error', 2);
```

- **Benefits**: Lower first-response time, consistent tone across agents, automatic discovery of emerging issues.

## Feature Adoption Campaigns

Spot adoption gaps and deploy nudges informed by probabilistic predictions.

- **Docs**: [Analytics Builder](../src/Builders/AnalyticsBuilder.php), [Events](../src/Events/PredictionMade.php)
- **Playbook**: [Feature Adoption Blueprint](./use-cases/feature-adoption-campaigns.md)
- **How to use**
  1. Attach observers to key models to emit training data when users adopt features.
  2. Use `AnalyticsBuilder` to aggregate drop-off probabilities by cohort.
  3. Trigger nurture campaigns and re-train after each release to measure uplift.

```php
Markovable::builder('analytics')
    ->fromCache('product-narrative')
    ->probabilitiesFor('checkout-flow');
```

- **Benefits**: Targeted lifecycle messaging, tighter feedback loop between product and marketing, data-backed roadmap priorities.

## Embedded Analytics

Deliver predictions directly inside your SaaS so customers get smarter menus, next-best actions, or bite-sized trend summaries.

- **Docs**: [Markovable Facade](../src/Facades/Markovable.php), [Database Storage](../src/Storage/DatabaseStorage.php)
- **Playbook**: [Embedded Analytics Blueprint](./use-cases/embedded-analytics.md)
- **How to use**
  1. Enable the database storage driver to persist customer-specific models.
  2. Expose cached predictions via API endpoints or widgets in your app.
  3. Schedule `TrainMarkovableJob` to refresh models as new data arrives.

```php
Markovable::storage('database')
    ->cache($tenantKey)
    ->generate(25, ['seed' => $customerContext]);
```

- **Benefits**: Differentiated product experiences, stickier customer workflows, recurring insight loops without manual analysis.

## Predictive Intelligence War Room

Turn noisy customer telemetry into churn alerts, revenue projections, and next-best actions your teams can trust.

- **Docs**: [Predictive Intelligence Guide](./predictive-intelligence.md), [Predictive Builder Playbook](./use-cases/predictive-intelligence.md)
- **Playbook**: [Predictive Intelligence Playbook](./use-cases/predictive-intelligence.md)
- **How to use**
  1. Cache predictive baselines per segment (`analytics::predictive-enterprise`, `analytics::predictive-growth`).
  2. Stream live customer snapshots into the predictive builder and call the scoring engines needed by each team.
  3. Route outputs to the right destinations: churn alerts to CRM, LTV tiers to finance dashboards, next-best actions to lifecycle campaigns.

```php
$insights = Markovable::predictive('analytics::predictive-enterprise')
    ->dataset($latestSignals)
    ->usingOptions([
        'churn' => ['include_recommendations' => true],
        'forecast' => ['metric' => 'monthly_recurring_revenue', 'confidence' => 0.9],
    ]);

$churn = $insights->churnScore()->get();
$forecast = $insights->seasonalForecast()->horizon(3)->get();
$actions = $insights->nextBestAction()->topN(2)->get();
```

- **Benefits**: Shared predictive ground truth across ops, success, and finance; resilient scoring even when upstream data is noisy; faster reactions to behavioural drift.

## SaaS Activation Loops

Keep new accounts progressing toward value by predicting the next best activation step for each cohort.

- **Docs**: [Markovable Chain](../src/MarkovableChain.php), [Train Command](../src/Commands/TrainCommand.php)
- **Playbook**: [SaaS Activation Blueprint](./use-cases/saas-activation.md)
- **How to use**
  1. Stream activation events with contextual metadata like segment, plan tier, and trial age.
  2. Train and cache sequences per segment (`activation-paths:mid-market`, `activation-paths:startup`).
  3. Serve predictions inside onboarding flows or lifecycle campaigns and log accepted suggestions for retraining.

```php
$recommendations = Markovable::analyze('navigation')
    ->cache('activation-paths:'.$segment)
    ->predict($latestEventKey, 3);

if ($recommendations->isNotEmpty()) {
    $nudgeBus->dispatch($accountId, $recommendations->pluck('token'));
}
```

- **Benefits**: Higher trial-to-paid conversion, tighter coordination between product and growth teams, automatic drift alerts that highlight when activation paths change.

## PageRank SaaS Authority Mapping

Surface the in-app steps, guides, and personas that most influence activation, renewal, and expansion.

- **Docs**: [PageRank Analyzer Guide](./pagerank.md), [Graph Builders](./pagerank-graph-builders.md)
- **Playbook**: [SaaS Authority Blueprint](./use-cases/pagerank-saas-authority.md)
- **How to use**
  1. Capture feature engagements and knowledge-base visits with consistent node identifiers.
  2. Build a weighted graph that rewards upgrades, completions, and high-intent actions.
  3. Run PageRank nightly, snapshot results, and alert on percentile drops for critical nodes.

```php
$authority = Markovable::pageRank()
    ->useGraphBuilder(new App\Markovable\SaaSAuthorityGraph())
    ->groupBy('prefix')
    ->includeMetadata()
    ->calculate();
```

- **Benefits**: Prioritized optimization backlog, automated drift detection for activation flows, shared visibility into the most influential features.

## Community Knowledge Graph

Elevate the most authoritative docs, tutorials, and community contributors.

- **Docs**: [PageRank Analyzer Guide](./pagerank.md)
- **Playbook**: [Community Knowledge Blueprint](./use-cases/pagerank-community-knowledge.md)
- **How to use**
  1. Store cross-links and engagements between articles, tutorials, and community replies.
  2. Build a bipartite graph that weights accepted answers and high-value interactions.
  3. Publish top-ranked content to search and documentation portals while notifying community managers about rising contributors.

```php
use Illuminate\Support\Str;

$knowledge = Markovable::pageRank()
    ->useGraphBuilder(new App\Markovable\KnowledgeGraph())
    ->option('window', now()->subMonths(6))
    ->groupBy(fn ($id) => Str::before($id, ':'))
    ->calculate();
```

- **Benefits**: Faster self-service resolution, clear contributor recognition, and proactive documentation updates driven by rank shifts.

Markovable is intentionally composable. Start with a single experiment, then let curiosity and iteration guide the roadmap.
