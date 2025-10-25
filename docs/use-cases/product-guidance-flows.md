# Product Guidance Flows Blueprint

Turn navigation exhaust into predictive onboarding hints that adapt with every session.

## When to Reach for It
- You have multi-step onboarding or configuration wizards with observable drop-off points.
- You track page or feature transitions and need automated suggestions for “what comes next”.
- You want probabilistic guidance without building a bespoke recommender from scratch.

## Data Model and Capture
- Log every navigation step with `user_id`, `session_id`, `from_state`, `to_state`, and contextual metadata (segment, device, experiment arm).
- Normalize the payload early; Markovable prefers concise tokens for states and attributes.

### Example Migration
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('navigation_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->foreignId('user_id')->nullable();
            $table->string('from_state');
            $table->string('to_state');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_events');
    }
};
```

### Instrumentation Snippet
```php
NavigationEvent::dispatch([
    'session_id' => (string) Str::uuid(),
    'user_id' => auth()->id(),
    'from_state' => 'pricing.welcome',
    'to_state' => 'pricing.configure-billing',
    'context' => ['segment' => 'startup'],
    'occurred_at' => now(),
]);
```

## Training Pipeline
- Use `NavigationAnalyzer` to convert transitions into probability matrices.
- Cache per segment when guidance should feel tailored.

```php
$transitions = NavigationEvent::query()
    ->select(['from_state', 'to_state'])
    ->whereJsonContains('context->segment', 'startup')
    ->orderBy('occurred_at')
    ->get()
    ->map(fn ($row) => [$row->from_state, $row->to_state]);

Markovable::analyze('navigation')
    ->train($transitions)
    ->cache('onboarding-map:startup');
```

### Scheduled Retraining
Add to `app/Console/Kernel.php`:
```php
$schedule->job(new TrainMarkovableJob(
    analyzer: 'navigation',
    cacheKey: 'onboarding-map:startup',
    payload: $transitionsSource
))->hourly();
```

Orchestrate data transforms with a custom builder:
```php
Markovable::builder('analytics')
    ->source(NavigationEvent::class)
    ->forCache('onboarding-map:startup')
    ->train();
```

## Runtime Integration
Serve predictions from controllers, Livewire components, or APIs.

```php
class OnboardingHintController
{
    public function __invoke(Request $request): JsonResponse
    {
        $nextSteps = Markovable::analyze('navigation')
            ->cache('onboarding-map:'.$request->segment)
            ->predict($request->current_state, 3);

        return response()->json([
            'current' => $request->current_state,
            'choices' => $nextSteps->map(fn ($step) => [
                'state' => $step->token,
                'probability' => round($step->probability, 3),
            ]),
        ]);
    }
}
```

Render inside Blade:
```php
@foreach($nextSteps as $step)
    <x-hint-card :title="$labels[$step->token]" :score="$step->probability" />
@endforeach
```

## Telemetry and Feedback Loops
- Subscribe to `PredictionMade` to log served hints and accepted choices.
- Emit analytics events to compare predicted vs. actual next steps.

```php
Event::listen(PredictionMade::class, function (PredictionMade $event) {
    app(Analytics::class)->record('guidance_suggestion', [
        'cache' => $event->cache,
        'input' => $event->inputToken,
        'suggested' => $event->predictions->pluck('token'),
    ]);
});
```

## Experimentation Checklist
- Segment cache keys per experiment arm (`onboarding-map:A`, `onboarding-map:B`).
- Attach probability deltas to Feature Flags to A/B test content variations.
- Combine with `Markovable::builder('analytics')` to export sequences for BI dashboards.

## Inspiration and Extensions
- Drive in-product coach marks that adapt as people explore your app.
- Suggest power-user shortcuts based on similar user cohorts.
- Prioritize documentation links for self-serve teams using the same Markovable cache.
- Feed the navigation matrix into personalization services (LaunchDarkly, GrowthBook) to influence progressive disclosure.
