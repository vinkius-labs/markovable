<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnomalyDetected
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $modelKey;

    /** @var array<string, mixed> */
    public array $anomaly;

    public string $severity;

    /**
     * @param array<string, mixed> $anomaly
     */
    public function __construct(string $modelKey, array $anomaly)
    {
        $this->modelKey = $modelKey;
        $this->anomaly = $anomaly;
        $this->severity = (string) ($anomaly['severity'] ?? 'info');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.alerts')];
    }

    public function summary(): string
    {
        $sequence = $this->anomaly['sequence'] ?? ($this->anomaly['pattern'] ?? []);
        $sequence = is_array($sequence) ? implode(' → ', $sequence) : (string) $sequence;
        $probability = $this->anomaly['probability'] ?? null;

        if ($probability !== null) {
            $probability = number_format((float) $probability * 100, 2) . '%';
        }

        return sprintf(
            '[%s] %s (%s)',
            strtoupper($this->severity),
            $sequence,
            $probability ?? 'probability unavailable'
        );
    }

    public function description(): string
    {
        return $this->summary();
    }
}
