<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Test\TestCase;

class TrainCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('train_command_models');

        parent::tearDown();
    }

    public function test_train_command_trains_model_from_file(): void
    {
        $file = __DIR__ . '/../../Fixtures/corpus.txt';

        $this->artisan('markovable:train', [
            '--file' => $file,
            '--cache-key' => 'command-train',
        ])->assertSuccessful();

        $chain = Markovable::chain()->cache('command-train');

        $this->assertNotEmpty($chain->toProbabilities());
    }

    public function test_train_command_can_dispatch_to_queue(): void
    {
        Bus::fake();

        $file = __DIR__ . '/../../Fixtures/corpus.txt';

        $this->artisan('markovable:train', [
            '--file' => $file,
            '--cache-key' => 'queued-train',
            '--queue' => true,
        ])->assertSuccessful();

        Bus::assertDispatched(TrainMarkovableJob::class);
    }

    public function test_train_command_fails_when_no_data_provided(): void
    {
        $this->artisan('markovable:train')
            ->expectsOutput('No training data found.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_train_command_errors_when_file_missing(): void
    {
        $missing = __DIR__ . '/missing.txt';

        $this->artisan('markovable:train', [
            '--file' => $missing,
        ])->expectsOutput('File ' . $missing . ' was not found.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_train_command_errors_when_field_missing_for_model(): void
    {
        $this->prepareModelTable();

        $this->artisan('markovable:train', [
            '--model' => TrainCommandModel::class,
        ])->expectsOutput('You must provide the --field option when training from a model.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_train_command_errors_when_model_not_found(): void
    {
        $this->artisan('markovable:train', [
            '--model' => 'Unknown\\Model',
            '--field' => 'content',
        ])->expectsOutput('Model Unknown\\Model was not found.')
            ->assertExitCode(Command::FAILURE);
    }

    private function prepareModelTable(): void
    {
        if (! Schema::hasTable('train_command_models')) {
            Schema::create('train_command_models', function (Blueprint $table) {
                $table->increments('id');
                $table->string('content');
            });
        }
    }
}

class TrainCommandModel extends Model
{
    protected $table = 'train_command_models';

    protected $guarded = [];

    public $timestamps = false;
}
