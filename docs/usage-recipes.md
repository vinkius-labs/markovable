# Usage Recipes

These recipes showcase how Markovable flows through everyday developer tasks. Copy, tailor, and extend them to fuel your own automations.

## 1. Train Models From Rich Datasets

```php
use VinkiusLabs\Markovable\Facades\Markovable;

$chain = Markovable::chain('text')
    ->order(3)
    ->trainFrom([
        'Blueprints become launchpads.',
        'Launchpads become products when teams stay curious.',
        'Curious teams measure, learn, and iterate.',
    ])
    ->cache('team-mantras', ttl: 3600);
```

## 2. Generate Content With Deterministic Seeds

```php
$output = Markovable::cache('team-mantras')
    ->generate(15, [
        'seed' => 'Curious teams',
    ]);
```

## 3. Predict Next Navigation Steps

```php
$prediction = Markovable::analyze('navigation')
    ->cache('team-mantras')
    ->predict('/dashboard', 5, [
        'from' => now()->subDay()->toIso8601String(),
        'to' => now()->toIso8601String(),
    ]);
```

## 4. Queue Intensive Workloads

```php
use Illuminate\Support\Facades\Bus;

Bus::dispatch(
    Markovable::train($yourDataset)
        ->cache('heavy-lift')
        ->queue()
);
```

## 5. Export Models for Offline Analysis

```php
Markovable::cache('team-mantras')
    ->export(storage_path('app/markovable/team-mantras.json'));
```

## 6. Plug Into Eloquent Models

Make any model self-training by adding the trait:

```php
use Illuminate\Database\Eloquent\Model;
use VinkiusLabs\Markovable\Traits\TrainsMarkovable;

class Article extends Model
{
    use TrainsMarkovable;

    protected $markovableColumns = ['title', 'summary'];
}
```

Whenever you save the model, Markovable learns from it automatically.

## 7. Broadcast Predictions

```php
use VinkiusLabs\Markovable\Events\PredictionMade;
use Illuminate\Support\Facades\Event;

Event::listen(PredictionMade::class, function ($event) {
    // Stream predictions to dashboards or websocket clients.
});

Markovable::train($dataset)
    ->broadcast('markovable.predictions')
    ->predict('growth marketing', 3);
```

Bring these fragments into your stack, mix with your own flair, and Markovable will keep pace with your imagination.
