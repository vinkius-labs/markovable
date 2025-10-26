# Artisan Command Operations Playbook

Markovable ships a family of Artisan commands that orchestrate training, analysis, reporting, and automation flows. This playbook highlights how teams combine them for day-to-day work, CI/CD guardrails, and specialty scenarios such as incident response.

## Everyday Operations

### Refresh a Cached Model Before a Planning Session
Use incremental training to merge the latest corpus updates without losing historical behaviour.

```bash
php artisan markovable:train newsroom
    --source=eloquent
    --data="App\\Models\\Article"
    --cache
    --tag=weekly-sync
    --incremental
    --meta=owner=newsroom --meta=purpose=planning
```

- Schedules a 2-gram chain by default; adjust with `--order=` when needed.
- Combined with `--notify=log`, channel performance stats land in the central logger for asynchronous review.

### Generate Drafts in an Editorial Stand-Up
Reference the cached model to produce fast outlines or prompts.

```bash
php artisan markovable:generate \
    --cache-key=newsroom:weekly-sync \
    --words=90 \
    --start="Lifecycle marketing campaign"
```

- Pair with `--output=storage/markovable/drafts.txt` to persist suggestions for asynchronous review.
- Add `--queue` when the generation job should run in the background worker.

### Run On-Demand Anomaly Analysis for Product Analytics
Spot behavioural shifts without leaving the terminal.

```bash
php artisan markovable:analyze anomalies \
    --cache-key=journey:onboarding \
    --from="2025-10-01" --to="2025-10-25" \
    --export=storage/markovable/anomalies.csv \
    --probabilities
```

- Redirect the CSV to analysts or pull it into spreadsheets for deeper exploration.
- `--queue` delegates heavy computations to the worker pool when datasets are large.

## CI/CD Pipelines

### Validate Models During Deployments
Ensure training jobs succeed inside the same container that will run production code.

```yaml
# .github/workflows/deploy.yml (excerpt)
- name: Train Markovable cache
  run: |
    php artisan markovable:train customer-insights \
      --source=database \
      --data="analytics:journeys:path" \
      --cache \
      --tag=${{ github.sha }}
- name: Snapshot model for rollback
  run: |
    php artisan markovable:snapshot customer-insights:${{ github.sha }} \
      --tag=release-${{ github.run_number }} \
      --compress --encrypt
```

- Pair snapshots with artifact uploads so the rollout can restore a known-good payload if issues occur.
- Include `php artisan markovable:report` to publish metrics into Slack or Confluence as part of release notes.

### Gate Releases on Analytics Health
Fail the pipeline when anomaly counts exceed expectations.

```bash
php artisan markovable:report customer-insights:${GITHUB_SHA} \
    --format=json \
    --sections=summary,anomalies \
    --period=24h > report.json
jq '.anomalies | length' report.json
```

- Combine with shell guards (`if [ $(jq '...') -gt 10 ]; then exit 1; fi`) to halt deployments automatically.
- Store the generated report as a build artifact for audit trails.

## Scheduled Automation

### Register Recurring Maintenance
Use the scheduler command to codify nightly retraining and weekly reports.

```bash
php artisan markovable:schedule train \
    --model=newsroom \
    --frequency=daily --time=02:00 \
    --enable

php artisan markovable:schedule report \
    --model=newsroom:weekly-sync \
    --frequency=weekly --time="Mon 08:00" \
    --callback="php artisan emails:send-newsroom-report"
```

- Invoke `php artisan markovable:schedule --list` to review and export definitions into Runbooks.
- Combine with Laravel's task runner in `app/Console/Kernel.php` to execute registered schedules.

## Incident Response & Auditing

### Freeze the Current Model State
Capture a snapshot before experimenting with new datasets.

```bash
php artisan markovable:snapshot customer-insights:latest \
    --tag=pre-hotfix \
    --storage=database \
    --description="State before drift investigation"
```

- Leverage `--from-storage=file` when caches live outside the default driver.
- Snapshot metadata retains `ModelMetrics` for quick comparisons after remediation.

### Broadcast a Rapid Health Report
Generate and deliver a markdown summary while triaging an incident.

```bash
php artisan markovable:report customer-insights:latest \
    --format=markdown \
    --sections=summary,predictions,anomalies \
    --period=7d \
    --email=oncall@example.com
```

- Attach `--webhook=https://hooks.slack.com/...` to alert chat channels with the same payload.
- Investigators can diff successive reports to confirm improvements.

## Collaboration & Knowledge Sharing

- Commit generated snapshots or reports into a `runbooks/` folder to keep institutional knowledge close to the code.
- Reference `docs/command-reference.md` from internal wikis so new teammates grasp every flag.
- Pair this playbook with `docs/technical-reference.md` when designing bespoke macros or pipelines.

## Best Practices Checklist

- **Environment parity**: Run commands via Docker or the deployed runtime (`docker-compose exec app ...`) to surface environment bugs early.
- **Version everything**: Use `--tag` and `--meta` consistently so snapshots, reports, and logs align with releases.
- **Automate notifications**: Prefer `--notify`, `--email`, or `--webhook` to reduce manual status checks.
- **Monitor drift**: Schedule anomaly detection weekly and snapshot before major data migrations.
- **Document outcomes**: Store CLI transcripts alongside sprint notes for reproducibility.
