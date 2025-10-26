<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use VinkiusLabs\Markovable\Events\ContentGenerated;
use VinkiusLabs\Markovable\Events\ModelTrained;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Test\TestCase;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Jobs\GenerateContentJob;

class TrainingAndPredictionTest extends TestCase
{
    public function test_training_with_empty_data_returns_empty_model(): void
    {
        $chain = Markovable::train([]);

        $this->assertEmpty($chain->toProbabilities());
    }

    public function test_training_with_single_word(): void
    {
        Event::fake([ModelTrained::class]);

        $chain = Markovable::train(['hello'])->order(1);

        $this->assertNotEmpty($chain->toProbabilities());
        Event::assertDispatched(ModelTrained::class);
    }

    public function test_training_with_large_dataset(): void
    {
        $data = array_fill(0, 100, 'large dataset training test phrase');
        $chain = Markovable::train($data)->order(2);

        $probabilities = $chain->toProbabilities();
        $this->assertNotEmpty($probabilities);
        $this->assertGreaterThan(0, count($probabilities));
    }

    public function test_predict_without_training_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        Markovable::chain('text')->predict('test', 1);
    }

    public function test_predict_with_unknown_seed(): void
    {
        $chain = Markovable::train(['known words here'])->order(1);

        $prediction = $chain->predict('unknown', 2);

        $this->assertIsArray($prediction);
        $this->assertGreaterThan(0, count($prediction));
    }

    public function test_generate_with_different_lengths(): void
    {
        Event::fake([ContentGenerated::class]);

        $chain = Markovable::train(['generate different lengths test phrase with more words'])->order(2);

        $short = $chain->generate(1);
        $medium = $chain->generate(5);
        $long = $chain->generate(10);

        $this->assertGreaterThanOrEqual(1, str_word_count($short));
        $this->assertGreaterThanOrEqual(1, str_word_count($medium));
        $this->assertGreaterThanOrEqual(1, str_word_count($long));

        Event::assertDispatched(ContentGenerated::class, 3);
    }

    public function test_training_with_different_orders(): void
    {
        $data = ['first order test', 'second order test phrase'];

        $order1 = Markovable::train($data)->order(1);
        $order2 = Markovable::train($data)->order(2);
        $order3 = Markovable::train($data)->order(3);

        $this->assertNotEmpty($order1->toProbabilities());
        $this->assertNotEmpty($order2->toProbabilities());
        $this->assertNotEmpty($order3->toProbabilities());

        // Higher order may have different or same number
        $this->assertGreaterThanOrEqual(0, count($order2->toProbabilities()) - count($order1->toProbabilities()));
    }

    public function test_predict_with_custom_analyzer(): void
    {
        $chain = Markovable::train(['navigation test data'])->analyzer('navigation');

        $prediction = $chain->predict('navigation', 3);

        $this->assertIsArray($prediction);
        $this->assertSame('navigation', $chain->getAnalyzer());
    }

    public function test_training_with_caching_persistence(): void
    {
        $key = 'training-cache-test';

        // Train and cache
        $original = Markovable::train(['cache persistence test'])->cache($key, 60);

        // Retrieve from cache
        $cached = Markovable::chain('text')->cache($key);

        $this->assertEquals($original->toProbabilities(), $cached->toProbabilities());
        $this->assertSame($original->getOrder(), $cached->getOrder());
    }

    public function test_predict_with_seed_and_length_variations(): void
    {
        $chain = Markovable::train([
            'predict with seed variations',
            'seed helps in prediction accuracy',
            'variations make it interesting'
        ])->order(2);

        $prediction1 = $chain->predict('predict', 1);
        $prediction2 = $chain->predict('predict', 5);
        $prediction3 = $chain->predict('seed', 3);

        $this->assertGreaterThanOrEqual(1, count($prediction1));
        $this->assertGreaterThanOrEqual(1, count($prediction2));
        $this->assertGreaterThanOrEqual(1, count($prediction3));

        // All predictions should be arrays with sequence and probability
        foreach ([$prediction1, $prediction2, $prediction3] as $pred) {
            foreach ($pred as $item) {
                $this->assertArrayHasKey('sequence', $item);
                $this->assertArrayHasKey('probability', $item);
            }
        }
    }

    public function test_generate_sequence_vs_text_consistency(): void
    {
        $chain = Markovable::train(['sequence generation test'])->order(2);

        $text = $chain->generate(4);
        $sequence = $chain->generateSequence(4);

        $this->assertSame($text, implode(' ', $sequence));
        $this->assertSame($sequence, $chain->toArray());
    }

    public function test_async_training_dispatches_job(): void
    {
        Bus::fake();

        $job = Markovable::train(['async training test'])->cache('async-train')->queue();
        Bus::dispatch($job);

        Bus::assertDispatched(TrainMarkovableJob::class);
    }

    public function test_async_prediction_via_generate_async(): void
    {
        Bus::fake();

        $chain = Markovable::train(['async prediction test'])->cache('async-predict');
        $job = $chain->generateAsync(10);
        Bus::dispatch($job);

        Bus::assertDispatched(GenerateContentJob::class);
    }
}