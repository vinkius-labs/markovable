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

Markovable is intentionally composable. Start with a single experiment, then let curiosity and iteration guide the roadmap.
