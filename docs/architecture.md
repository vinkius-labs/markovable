# Architecture

Markovable balances Laravel comfort with computational power. Understanding the moving parts helps you bend it to ambitious pipelines.

## Core Components

- **MarkovableChain** – The fluent interface for training, caching, generating, analyzing, clustering, and monitoring. Chains are stateless builders that delegate storage and processing to dedicated collaborators.
- **MarkovableManager** – Resolves builders, generators, analyzers, detectors, and storages. Extend it to plug in your own strategies or swap implementations on the fly.
- **Generators** – Convert Markov models into outputs. `TextGenerator` emits natural language, while `SequenceGenerator` excels at navigation paths.
- **Analyzers** – Provide introspection, predictions, anomaly scoring, and probability logic. `TextAnalyzer`, `NavigationAnalyzer`, and `Analyzers\AnomalyDetector` ship by default, and you can register more.
- **Detectors** – Lightweight engines (`Detectors\UnseenSequenceDetector`, `EmergingPatternDetector`, `DriftDetector`, `Analyzers\SeasonalAnalyzer`) that expose a shared `Contracts\Detector` interface for anomaly hunting.
- **Monitoring Pipelines** – `Support\MonitorPipeline` orchestrates recurring anomaly scans and alert routing.
- **Storage Drivers** – Decide where trained models live (cache, database, file). Write additional drivers for S3, Redis clusters, or any custom persistence.
- **Domain Models** – `Models\AnomalyRecord`, `PatternAlert`, and `ClusterProfile` persist insights when migrations are published.

## Lifecycle

1. **Train** – Tokenize inputs and build a Markov matrix.
2. **Persist** – Optionally store the matrix via the selected storage driver.
3. **Generate/Analyze** – Reuse the chain, reload from cache, or queue background jobs to process asynchronously.
4. **Detect & Monitor** – Compare fresh data to cached baselines, surface anomalies, and group sessions into clusters.
5. **Observe** – Leverage Laravel events (`ModelTrained`, `ContentGenerated`, `PredictionMade`, `AnomalyDetected`, `PatternEmerged`, `ClusterShifted`) to instrument analytics or trigger side effects.

## Extensibility Hooks

- `Markovable::extendBuilder()` for alternate chain contexts.
- `Markovable::extendGenerator()` for custom outputs (e.g., JSON-LD, DSL).
- `Markovable::extendAnalyzer()` / `Markovable::extend()` to run bespoke scoring algorithms.
- Register additional detectors by implementing `Contracts\Detector` and pushing them into custom `Analyzers\AnomalyDetector` macros or subclasses.
- Traits like `TrainsMarkovable` and observers (`AutoTrainObserver`) to keep Eloquent models self-sustaining.

## Anomaly & Pattern Detection Topology

```
MarkovableChain::detect() → Analyzers\AnomalyDetector → Support\DetectionContext
										  ├── Detectors\UnseenSequenceDetector
										  ├── Detectors\EmergingPatternDetector
										  ├── Analyzers\SeasonalAnalyzer
										  └── Detectors\DriftDetector

MarkovableChain::monitor() → Support\MonitorPipeline
										   └── orchestrates detectors + alert channels

MarkovableChain::cluster() → Detectors\ClusterAnalyzer → Events\ClusterShifted
```

Key behaviours:

- **DetectionContext** rebuilds baseline frequencies, probabilities, and metadata so detectors stay stateless.
- **AnomalyDetector** aggregates detector results, persists them (when migrations are in place), and dispatches broadcastable events.
- **MonitorPipeline** wraps the detector in a repeatable schedule with alert routing (Slack, email, PagerDuty, webhooks).
- **ClusterAnalyzer** segments navigation paths into lightweight behavioural profiles.

## Performance Tips

- Cache frequently used chains with TTLs to avoid rebuilding models on every request.
- Use job queues for large corpora and set `markovable.queue` options to balance throughput.
- Export trained models and re-import them in worker nodes to minimize cold starts.

Markovable stays light but leaves plenty of hooks for teams chasing scale, safety, and creativity in equal measure.
