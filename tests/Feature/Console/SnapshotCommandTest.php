<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Test\TestCase;

class SnapshotCommandTest extends TestCase
{
    public function test_snapshot_command_persists_snapshot_in_database(): void
    {
        $modelKey = 'snapshot-model:latest';
        $this->trainModel($modelKey);

        $this->artisan('markovable:snapshot', [
            'model' => $modelKey,
            '--storage' => 'database',
            '--tag' => 'v1',
            '--description' => 'Integration test snapshot',
        ])->assertSuccessful();

        $this->assertDatabaseHas('markovable_model_snapshots', [
            'model_key' => $modelKey,
            'tag' => 'v1',
        ]);
    }

    public function test_snapshot_command_can_store_snapshot_on_disk(): void
    {
        Storage::fake('local');

        $modelKey = 'disk-model:latest';
        $this->trainModel($modelKey);

        $this->artisan('markovable:snapshot', [
            'model' => $modelKey,
            '--storage' => 'file',
            '--tag' => 'nightly',
            '--compress' => true,
        ])->assertSuccessful();

        $expectedPath = 'markovable/snapshots/'.Str::slug($modelKey).'/'.Str::slug($modelKey.'-nightly').'.snapshot';

        Storage::disk('local')->assertExists($expectedPath);
    }

    public function test_snapshot_command_fails_when_model_missing(): void
    {
        $this->artisan('markovable:snapshot', [
            'model' => 'missing-model',
        ])->expectsOutput('Model missing-model was not found in cache storage.')
            ->assertExitCode(Command::FAILURE);
    }

    private function trainModel(string $cacheKey): void
    {
        Markovable::chain('text')
            ->order(2)
            ->cache($cacheKey)
            ->trainFrom([
                'visits checkout confirmation',
                'user journeys navigation',
            ]);
    }
}
