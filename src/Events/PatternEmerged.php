<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatternEmerged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @var array<int, string> */
    public array $pattern;

    public float $growth;

    /** @var array<string, mixed> */
    public array $payload;

    /**
     * @param array<int, string> $pattern
     * @param array<string, mixed> $payload
     */
    public function __construct(array $pattern, float $growth, array $payload)
    {
        $this->pattern = $pattern;
        $this->growth = $growth;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.alerts')];
    }

    public function description(): string
    {
        $pattern = implode(' → ', $this->pattern);
        $growth = sprintf('%+.0f%%', $this->growth * 100);

        return "Pattern {$pattern} grew {$growth}";
    }
}
