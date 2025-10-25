<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\ContentGenerated;
use VinkiusLabs\Markovable\Events\ModelTrained;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\GenerateContentJob;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Test\TestCase;

class MarkovableChainTest extends TestCase
{
    public function test_it_trains_and_generates_text(): void
    {
        Event::fake([ModelTrained::class, ContentGenerated::class]);

        $chain = Markovable::train([
            'laravel makes php elegant',
            'laravel elevates developer experience',
        ])->order(2);

        $text = $chain->generate(5);

        $this->assertIsString($text);
        $this->assertGreaterThanOrEqual(1, str_word_count($text));

        Event::assertDispatched(ModelTrained::class);
        Event::assertDispatched(ContentGenerated::class);
    }

    public function test_it_caches_trained_models(): void
    {
        $chain = Markovable::train('laravel markovable chains')->cache('markovable:test', ttl: 30);

        $cached = Markovable::chain('text')->cache('markovable:test');

        $this->assertNotEmpty($cached->toProbabilities());
        $this->assertSame($chain->getOrder(), $cached->getOrder());
    }

    public function test_collection_macro_trains_from_collection(): void
    {
        $collection = Collection::make(['hello world', 'hello Markovable']);

        $chain = $collection->trainMarkovable();

        $this->assertGreaterThan(0, count($chain->toProbabilities()));
    }

    public function test_queue_jobs_can_be_dispatched(): void
    {
        Bus::fake();

        $job = Markovable::train(['queue test'])->cache('queue-test')->queue();
        Bus::dispatch($job);

        Bus::assertDispatched(TrainMarkovableJob::class);
    }

    public function test_generate_async_dispatches_job(): void
    {
        Bus::fake();

        $job = Markovable::train('async generation')->generateAsync(10);
        Bus::dispatch($job);

        Bus::assertDispatched(GenerateContentJob::class);
    }

    public function test_broadcasts_prediction_events(): void
    {
        Event::fake([PredictionMade::class]);

        Markovable::train(['home products cart'])
            ->broadcast('markovable-channel')
            ->predict('home', 3);

        Event::assertDispatched(PredictionMade::class);
    }
}
