# Markovable Artisan Command Reference

This guide documents every Markovable Artisan command, the parameters they accept, and practical examples that map directly to production operations. Commands are safe to invoke from the host terminal or inside the project Docker container (e.g. `docker-compose exec app php artisan …`).

See [Artisan Command Operations Playbook](./use-cases/artisan-commands.md) for real-world usage patterns, CI/CD integrations, and automation scenarios. See [Markovable Use Cases](./use-cases/markovable-use-cases.md) for specific project examples and workflows.

## Table of Contents

- [markovable:train](#markovabletrain)
- [markovable:snapshot](#markovablesnapshot)
- [markovable:schedule](#markovableschedule)
- [markovable:report](#markovablereport)
- [markovable:generate](#markovablegenerate)
- [markovable:analyze](#markovableanalyze)
- [markovable:detect](#markovabledetect)
- [markovable:pagerank](#markovablerank)

---

## `markovable:train`

Train (or incrementally update) a cached Markovable model from a variety of sources.

```bash
php artisan markovable:train {model}
    {--source=eloquent}
    {--data=}
    {--order=2}
    {--cache}
    {--storage=}
    {--incremental}
    {--async}
    {--notify=}
    {--tag=}
    {--context=text}
    {--meta=*}
```

| Parameter | Required | Description |
| --- | --- | --- |
| `model` | Yes | Cache key identifier (e.g. `user-navigation`). When `--cache` is set, the final cache key is `{model}:{tag|latest}`. |
| `--source` | No | Data source: `eloquent`, `csv`, `json`, `api`, or `database`. Defaults to `eloquent`. |
| `--data` | Conditional | Source-specific pointer: Eloquent class name, file path, API URL, or `connection:table:column` triplet for database mode. |
| `--order` | No | Markov order (window size). Defaults to `2`. |
| `--cache` | No | Persist the trained model using the default or specified storage driver. |
| `--storage` | No | Override storage driver (`cache`, `database`, `file`, or a custom driver). |
| `--incremental` | No | Merge new data into the cached model. Requires `--cache` or `--tag` so the baseline can be retrieved. |
| `--async` | No | Dispatch `TrainMarkovableJob` to the queue instead of training synchronously. |
| `--notify` | No | Send completion notifications. Supported channels: `log`, `email:address1,address2`, `webhook:url`. |
| `--tag` | No | Version tag appended to the cache key (e.g. `--tag=production`). |
| `--context` | No | Chain context to resolve proper builder/generator (defaults to `text`). |
| `--meta` | No | Repeatable key/value pairs stored in model metadata. Example: `--meta=environment=staging --meta=team=ml`. |

### Examples

```bash
# Train from a CSV dataset and persist in redis-backed cache
php artisan markovable:train journeys --source=csv --data=storage/datasets/journeys.csv --cache --tag=v1.0

# Incrementally append API events to an existing cached model
php artisan markovable:train events --source=api --data="https://api.example.com/events" --cache --tag=rolling --incremental

# Queue training using a database column as corpus
php artisan markovable:train navigation --source=database --data=analytics:page_events:sequence --cache --async
```

---

## `markovable:snapshot`

Persist a point-in-time snapshot of a cached model for rollback, audit, or cross-environment promotion.

```bash
php artisan markovable:snapshot {model}
    {--tag=}
    {--description=}
    {--storage=database}
    {--compress}
    {--encrypt}
    {--from-storage=}
    {--output-path=}
```

| Parameter | Required | Description |
| --- | --- | --- |
| `model` | Yes | Cached model key (e.g. `journeys:latest`). |
| `--tag` | No | Snapshot identifier; defaults to current timestamp. |
| `--description` | No | Human-readable summary added to metadata. |
| `--storage` | No | Snapshot destination (`database`, `file`, or `disk:name`). Defaults to `database`. |
| `--compress` | No | Gzip-compress the serialized payload before storage. |
| `--encrypt` | No | Encrypt payload using Laravel’s encrypter. Automatically base64 encodes before storage. |
| `--from-storage` | No | Storage driver where the active model is cached (defaults to configured driver). |
| `--output-path` | No | Custom file path when using `--storage=file` or `disk:name`. |

### Examples

```bash
# Create encrypted snapshot in the database
php artisan markovable:snapshot journeys:latest --tag=v2.1 --description="Post Black Friday" --compress --encrypt

# Write compressed snapshot to local disk
php artisan markovable:snapshot onboarding:staging --storage=file --compress --output-path=backups/onboarding-v3.snapshot
```

---

## `markovable:schedule`

Create or list Markovable automation schedules (training, detection, reports, snapshots).

```bash
php artisan markovable:schedule {action?}
    {--model=}
    {--frequency=daily}
    {--time=02:00}
    {--callback=}
    {--enable}
    {--disable}
    {--list}
```

| Parameter | Required | Description |
| --- | --- | --- |
| `action` | Conditional | Task to execute (`train`, `detect`, `report`, `snapshot`). Required unless `--list` is used. |
| `--model` | No | Model key the schedule targets. |
| `--frequency` | No | Interval: `hourly`, `daily`, `weekly`, `monthly`, or `cron`. Defaults to `daily`. |
| `--time` | No | Execution time: `HH:MM` for daily, `MM` minutes
| `--disable` | No | Persist schedule as disabled. |
| `--list` | No | Display a table of existing schedules instead of creating/updating one. |

### Examples

```bash
# Nightly training with follow-up report
php artisan markovable:schedule train --model=journeys --frequency=daily --time=02:00 --callback="markovable:report journeys --format=pdf"

# Hourly anomaly detection hitting a webhook
php artisan markovable:schedule detect --model=checkout --frequency=hourly --time=15 --callback="webhook:https://ops.example.com/anomalies"

# View all schedules
php artisan markovable:schedule --list
```

---

## `markovable:report`

Generate multi-channel analytics reports from cached models.

```bash
php artisan markovable:report {model}
    {--format=pdf}
    {--sections=all}
    {--period=7d}
    {--email=}
    {--webhook=}
    {--save=}
    {--template=default}
    {--from-storage=}
```

| Parameter | Required | Description |
| --- | --- | --- |
| `model` | Yes | Cached model key. |
| `--format` | No | Output format: `pdf`, `html`, `json`, `csv`, `markdown`. Defaults to `pdf`. |
| `--sections` | No | Comma-separated subset of `summary`, `predictions`, `anomalies`, `recommendations`. Use `all` (default) for every section. |
| `--period` | No | Relative window (`24h`, `7d`, `4w`, etc.). Defaults to `7d`. |
| `--email` | No | Comma-separated recipients for emailed report. |
| `--webhook` | No | Target URL for JSON payload delivery. |
| `--save` | No | Local disk path to store the generated report. |
| `--template` | No | Report template: `default` (structured data) or `summary` (executive highlights). Defaults to `default`. |
| `--from-storage` | No | Storage driver where the cached model is stored. Defaults to configured driver. |

PDF generation uses [dompdf/dompdf](https://github.com/dompdf/dompdf) under the hood. When `--format=pdf` is used, emails include the PDF as an attachment and webhook payloads encode the binary via `report_base64`. Persisting with `--save` writes the raw PDF bytes to disk. Templates tailor the layout per channel—`default` returns structured data for downstream tooling, while `summary` produces executive-ready HTML/Markdown/PDF output with key highlights.

### Examples

```bash
# Weekly PDF emailed to leadership and stored locally
php artisan markovable:report journeys:latest --format=pdf --period=7d --email="product@example.com,exec@example.com" --save=reports/journeys-weekly.pdf

# JSON summary pushed to Slack webhook
php artisan markovable:report churn:model --format=json --sections=summary,predictions --webhook="https://hooks.slack.com/..."
```

---

## `markovable:pagerank`

Calculate PageRank scores from cached baselines, graph builders, or raw adjacency files.

```bash
php artisan markovable:pagerank {baseline}
    {--graph=}
    {--graph-builder=}
    {--damping=0.85}
    {--threshold=1.0E-6}
    {--iterations=100}
    {--top=}
    {--group-by=}
    {--include-metadata}
    {--store}
    {--export=}
```

| Parameter | Required | Description |
| --- | --- | --- |
| `baseline` | No | Cache key or context identifier stored with the result metadata. Useful when comparing snapshots. |
| `--graph` | Conditional | Path to a JSON file containing an adjacency list formatted as `{ "node": { "target": weight } }`. |
| `--graph-builder` | Conditional | Container binding or class name implementing `Contracts\PageRankGraphBuilder`. |
| `--damping` | No | Damping factor between `0` and `1`. Defaults to `0.85`. |
| `--threshold` | No | Convergence tolerance (float). Defaults to `1e-6`. |
| `--iterations` | No | Maximum iterations before the solver stops (default `100`). |
| `--top` | No | Limit the number of nodes returned. |
| `--group-by` | No | Grouping strategy: `prefix`, `domain`, `segment:n`, or a container-resolved callable. |
| `--include-metadata` | No | Include metadata and embed the `PageRankResult` in the export payload. |
| `--store` | No | Persist the calculation via `PageRankSnapshot::capture(baseline, result)`. |
| `--export` | No | Write the payload to the provided path (`storage/app/pagerank.json`, etc.). |

When both `--graph` and `--graph-builder` are omitted, the command falls back to the cached baseline returned by the resolved chain context.

### Examples

```bash
# Calculate PageRank using a custom graph builder and persist snapshot
php artisan markovable:pagerank analytics::saas-authority \
    --graph-builder="App\\Markovable\\SaaSAuthorityGraph" \
    --damping=0.9 \
    --include-metadata \
    --store

# Run against a JSON adjacency file and export the results
php artisan markovable:pagerank knowledge-base \
    --graph=storage/app/graphs/knowledge.json \
    --top=25 \
    --export=storage/app/reports/knowledge-pagerank.json
```

---

## `markovable:generate`

Produce content or sequences from a trained model.

```bash
php artisan markovable:generate {model?}
    {--file=}
    {--words=100}
    {--start=}
    {--cache-key=}
    {--order=2}
    {--output=}
    {--queue}
```

Refer to the in-source PHPDoc for parameter behaviour. Typical usage:

```bash
# Generate 120-word blog outline seeded with a topic
php artisan markovable:generate --model="App\\Models\\Article" --field=body --words=120 --start="Q4 retention"
```

---

## `markovable:analyze`

Run analyzers (probabilities, predictions) on trained corpora.

```bash
php artisan markovable:analyze {profile}
    {--model=}
    {--field=}
    {--file=}
    {--order=2}
    {--predict}
    {--seed=}
    {--cache-key=}
    {--from=}
    {--to=}
    {--export=}
    {--probabilities}
    {--queue}
```

Common example:

```bash
# Predict likely next steps for an onboarding funnel
php artisan markovable:analyze navigation --cache-key=journeys:latest --predict --seed="signup email_confirmation"
```

---

## `markovable:detect`

Trigger anomaly detection pipelines against a cached baseline.

```bash
php artisan markovable:detect {baseline}
    {--current=}
    {--type=}
    {--threshold=}
    {--min-frequency=}
    {--without-persistence}
    {--without-events}
    {--order=}
    {--queue}
```

Baseline usage:

```bash
php artisan markovable:detect journeys:latest --current=storage/datasets/journeys-today.csv --type=unseen
```

---

**Tip:** For reproducible environments, prefer running commands inside Docker: `docker-compose exec app php artisan …`. The signatures are identical whether executed locally or inside the container.
