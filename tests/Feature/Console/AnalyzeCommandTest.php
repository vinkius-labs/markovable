<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\AnalyzePatternsJob;
use VinkiusLabs\Markovable\Test\TestCase;

class AnalyzeCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('analyze_command_models');

        parent::tearDown();
    }

    public function test_analyze_command_exports_csv_with_filters(): void
    {
        $dataset = __DIR__.'/../../Fixtures/corpus.txt';
        $export = tempnam(sys_get_temp_dir(), 'Markovable-analysis');

        Markovable::train(['cached analysis seed'])->cache('analyze-fixture');

        $this->artisan('markovable:analyze', [
            'profile' => 'text',
            '--file' => $dataset,
            '--seed' => 'Laravel',
            '--predict' => 3,
            '--probabilities' => true,
            '--export' => $export,
            '--from' => '2024-01-01T00:00:00Z',
            '--to' => '2024-01-31T23:59:59Z',
            '--cache-key' => 'analyze-fixture',
        ])->assertSuccessful();

        $this->assertFileExists($export);
        $this->assertStringContainsString(';', File::get($export));

        @unlink($export);
    }

    public function test_analyze_command_can_be_queued(): void
    {
        Bus::fake();

        $dataset = __DIR__.'/../../Fixtures/corpus.txt';

        $this->artisan('markovable:analyze', [
            'profile' => 'text',
            '--file' => $dataset,
            '--queue' => true,
        ])->assertSuccessful();

        Bus::assertDispatched(AnalyzePatternsJob::class);
    }

    public function test_analyze_command_uses_cached_model(): void
    {
        Markovable::train(['analyze cached result'])->cache('analyze-cache');

        $this->artisan('markovable:analyze', [
            'profile' => 'text',
            '--cache-key' => 'analyze-cache',
            '--predict' => 2,
        ])->assertSuccessful();
    }

    public function test_analyze_command_errors_when_file_missing(): void
    {
        $missing = __DIR__.'/missing.txt';

        Markovable::train(['support missing file'])->cache('analyze-fixture');

        $this->artisan('markovable:analyze', [
            'profile' => 'text',
            '--file' => $missing,
            '--cache-key' => 'analyze-fixture',
        ])->expectsOutput('File '.$missing.' was not found.')
            ->assertSuccessful();
    }

    public function test_analyze_command_errors_when_field_missing_for_model(): void
    {
        $this->prepareModelTable();

        Markovable::train(['model missing field'])->cache('analyze-fixture');

        $this->artisan('markovable:analyze', [
            'profile' => 'text',
            '--model' => AnalyzeCommandModel::class,
            '--cache-key' => 'analyze-fixture',
        ])->expectsOutput('You must provide the --field option when analyzing a model.')
            ->assertSuccessful();
    }

    public function test_analyze_command_errors_when_model_not_found(): void
    {
        Markovable::train(['model not found'])->cache('analyze-fixture');

        $this->artisan('markovable:analyze', [
            'profile' => 'text',
            '--model' => 'Unknown\\Model',
            '--field' => 'content',
            '--cache-key' => 'analyze-fixture',
        ])->expectsOutput('Model Unknown\\Model was not found.')
            ->assertSuccessful();
    }

    private function prepareModelTable(): void
    {
        if (! Schema::hasTable('analyze_command_models')) {
            Schema::create('analyze_command_models', function (Blueprint $table) {
                $table->increments('id');
                $table->string('content');
            });
        }
    }
}

class AnalyzeCommandModel extends Model
{
    protected $table = 'analyze_command_models';

    protected $guarded = [];

    public $timestamps = false;
}
