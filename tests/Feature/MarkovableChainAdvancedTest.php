<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\AnalyzePatternsJob;
use VinkiusLabs\Markovable\Jobs\GenerateContentJob;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Test\TestCase;

class MarkovableChainAdvancedTest extends TestCase
{
    public function test_order_must_be_positive(): void
    {
        $this->expectException(RuntimeException::class);
        Markovable::chain('text')->order(0);
    }

    public function test_ensure_model_requires_training_or_cache(): void
    {
        $this->expectException(RuntimeException::class);
        Markovable::chain('text')->toProbabilities();
    }

    public function test_cache_persists_and_restores_model(): void
    {
        $chain = Markovable::chain('text')->train(['laravel testing is fun'])->cache('advanced-chain');

        $original = $chain->toProbabilities();

        $restored = Markovable::chain('text')->cache('advanced-chain');

        $this->assertSame($original, $restored->toProbabilities());
    }

    public function test_generate_sequence_and_json_representation(): void
    {
        $chain = Markovable::train(['framework testing coverage'])->order(2);
        $chain->explain();

        Log::shouldReceive('debug');

        $text = $chain->generate(4, ['seed' => 'framework']);

        $this->assertNotEmpty($text);
        $this->assertSame($text, $chain->getLastGenerated());

        $sequence = $chain->generateSequence(4);

        $this->assertNotEmpty($sequence);
        $this->assertSame($sequence, $chain->toArray());
        $this->assertSame(implode(' ', $sequence), $chain->getLastGenerated());
        $this->assertJson($chain->toJson());
    }

    public function test_analyze_with_probabilities_and_custom_analyzer(): void
    {
        $chain = Markovable::chain('text')->withProbabilities();

        $this->assertSame($chain, $chain->analyze('navigation'));
        $chain->analyzer('text');
        $this->assertSame('text', $chain->getAnalyzer());

        $analysis = $chain->analyze(['users explore dashboard']);

        $this->assertIsArray($analysis);
        $this->assertSame($analysis, $chain->getLastAnalysis());
    }

    public function test_predict_with_when_unless_and_broadcast(): void
    {
        Event::fake([PredictionMade::class]);

        $results = [];
        $chain = Markovable::train(['users navigate pages'])->broadcast('events')->when(true, function ($markovable) use (&$results) {
            $results[] = 'when';
            $markovable->option('meta', ['note' => 'applied']);
        })->when(false, function () use (&$results) {
            $results[] = 'when-fail';
        }, function () use (&$results) {
            $results[] = 'when-default';
        })->unless(false, function () use (&$results) {
            $results[] = 'unless';
        })->unless(true, function () use (&$results) {
            $results[] = 'unless-fail';
        }, function () use (&$results) {
            $results[] = 'unless-default';
        });

        $prediction = $chain->predict('users', 2);

        $this->assertNotEmpty($results);
        $this->assertContains('when-default', $results);
        $this->assertContains('unless-default', $results);
        $this->assertNotEmpty($prediction);
        $this->assertSame(['note' => 'applied'], $chain->getOptions()['meta']);

        Event::assertDispatched(PredictionMade::class);
    }

    public function test_async_jobs_inherit_configuration(): void
    {
        $chain = Markovable::train(['async jobs demonstrate configuration'])
            ->options(['generator' => 'text'])
            ->cache('async-chain', ttl: 120, storage: 'cache');

        $trainJob = $chain->queue();
        $generateJob = $chain->generateAsync(5, ['length' => 5]);
        $analyzeJob = $chain->analyzeAsync('async', 3, ['broadcast' => 'channel']);

        $this->assertInstanceOf(TrainMarkovableJob::class, $trainJob);
        $this->assertInstanceOf(GenerateContentJob::class, $generateJob);
        $this->assertInstanceOf(AnalyzePatternsJob::class, $analyzeJob);
    }

    public function test_export_writes_model_payload(): void
    {
        $chain = Markovable::train(['exporting markovable models']);

        $path = storage_path('app/markovable/export-test.json');
        @unlink($path);

        $chain->export($path);

        $this->assertFileExists($path);
        @unlink($path);
    }

    public function test_analyzer_falls_back_to_text_for_unknown_context(): void
    {
        $chain = Markovable::chain('custom')->train(['fallback analyzer test']);

        $analysis = $chain->analyze();

        $this->assertNotEmpty($analysis);
        $this->assertSame('text', $chain->getAnalyzer());
    }
}
