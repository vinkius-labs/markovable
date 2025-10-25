<?php

namespace VinkiusLabs\Markovable\Test\Feature\Jobs;

use Illuminate\Support\Facades\Cache;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Test\TestCase;

class TrainMarkovableJobTest extends TestCase
{
    public function test_handle_trains_and_caches_model(): void
    {
        Cache::clear();

        $job = new TrainMarkovableJob(
            ['laravel makes testing pleasant'],
            2,
            'text',
            'job-key',
            60,
            'cache',
            ['meta' => ['type' => 'App\\Model', 'id' => 1]]
        );

        $job->handle($this->app->make(MarkovableManager::class));

        $chain = $this->app->make(MarkovableManager::class)->chain('text')->cache('job-key');

        $this->assertNotEmpty($chain->toProbabilities());
        $this->assertSame(2, $chain->getOrder());
    }

    public function test_cache_method_updates_properties_before_handle(): void
    {
        $job = new TrainMarkovableJob(
            ['database storage keeps models safe'],
            3,
            'text',
            null,
            null,
            null,
            ['meta' => ['type' => 'App\\Model', 'id' => 2]]
        );

        $job->cache('database-key', 120, 'database');

        $job->handle($this->app->make(MarkovableManager::class));

        $model = $this->app->make(MarkovableManager::class)
            ->chain('text')
            ->cache('database-key', storage: 'database');

        $this->assertNotEmpty($model->toProbabilities());
        $this->assertSame(3, $model->getOrder());
    }
}
