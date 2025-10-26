# SaaS Activation Blueprint

Accelerate trial-to-paid journeys by letting Markovable expose the next actions most likely to convert new teams.

## When to Reach for It
- Your onboarding wizard captures multiple milestones (invite teammates, connect integrations, publish content).
- Activation rates vary widely by segment or account size and you need guidance that reacts in real time.
- Product marketing wants automated nudges triggered from behaviour rather than manual lifecycle campaigns.

## Data Model and Capture
- Track feature interactions with `user_id`, `account_id`, `event_key`, `payload`, and timestamps.
- Enrich events with lifecycle context such as plan tier, signup cohort, and acquisition channel.

### Example Migration
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activation_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_id');
            $table->foreignId('user_id')->nullable();
            $table->string('event_key');
            $table->json('payload')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_events');
    }
};
```

### Event Emitter Example
```php
ActivationEvent::dispatch([
    'account_id' => $account->id,
    'user_id' => auth()->id(),
    'event_key' => 'integration.connected',
    'context' => [
        'segment' => 'mid-market',
        'trial_age_days' => $account->trial_age,
    ],
    'occurred_at' => now(),
]);
```

## Training Pipeline
- Group interactions by account to maintain sequence fidelity during training.
- Cache per segment so suggestions feel tailored to cohort expectations.

```php
$sequences = ActivationEvent::query()
    ->where('occurred_at', '>=', now()->subDays(90))
    ->orderBy('account_id')
    ->orderBy('occurred_at')
    ->get()
    ->groupBy('account_id')
    ->map(fn ($rows) => $rows->pluck('event_key')->all());

Markovable::chain('text')
    ->order(3)
    ->option('meta', ['segment' => 'mid-market'])
    ->train($sequences)
    ->cache('activation-paths:mid-market');
```

### Scheduled Retraining
Add to `app/Console/Kernel.php`:
```php
$schedule->command('markovable:train', [
    '--model' => ActivationEvent::class,
    '--field' => 'event_key',
    '--order' => 3,
    '--cache-key' => 'activation-paths:mid-market',
    '--storage' => 'database',
])->dailyAt('02:00');
```

## Runtime Integration
Serve next-best activation steps in-product, via email, or inside CS dashboards.

```php
$recommended = Markovable::analyze('navigation')
    ->cache('activation-paths:'.$segment)
    ->predict($latestEventKey, 3);
```

- Trigger in-product banners whenever the top suggestion differs from the user's last action.
- Pipe predictions into marketing automation platforms to send cohort-specific nudges.

## Alerts and Drift Monitoring
- Monitor activation drift by comparing today's sequences against a baseline cache (`activation-paths:q1-cohort`).
- Use `AnomalyDetected` events to notify lifecycle marketing when activation steps drop in probability.

```php
Markovable::train($recentSequences)
    ->detect('activation-paths:q1-cohort')
    ->drift()
    ->threshold(0.08)
    ->alerts(['high' => ['slack' => '#growth-alerts']])
    ->get();
```

## Experimentation Checklist
- Maintain separate cache keys for experiment arms (e.g. `activation-paths:signup-flow-A`).
- Feed activation probabilities into pricing experiments to gauge perceived value.
- Combine with `Markovable::builder('analytics')` to export sequences for BI dashboards and revenue attribution.

## Outcomes
- Higher trial-to-paid conversion without custom ML infrastructure.
- Faster iteration loops between product, marketing, and customer success teams.
- Consistent activation experiences that adapt automatically as new features roll out.
