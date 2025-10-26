<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeasonalForecastReady implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public string $metric;

    /** @var array<int, array<string, mixed>> */
    public array $forecast;

    /** @var array<int, array<string, mixed>> */
    public array $patterns;

    public function __construct(string $metric, array $forecast, array $patterns = [])
    {
        $this->metric = $metric;
        $this->forecast = $forecast;
        $this->patterns = $patterns;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.predictions')];
    }

    public function broadcastAs(): string
    {
        return 'markovable.seasonal-forecast-ready';
    }
}
