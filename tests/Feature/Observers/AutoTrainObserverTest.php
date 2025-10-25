<?php

namespace VinkiusLabs\Markovable\Test\Feature\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Observers\AutoTrainObserver;
use VinkiusLabs\Markovable\Test\TestCase;

class AutoTrainObserverTest extends TestCase
{
    public function test_saved_trains_and_caches_model_based_on_markovable_columns(): void
    {
        $model = new class extends Model {
            protected $table = 'virtual_models';
            protected $guarded = [];
            public $timestamps = false;
            public array $markovableColumns = ['content'];
        };

        $model->setConnection('testing');
        $model->exists = true;
        $model->setAttribute($model->getKeyName(), 1);
        $model->setAttribute('content', 'auto training works');

        $expectedKey = sprintf('markovable::%s:%s', Str::slug((new \ReflectionClass($model))->getShortName()), $model->getKey());

        Cache::forget($expectedKey);

        $observer = new AutoTrainObserver();
        $observer->saved($model);

        $this->assertTrue(Cache::has($expectedKey));
    }

    public function test_saved_dispatches_job_when_queue_is_enabled(): void
    {
        Bus::fake();

        config()->set('markovable.queue', [
            'enabled' => true,
            'connection' => 'database',
            'queue' => 'markovable-jobs',
        ]);

        $model = new class extends Model {
            protected $table = 'documents';
            protected $guarded = [];
            public $timestamps = false;
        };

        $model->setConnection('testing');
        $model->exists = true;
        $model->setAttribute($model->getKeyName(), 42);
        $model->setAttribute('body', 'queued training kicks in');

        $observer = new AutoTrainObserver('body');
        $observer->saved($model);

        Bus::assertDispatched(TrainMarkovableJob::class);

        config()->set('markovable.queue.enabled', false);
    }
}
