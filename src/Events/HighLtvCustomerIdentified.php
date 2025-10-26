<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HighLtvCustomerIdentified implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    /** @var array<string, mixed> */
    public array $customer;

    public float $ltvScore;

    public float $confidence;

    /** @var array<string, mixed> */
    public array $insights;

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $insights
     */
    public function __construct(array $customer, float $ltvScore, float $confidence, array $insights = [])
    {
        $this->customer = $customer;
        $this->ltvScore = $ltvScore;
        $this->confidence = $confidence;
        $this->insights = $insights;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('markovable.predictions')];
    }

    public function broadcastAs(): string
    {
        return 'markovable.high-ltv-identified';
    }
}
