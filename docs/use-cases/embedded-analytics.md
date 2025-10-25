# Embedded Analytics Blueprint

Ship Markovable-powered insights directly to customers inside your SaaS products with multi-tenant safety and observability built in.

## Problem Signals
- Customers request predictive hints or trend summaries that you cannot deliver with static dashboards.
- You must isolate models and caches per tenant while keeping infrastructure cost predictable.
- You need explainable predictions surfaced alongside supporting evidence.

## Multi-Tenant Configuration
- Configure `DatabaseStorage` to persist chains per `tenant_id`.

```php
config(['markovable.storage' => [
    'driver' => 'database',
    'table' => 'markovable_models',
    'tenant_column' => 'tenant_id',
]]);
```

- Extend `MarkovableModel` with tenant scoping.

```php
class TenantMarkovableModel extends MarkovableModel
{
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $builder->where('tenant_id', tenant()->id);
        });
    }
}
```

## Training per Tenant
- Queue `TrainMarkovableJob` with tenant context to keep caches isolated.

```php
Tenancy::eachTenant(function (Tenant $tenant) {
    tenancy()->run($tenant, function () {
        TrainMarkovableJob::dispatch(
            analyzer: 'navigation',
            cacheKey: 'tenant:'.tenant()->id.':menu',
            payload: CustomerEvent::recentTransitions()
        );
    });
});
```

## API Surface for Insights
- Expose a versioned API delivering predictions, probability distributions, and human-readable labels.

```php
Route::middleware(['auth:sanctum', 'tenant'])
    ->get('/insights/navigation', NavigationInsightsController::class);

class NavigationInsightsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $cacheKey = sprintf('tenant:%s:menu', tenant()->id);

        $predictions = Markovable::analyze('navigation')
            ->cache($cacheKey)
            ->predict($request->input('current'), $request->integer('count', 5));

        return response()->json([
            'current' => $request->input('current'),
            'predictions' => $predictions->map(fn ($item) => [
                'token' => $item->token,
                'score' => $item->probability,
                'label' => MenuDictionary::label($item->token),
            ]),
        ]);
    }
}
```

## Frontend Widgets
- Stream predictions into SPA components via SSE or WebSockets for real-time adaptation.

```js
import { useQuery } from '@tanstack/react-query';

export function NextBestAction({ current }) {
  const { data } = useQuery(['navigation', current], () =>
    api.get('/insights/navigation', { params: { current, count: 3 } })
  );

  return (
    <Card title="Suggested next actions">
      {data?.predictions.map(action => (
        <ActionChip key={action.token} label={action.label} score={action.score} />
      ))}
    </Card>
  );
}
```

## Explainability Toolkit
- Attach metadata by enriching predictions with supporting evidence (top source sequences).

```php
$predictions = Markovable::analyze('navigation')
    ->cache($cacheKey)
    ->withContext(fn ($matrix) => $matrix->topTransitionsFor($request->input('current')))
    ->predict($request->input('current'), 5);
```

- Surface context in UI tooltips so customers understand why a recommendation appears.

## Observability and Governance
- Emit tenant-scoped metrics: prediction counts, cache freshness, divergence between recommended and chosen actions.
- Store audit trails (`PredictionMade` payloads) for compliance.

```php
Event::listen(PredictionMade::class, function (PredictionMade $event) {
    Audit::tenant(tenant()->id)->log('prediction_served', [
        'cache' => $event->cache,
        'input' => $event->inputToken,
        'outputs' => $event->predictions,
    ]);
});
```

## Monetization & Packaging Ideas
- Offer predictive navigation as a premium add-on with configurable frequency caps.
- Bundle insight exports (CSV, API) so customers can automate workflow triggers.
- Provide white-label dashboards pulling from the same Markovable caches for executive reporting.

## Inspiration for Expansion
- Extend to textual insights: generate short trend summaries (`Markovable::cache('tenant:123:trends')->generate(40)`).
- Trigger guided tours inside the app using predictions as storyboards.
- Feed customer success playbooks where CSMs preview predicted churn points before QBR calls.
