<?php

namespace VinkiusLabs\Markovable\Test\Feature\Jobs;

use Illuminate\Support\Facades\Event;
use VinkiusLabs\Markovable\Events\ContentGenerated;
use VinkiusLabs\Markovable\Jobs\GenerateContentJob;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Test\TestCase;

class GenerateContentJobTest extends TestCase
{
    public function test_handle_generates_text_and_dispatches_event(): void
    {
        Event::fake([ContentGenerated::class]);

        $job = new GenerateContentJob(
            ['testing asynchronous generation'],
            2,
            'text',
            'generate-key',
            300,
            'cache',
            ['length' => 4]
        );

        $job->handle($this->app->make(MarkovableManager::class));

        $this->assertNotNull($job->result);
        $this->assertGreaterThan(0, str_word_count((string) $job->result));

        Event::assertDispatched(ContentGenerated::class);
    }

    public function test_cache_method_switches_storage_driver(): void
    {
        $job = new GenerateContentJob(
            ['file storage keeps results'],
            2,
            'text',
            null,
            null,
            null,
            ['length' => 3]
        );

        $job->cache('file-job', 60, 'file');

        $job->handle($this->app->make(MarkovableManager::class));

        $path = storage_path('app/markovable/file-job.json');
        $this->assertFileExists($path);
    }
}
