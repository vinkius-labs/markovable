<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChurnRiskIdentified implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    /** @var array<string, mixed> */
    public array $customer;

    public float $churnScore;

    public string $riskLevel;

    /** @var array<int, array<string, mixed>> */
    public array $actions;

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<int, array<string, mixed>>  $actions
     */
    public function __construct(array $customer, float $churnScore, string $riskLevel, array $actions = [])
    {
        $this->customer = $customer;
        $this->churnScore = $churnScore;
        $this->riskLevel = $riskLevel;
        $this->actions = $actions;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.predictions')];
    }

    public function broadcastAs(): string
    {
        return 'markovable.churn-risk-identified';
    }
}
