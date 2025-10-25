# Architecture

Markovable balances Laravel comfort with computational power. Understanding the moving parts helps you bend it to ambitious pipelines.

## Core Components

- **MarkovableChain** – The fluent interface for training, caching, generating, and analyzing. Chains are stateless builders that delegate storage and processing to dedicated collaborators.
- **MarkovableManager** – Resolves builders, generators, analyzers, and storages. Extend it to plug in your own strategies or swap implementations on the fly.
- **Generators** – Convert Markov models into outputs. `TextGenerator` emits natural language, while `SequenceGenerator` excels at navigation paths.
- **Analyzers** – Provide introspection, predictions, and probability logic. `TextAnalyzer` and `NavigationAnalyzer` ship by default, and you can register more.
- **Storage Drivers** – Decide where trained models live (cache, database, file). Write additional drivers for S3, Redis clusters, or any custom persistence.

## Lifecycle

1. **Train** – Tokenize inputs and build a Markov matrix.
2. **Persist** – Optionally store the matrix via the selected storage driver.
3. **Generate/Analyze** – Reuse the chain, reload from cache, or queue background jobs to process asynchronously.
4. **Observe** – Leverage Laravel events (`ModelTrained`, `ContentGenerated`, `PredictionMade`) to instrument analytics or trigger side effects.

## Extensibility Hooks

- `Markovable::extendBuilder()` for alternate chain contexts.
- `Markovable::extendGenerator()` for custom outputs (e.g., JSON-LD, DSL).
- `Markovable::extendAnalyzer()` to run bespoke scoring algorithms.
- Traits like `TrainsMarkovable` and observers (`AutoTrainObserver`) to keep Eloquent models self-sustaining.

## Performance Tips

- Cache frequently used chains with TTLs to avoid rebuilding models on every request.
- Use job queues for large corpora and set `markovable.queue` options to balance throughput.
- Export trained models and re-import them in worker nodes to minimize cold starts.

Markovable stays light but leaves plenty of hooks for teams chasing scale, safety, and creativity in equal measure.
