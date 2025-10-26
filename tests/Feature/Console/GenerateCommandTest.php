<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\GenerateContentJob;
use VinkiusLabs\Markovable\Test\TestCase;

class GenerateCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('generate_command_models');

        parent::tearDown();
    }

    public function test_generate_command_writes_output_file(): void
    {
        $file = __DIR__.'/../../Fixtures/corpus.txt';
        $output = tempnam(sys_get_temp_dir(), 'Markovable');

        $this->artisan('markovable:generate', [
            '--file' => $file,
            '--words' => 10,
            '--output' => $output,
            '--start' => 'laravel',
            '--order' => 2,
        ])->assertSuccessful();

        $this->assertFileExists($output);
        $this->assertNotSame('', trim(File::get($output)));

        @unlink($output);
    }

    public function test_generate_command_can_queue_job(): void
    {
        Bus::fake();

        $file = __DIR__.'/../../Fixtures/corpus.txt';

        $this->artisan('markovable:generate', [
            '--file' => $file,
            '--words' => 5,
            '--queue' => true,
        ])->assertSuccessful();

        Bus::assertDispatched(GenerateContentJob::class);
    }

    public function test_generate_command_uses_cached_model(): void
    {
        Markovable::train(['cached generation works'])->cache('generate-cache');

        $this->artisan('markovable:generate', [
            '--cache-key' => 'generate-cache',
            '--words' => 5,
        ])->assertSuccessful();
    }

    public function test_generate_command_errors_when_file_missing(): void
    {
        $missing = __DIR__.'/missing.txt';

        Markovable::train(['generate fallback'])->cache('generate-fixture');

        $this->artisan('markovable:generate', [
            '--file' => $missing,
            '--cache-key' => 'generate-fixture',
        ])->expectsOutput('File '.$missing.' was not found.')
            ->assertSuccessful();
    }

    public function test_generate_command_errors_when_field_missing_for_model(): void
    {
        $this->prepareModelTable();

        Markovable::train(['generate fallback'])->cache('generate-fixture');

        $this->artisan('markovable:generate', [
            '--model' => GenerateCommandModel::class,
            '--cache-key' => 'generate-fixture',
        ])->expectsOutput('You must provide the --field option when using --model.')
            ->assertSuccessful();
    }

    public function test_generate_command_errors_when_model_not_found(): void
    {
        Markovable::train(['generate fallback'])->cache('generate-fixture');

        $this->artisan('markovable:generate', [
            '--model' => 'Unknown\\Model',
            '--field' => 'content',
            '--cache-key' => 'generate-fixture',
        ])->expectsOutput('Model Unknown\\Model was not found.')
            ->assertSuccessful();
    }

    private function prepareModelTable(): void
    {
        if (! Schema::hasTable('generate_command_models')) {
            Schema::create('generate_command_models', function (Blueprint $table) {
                $table->increments('id');
                $table->string('content');
            });
        }
    }
}

class GenerateCommandModel extends Model
{
    protected $table = 'generate_command_models';

    protected $guarded = [];

    public $timestamps = false;
}

namespace VinkiusLabs\Markovable\Test\Feature\Console;
