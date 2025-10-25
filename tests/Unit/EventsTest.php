<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use Illuminate\Broadcasting\Channel;
use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Events\ContentGenerated;
use VinkiusLabs\Markovable\Events\ModelTrained;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\MarkovableChain;

class EventsTest extends TestCase
{
    public function test_content_generated_event_holds_chain_and_content(): void
    {
        $chain = $this->createMock(MarkovableChain::class);
        $event = new ContentGenerated($chain, 'generated text');

        $this->assertSame($chain, $event->chain);
        $this->assertSame('generated text', $event->content);
    }

    public function test_model_trained_event_exposes_chain(): void
    {
        $chain = $this->createMock(MarkovableChain::class);
        $event = new ModelTrained($chain);

        $this->assertSame($chain, $event->chain);
    }

    public function test_prediction_made_event_broadcasts_payload(): void
    {
        $chain = $this->createConfiguredMock(MarkovableChain::class, [
            'getContext' => 'text',
        ]);

        $payload = ['predictions' => [['sequence' => 'next']]];
        $event = new PredictionMade($chain, 'seed', $payload, 'markovable-channel');

        $this->assertInstanceOf(Channel::class, $event->broadcastOn());
        $this->assertSame([
            'context' => 'text',
            'seed' => 'seed',
            'data' => $payload,
        ], $event->broadcastWith());
    }
}
