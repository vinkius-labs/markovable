<?php

namespace VinkiusLabs\Markovable\Commands;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use VinkiusLabs\Markovable\Console\Concerns\FormatsBytes;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Models\AnomalyRecord;
use VinkiusLabs\Markovable\Support\ModelMetrics;

class ReportCommand extends Command
{
    use FormatsBytes;

    protected $signature = 'markovable:report
        {model : Model key to generate the report from}
        {--format=pdf : Report format (pdf, html, json, csv, markdown)}
        {--sections=all : Comma separated sections (summary,predictions,anomalies,recommendations)}
        {--period=7d : Reporting period (e.g. 24h, 7d, 4w)}
        {--email= : Comma separated list of recipients}
        {--webhook= : Webhook URL to deliver the report}
        {--save= : Persist the report to the local storage disk}
        {--template=default : Report template (reserved for future use)}
        {--from-storage= : Storage driver where the model is cached}';

    protected $description = 'Generate Markovable analytics reports.';

    public function handle(): int
    {
        $modelKey = (string) $this->argument('model');
        $format = strtolower((string) $this->option('format'));
        $sections = $this->parseSections((string) $this->option('sections'));
        $period = $this->parsePeriod((string) $this->option('period'));
        $storage = $this->option('from-storage') ?: config('markovable.storage', 'cache');

        if ($modelKey === '') {
            $this->error('A model key is required.');

            return Command::FAILURE;
        }

        $manager = $this->resolveManager();
        $payload = $manager->storage($storage)->get($modelKey);

        if ($payload === null) {
            $this->error("Model {$modelKey} was not found in {$storage} storage.");

            return Command::FAILURE;
        }

        $context = $payload['context'] ?? 'text';
        $chain = Markovable::chain($context)->useStorage($storage)->cache($modelKey);
        $chain->toProbabilities();
        $metrics = ModelMetrics::fromChain($chain);

        $data = $this->collectReportData($modelKey, $metrics, $chain->getSequenceFrequencies(), $period);
        $filtered = $this->filterSections($data, $sections);

        $this->info("📊 Generating report: {$modelKey}");
        $this->info('📅 Period: '.$period['description']);
        $this->info('📄 Format: '.strtoupper($format));
        $this->line('');
        $this->line('Building report...');

        foreach (array_keys($filtered) as $section) {
            $this->info('✅ '.Str::headline($section).' section: Complete');
        }

        try {
            $report = $this->renderReport($filtered, $format);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->handleReportPersistence($report, $format);
        $this->handleReportDelivery($report, $format, $modelKey);
        $this->displayReportStatistics($report, $filtered);

        return Command::SUCCESS;
    }

    private function collectReportData(string $modelKey, ModelMetrics $metrics, array $frequencies, array $period): array
    {
        return [
            'summary' => $this->buildSummary($modelKey, $metrics, $period),
            'predictions' => $this->buildPredictions($frequencies),
            'anomalies' => $this->buildAnomalies($modelKey, $period),
            'recommendations' => $this->buildRecommendations($metrics),
        ];
    }

    private function buildSummary(string $modelKey, ModelMetrics $metrics, array $period): array
    {
        return [
            'model' => $modelKey,
            'period' => $period['description'],
            'generated_at' => now()->toDateTimeString(),
            'states' => $metrics->stateCount(),
            'transitions' => $metrics->transitionCount(),
            'sequence_count' => $metrics->sequenceCount(),
            'confidence' => $metrics->confidenceScore(),
            'average_probability' => $metrics->averageProbability(),
        ];
    }

    private function buildPredictions(array $frequencies): array
    {
        arsort($frequencies);
        $top = array_slice($frequencies, 0, 5, true);

        $total = array_sum($frequencies) ?: 1;

        $predictions = [];

        foreach ($top as $sequence => $count) {
            $predictions[] = [
                'sequence' => $sequence,
                'probability' => round($count / $total, 4),
                'count' => $count,
            ];
        }

        return $predictions;
    }

    private function buildAnomalies(string $modelKey, array $period): array
    {
        return AnomalyRecord::query()
            ->where('model_key', $modelKey)
            ->whereBetween('detected_at', [$period['start'], $period['end']])
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->map(static function (AnomalyRecord $record) {
                return [
                    'type' => $record->type,
                    'score' => $record->score,
                    'detected_at' => optional($record->detected_at)->toDateTimeString(),
                    'metadata' => $record->metadata,
                ];
            })
            ->all();
    }

    private function buildRecommendations(ModelMetrics $metrics): array
    {
        $recommendations = [];

        if ($metrics->confidenceScore() < 0.5) {
            $recommendations[] = 'Confidence is below 0.5 – consider providing additional training data.';
        }

        if ($metrics->stateCount() > 1000) {
            $recommendations[] = 'High state count detected – snapshot the model before deploying major updates.';
        }

        if ($metrics->averageProbability() < 0.05) {
            $recommendations[] = 'Average transition probability is low – review data quality for sparsity.';
        }

        return $recommendations === [] ? ['Model is healthy. No immediate action required.'] : $recommendations;
    }

    private function renderReport(array $data, string $format): string
    {
        return match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'markdown' => $this->renderMarkdown($data),
            'html' => $this->renderHtml($data),
            'csv' => $this->renderCsv($data),
            'pdf' => $this->renderPdf($data),
            default => throw new InvalidArgumentException("Unsupported report format [{$format}]."),
        };
    }

    private function renderMarkdown(array $data): string
    {
        $sections = [];

        foreach ($data as $section => $content) {
            $sections[] = '## '.Str::headline($section);
            $sections[] = '```json';
            $sections[] = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $sections[] = '```';
            $sections[] = '';
        }

        return implode("\n", $sections);
    }

    private function renderHtml(array $data): string
    {
        $html = '<html><head><title>Markovable Report</title></head><body>';

        foreach ($data as $section => $content) {
            $html .= '<h2>'.e(Str::headline($section)).'</h2>';
            $html .= '<pre>'.e(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'</pre>';
        }

        return $html.'</body></html>';
    }

    private function renderCsv(array $data): string
    {
        $rows = [
            ['section', 'key', 'value'],
        ];

        foreach ($data as $section => $content) {
            if (! is_array($content)) {
                $rows[] = [$section, 'value', (string) $content];

                continue;
            }

            foreach ($content as $key => $value) {
                $rows[] = [
                    $section,
                    is_string($key) ? $key : (string) $key,
                    is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $stream = fopen('php://temp', 'rb+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    private function renderPdf(array $data): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderHtml($data));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function handleReportPersistence(string $report, string $format): void
    {
        if (! $path = $this->option('save')) {
            return;
        }

        Storage::disk('local')->put($path, $report);
        $this->info('💾 Saved to: '.$path);
    }

    private function handleReportDelivery(string $report, string $format, string $modelKey): void
    {
        if ($emails = $this->option('email')) {
            $recipients = array_filter(array_map('trim', explode(',', $emails)));

            if ($recipients !== []) {
                try {
                    if ($format === 'pdf') {
                        Mail::send([], [], function ($message) use ($recipients, $modelKey, $report): void {
                            $message->to($recipients)
                                ->subject('Markovable report: '.$modelKey.' (pdf)')
                                ->setBody('Markovable report generated by markovable:report is attached.', 'text/plain')
                                ->attachData($report, 'markovable-report.pdf', [
                                    'mime' => 'application/pdf',
                                ]);
                        });
                    } else {
                        Mail::raw($report, static function ($message) use ($recipients, $modelKey, $format): void {
                            $message->to($recipients)->subject('Markovable report: '.$modelKey.' ('.$format.')');
                        });
                    }

                    $this->info('📧 Sending to: '.implode(', ', $recipients));
                    $this->info('✅ Email sent successfully');
                } catch (Throwable $exception) {
                    $this->warn('Email delivery failed: '.$exception->getMessage());
                }
            }
        }

        if ($webhook = $this->option('webhook')) {
            try {
                $payload = [
                    'format' => $format,
                    'model' => $modelKey,
                ];

                if ($format === 'pdf') {
                    $payload['report_base64'] = base64_encode($report);
                } else {
                    $payload['report'] = $report;
                }

                Http::asJson()->post($webhook, $payload)->throw();

                $this->info('🔗 Webhook delivered to: '.$webhook);
            } catch (Throwable $exception) {
                $this->warn('Webhook delivery failed: '.$exception->getMessage());
            }
        }
    }

    private function displayReportStatistics(string $report, array $sections): void
    {
        $this->newLine();
        $this->info('Report Statistics:');
        $this->line('  - Sections included: '.count($sections));
        $this->line('  - Characters: '.number_format(strlen($report)));
        $this->line('  - Estimated size: '.$this->formatBytes(strlen($report)));
    }

    private function parseSections(string $input): array
    {
        if ($input === '' || strtolower($input) === 'all') {
            return ['summary', 'predictions', 'anomalies', 'recommendations'];
        }

        return array_values(array_unique(array_filter(array_map(static fn ($section) => strtolower(trim($section)), explode(',', $input)))));
    }

    private function filterSections(array $data, array $sections): array
    {
        $allowed = $sections === [] ? array_keys($data) : $sections;

        $filtered = [];

        foreach ($allowed as $section) {
            if (array_key_exists($section, $data)) {
                $filtered[$section] = $data[$section];
            }
        }

        return $filtered;
    }

    private function parsePeriod(string $value): array
    {
        $end = Carbon::now();
        $amount = 7;
        $unitLabel = 'days';

        if (preg_match('/^(\d+)([hdw])$/', strtolower($value), $matches)) {
            [$all, $amount, $unit] = $matches;
            $amount = (int) $amount;

            $start = match ($unit) {
                'h' => $end->copy()->subHours($amount),
                'd' => $end->copy()->subDays($amount),
                'w' => $end->copy()->subWeeks($amount),
            };

            $unitLabel = match ($unit) {
                'h' => $amount === 1 ? 'hour' : 'hours',
                'd' => $amount === 1 ? 'day' : 'days',
                'w' => $amount === 1 ? 'week' : 'weeks',
            };
        } else {
            $start = $end->copy()->subDays(7);
            $amount = 7;
            $unitLabel = 'days';
        }

        return [
            'start' => $start,
            'end' => $end,
            'description' => sprintf(
                'Last %d %s (%s to %s)',
                $amount,
                $unitLabel,
                $start->toDateTimeString(),
                $end->toDateTimeString()
            ),
        ];
    }

    private function resolveManager(): MarkovableManager
    {
        return app(MarkovableManager::class);
    }
}
