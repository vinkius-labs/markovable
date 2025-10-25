<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\Markovable\MarkovableChain;

class PredictionMade implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public MarkovableChain $chain;

    public string $seed;

    /** @var array<string, mixed> */
    public array $payload;

    private string $channel;

    public function __construct(MarkovableChain $chain, string $seed, array $payload, string $channel)
    {
        $this->chain = $chain;
        $this->seed = $seed;
        $this->payload = $payload;
        $this->channel = $channel;
    }

    public function broadcastOn(): Channel
    {
        return new Channel($this->channel);
    }

    public function broadcastWith(): array
    {
        return [
            'context' => $this->chain->getContext(),
            'seed' => $this->seed,
            'data' => $this->payload,
        ];
    }
}



