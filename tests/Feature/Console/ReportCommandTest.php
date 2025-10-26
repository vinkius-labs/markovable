<?php

namespace VinkiusLabs\Markovable\Test\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    public function test_report_command_generates_pdf_summary_template_with_all_channels(): void
    {
        Http::fake();
        Storage::fake('local');

        // Suppress mail sending to avoid configuration issues
        config(['mail.default' => 'log']);

        $modelKey = 'report-model:summary';
        $this->trainModel($modelKey);
        $this->createAnomaly($modelKey);

        $this->artisan('markovable:report', [
            'model' => $modelKey,
            '--format' => 'pdf',
            '--template' => 'summary',
            '--email' => 'ops@example.com',
            '--webhook' => 'https://example.com/hooks',
            '--save' => 'reports/summary.pdf',
        ])->assertSuccessful();

        // Assert PDF was generated and saved
        Storage::disk('local')->assertExists('reports/summary.pdf');
        $pdf = Storage::disk('local')->get('reports/summary.pdf');
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf), 'PDF should contain substantial content');

        // Assert webhook received correct payload
        Http::assertSent(function ($request) use ($pdf) {
            $data = $request->data();

            if ($request->url() !== 'https://example.com/hooks') {
                return false;
            }

            if (($data['format'] ?? null) !== 'pdf' || ! isset($data['report_base64'])) {
                return false;
            }

            $decoded = base64_decode($data['report_base64'], true);

            return $decoded !== false && $decoded === $pdf;
        });
    }

    public function test_report_command_rejects_unknown_template(): void
    {
        $this->artisan('markovable:report', [
            'model' => 'any:model',
            '--template' => 'unknown',
        ])->expectsOutput('Unsupported template [unknown]. Available templates: default, summary.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_summary_template_generates_highlights_with_sparse_data(): void
    {
        Http::fake();
        Storage::fake('local');

        $modelKey = 'report-model:sparse';
        $this->trainModel($modelKey);

        $this->artisan('markovable:report', [
            'model' => $modelKey,
            '--format' => 'json',
            '--sections' => 'summary',
            '--template' => 'summary',
            '--save' => 'reports/summary.json',
        ])->assertSuccessful();

        $payload = json_decode(Storage::disk('local')->get('reports/summary.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('summary', $payload);
        $this->assertArrayHasKey('highlights', $payload);
        $this->assertArrayHasKey('Anomaly Count', $payload['highlights']);
        $this->assertSame('0', $payload['highlights']['Anomaly Count']);
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
