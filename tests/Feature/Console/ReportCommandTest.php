<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Models\AnomalyRecord;
use VinkiusLabs\Markovable\Test\TestCase;

class ReportCommandTest extends TestCase
{
    public function test_report_command_generates_json_report_and_saves_file(): void
    {
        Http::fake();
        Storage::fake('local');

        $modelKey = 'report-model:latest';
        $this->trainModel($modelKey);
        $this->createAnomaly($modelKey);

        $this->artisan('markovable:report', [
            'model' => $modelKey,
            '--format' => 'json',
            '--sections' => 'summary,predictions,anomalies',
            '--period' => '7d',
            '--save' => 'reports/report.json',
            '--webhook' => 'https://example.com/hooks',
        ])->assertSuccessful();

        Storage::disk('local')->assertExists('reports/report.json');
        Http::assertSentCount(1);
    }

    public function test_report_command_fails_for_missing_model(): void
    {
        $this->artisan('markovable:report', [
            'model' => 'missing-model',
        ])->expectsOutput('Model missing-model was not found in cache storage.')
            ->assertExitCode(Command::FAILURE);
    }

    private function trainModel(string $key): void
    {
        Markovable::chain('text')
            ->order(2)
            ->cache($key)
            ->trainFrom([
                'user explores pricing page',
                'user views feature tour',
                'customer requests demo',
            ]);
    }

    private function createAnomaly(string $modelKey): void
    {
        AnomalyRecord::create([
            'model_key' => $modelKey,
            'type' => 'spike',
            'sequence' => ['user explores pricing page'],
            'score' => 0.87,
            'count' => 3,
            'metadata' => ['note' => 'testing'],
            'detected_at' => now()->subDay(),
        ]);
    }
}
