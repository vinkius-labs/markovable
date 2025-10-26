<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Test\TestCase;

class TrainCommandTest extends TestCase
{
    public function test_train_command_trains_and_caches_from_csv(): void
    {
        $file = __DIR__.'/../../Fixtures/corpus.txt';
        $modelKey = 'user-navigation';

        $this->artisan('markovable:train', [
            'model' => $modelKey,
            '--source' => 'csv',
            '--data' => $file,
            '--cache' => true,
        ])->assertSuccessful();

        $chain = Markovable::chain('text')->cache($modelKey.':latest');

        $this->assertNotEmpty($chain->toProbabilities());
    }

    public function test_train_command_dispatches_async_job(): void
    {
        Bus::fake();

        $file = __DIR__.'/../../Fixtures/corpus.txt';

        $this->artisan('markovable:train', [
            'model' => 'queued-model',
            '--source' => 'csv',
            '--data' => $file,
            '--cache' => true,
            '--async' => true,
        ])->assertSuccessful();

        Bus::assertDispatched(TrainMarkovableJob::class);
    }

    public function test_train_command_supports_incremental_training(): void
    {
        $modelKey = 'incremental-model';
        $file = __DIR__.'/../../Fixtures/corpus.txt';

        $this->artisan('markovable:train', [
            'model' => $modelKey,
            '--source' => 'csv',
            '--data' => $file,
            '--cache' => true,
        ])->assertSuccessful();

        $extraData = $this->createTemporaryCsv(['extra sequence one', 'another sequence two']);

        $this->artisan('markovable:train', [
            'model' => $modelKey,
            '--source' => 'csv',
            '--data' => $extraData,
            '--cache' => true,
            '--incremental' => true,
        ])->assertSuccessful();

        $chain = Markovable::chain('text')->cache($modelKey.':latest');
        $frequencies = $chain->getSequenceFrequencies();

        $this->assertArrayHasKey('extra sequence one', $frequencies);
        $this->assertArrayHasKey('another sequence two', $frequencies);
    }

    public function test_train_command_requires_data(): void
    {
        $this->artisan('markovable:train', [
            'model' => 'no-data',
        ])->expectsOutput('Provide --data with an Eloquent model class when using the eloquent source.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_train_command_validates_missing_source_file(): void
    {
        $missing = Str::uuid().'.csv';

        $this->artisan('markovable:train', [
            'model' => 'missing-file',
            '--source' => 'csv',
            '--data' => $missing,
        ])->expectsOutput('Provide --data with a readable CSV file path.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_train_command_sends_log_notification(): void
    {
        Mail::fake();

        $file = __DIR__.'/../../Fixtures/corpus.txt';

        $this->artisan('markovable:train', [
            'model' => 'notify-model',
            '--source' => 'csv',
            '--data' => $file,
            '--cache' => true,
            '--notify' => 'log',
        ])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_incremental_training_without_cache_fails(): void
    {
        $file = __DIR__.'/../../Fixtures/corpus.txt';

        $this->artisan('markovable:train', [
            'model' => 'no-cache',
            '--source' => 'csv',
            '--data' => $file,
            '--incremental' => true,
        ])->expectsOutput('Incremental training requires the model to be cached. Use --cache or provide a tag.')
            ->assertExitCode(Command::FAILURE);
    }

    private function createTemporaryCsv(array $rows): string
    {
        $path = sys_get_temp_dir().'/markovable_'.Str::uuid().'.csv';
        file_put_contents($path, implode(PHP_EOL, $rows));

        return $path;
    }
}
