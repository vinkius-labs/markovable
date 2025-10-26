<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClusterShifted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @var array<int, array<string, mixed>> */
    public array $baseline;

    /** @var array<int, array<string, mixed>> */
    public array $current;

    /**
     * @param  array<int, array<string, mixed>>  $baseline
     * @param  array<int, array<string, mixed>>  $current
     */
    public function __construct(array $baseline, array $current)
    {
        $this->baseline = $baseline;
        $this->current = $current;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.alerts')];
    }

    public function summary(): string
    {
        return sprintf('Cluster profile changed (%d → %d)', count($this->baseline), count($this->current));
    }
}
