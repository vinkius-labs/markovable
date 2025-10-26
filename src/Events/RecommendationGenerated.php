<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecommendationGenerated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public string $customerId;

    /** @var array<int, array<string, mixed>> */
    public array $actions;

    public float $probability;

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    public function __construct(string $customerId, array $actions, float $probability)
    {
        $this->customerId = $customerId;
        $this->actions = $actions;
        $this->probability = $probability;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.predictions')];
    }

    public function broadcastAs(): string
    {
        return 'markovable.recommendation-generated';
    }
}
