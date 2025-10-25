<?php

namespace VinkiusLabs\Markovable\Test\Feature\Jobs;

use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\Jobs\AnalyzePatternsJob;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Test\TestCase;

class AnalyzePatternsJobTest extends TestCase
{
    public function test_handle_runs_analysis_and_broadcasts_prediction(): void
    {
        Event::fake([PredictionMade::class]);

        $job = new AnalyzePatternsJob(
            ['users browse dashboard reports'],
            2,
            'navigation',
            'analysis-key',
            120,
            'cache',
            [
                'seed' => 'users',
                'limit' => 2,
                'analyzer' => 'navigation',
                'broadcast' => 'markovable-channel',
                'meta' => ['type' => 'App\\Analytics', 'id' => 1],
            ]
        );

        $job->handle($this->app->make(MarkovableManager::class));

        $this->assertIsArray($job->result);
        $this->assertNotEmpty($job->result);

        Event::assertDispatched(PredictionMade::class);
    }

    public function test_cache_method_can_switch_to_database_storage(): void
    {
        $manager = $this->app->make(MarkovableManager::class);
        $manager->chain('text')
            ->option('meta', ['type' => 'App\\Analytics', 'id' => 2])
            ->train(['persisted sample'])
            ->cache('analyze-db', storage: 'database');

        $job = new AnalyzePatternsJob(
            [],
            2,
            'text',
            'analyze-db',
            60,
            null,
            []
        );

        $job->cache('analyze-db', 60, 'database');

        $job->handle($manager);

        $this->assertIsArray($job->result);
    }
}
