<?php

namespace VinkiusLabs\Markovable\Test\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Test\TestCase;
use VinkiusLabs\Markovable\Traits\TrainsMarkovable;

class TrainsMarkovableTest extends TestCase
{
    public function test_train_markovable_trains_and_caches_chain(): void
    {
        $model = new class extends Model
        {
            use TrainsMarkovable;

            protected $table = 'posts';

            protected $guarded = [];

            public $timestamps = false;

            public array $markovableColumns = ['body'];
        };

        $model->setConnection('testing');
        $model->exists = true;
        $model->setAttribute($model->getKeyName(), 5);
        $model->setAttribute('body', 'markovable traits are helpful');

        $expectedKey = sprintf('markovable::%s:%s', Str::slug(class_basename($model)), $model->getKey());

        Cache::forget($expectedKey);

        $model->trainMarkovable();

        $this->assertTrue(Cache::has($expectedKey));
    }

    public function test_markovable_queue_dispatches_training_job(): void
    {
        Bus::fake();

        $model = new class extends Model
        {
            use TrainsMarkovable;

            protected $table = 'articles';

            protected $guarded = [];

            public $timestamps = false;
        };

        $model->setConnection('testing');
        $model->exists = true;
        $model->setAttribute($model->getKeyName(), 7);
        $model->setAttribute('content', 'queue this content for later training');

        $model->markovableQueue(['content']);

        Bus::assertDispatched(TrainMarkovableJob::class);
    }
}
