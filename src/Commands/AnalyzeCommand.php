<?php

namespace VinkiusLabs\Markovable\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use VinkiusLabs\Markovable\Facades\Markovable;

class AnalyzeCommand extends Command
{
    protected $signature = 'markovable:analyze {profile : Analyzer profile name to use}
        {--model= : Fully qualified model class used for training data}
        {--field= : Model attribute or accessor to analyze}
        {--file= : Path to a newline-delimited data file}
        {--order= : Chain order to apply}
        {--predict= : Number of predictions to return}
        {--seed= : Seed value (for example a route or word)}
        {--cache-key= : Cache key of a previously trained model}
        {--from= : ISO datetime lower bound filter}
        {--to= : ISO datetime upper bound filter}
        {--export= : CSV file path for exporting results}
        {--probabilities : Include probability values in the output}
        {--queue : Dispatch the analysis to the queue}';

    protected $description = 'Analyze Markovable predictions and probabilities.';

    public function handle(): int
    {
        $profile = (string) $this->argument('profile');
        $chain = Markovable::analyze($profile);

        if ($order = $this->option('order')) {
            $chain->order((int) $order);
        }

        if ($this->option('probabilities')) {
            $chain->withProbabilities();
        }

        $dataset = $this->corpus();

        if (! empty($dataset)) {
            $chain->trainFrom($dataset);
        }

        if ($cacheKey = $this->option('cache-key')) {
            $chain->cache($cacheKey);
        }

        $seed = $this->option('seed') ?? '';
        $limit = (int) ($this->option('predict') ?? 3);
        $filters = $this->filters();

        if ($this->option('queue')) {
            Bus::dispatch($chain->options($filters)->analyzeAsync($seed, $limit));
            $this->info('Analysis dispatched to the queue.');

            return Command::SUCCESS;
        }

        $result = $chain->options($filters)->predict($seed, $limit);

        if ($export = $this->option('export')) {
            $this->exportResult($result, $export);
            $this->info("Results exported to {$export}.");
        } else {
            $this->table(
                ['Sequence', 'Probability'],
                $this->formatForTable($result)
            );
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function corpus(): array
    {
        if ($file = $this->option('file')) {
            return $this->corpusFromFile($file);
        }

        if ($model = $this->option('model')) {
            return $this->corpusFromModel($model, (string) $this->option('field'));
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        $filters = [];

        if ($from = $this->option('from')) {
            $filters['from'] = Carbon::parse($from)->toDateTimeString();
        }

        if ($to = $this->option('to')) {
            $filters['to'] = Carbon::parse($to)->toDateTimeString();
        }

        return $filters;
    }

    private function exportResult($result, string $path): void
    {
        $rows = $this->formatForTable($result);
        $csv = implode("\n", array_map(static fn ($row) => implode(';', $row), $rows));
        File::put($path, $csv);
    }

    private function formatForTable($result): array
    {
        if (! is_array($result)) {
            return [[(string) $result, '']];
        }

        if (isset($result['predictions'])) {
            return $this->formatPredictionRows($result['predictions']);
        }

        if (isset($result[0]) && is_array($result[0])) {
            return $this->formatPredictionRows($result);
        }

        return array_map(static function ($value, $probability) {
            return [
                is_array($value) ? implode(' ', $value) : (string) $value,
                is_numeric($probability) ? number_format((float) $probability, 4) : (string) $probability,
            ];
        }, array_keys($result), array_values($result));
    }

    /**
     * @param  array<int, mixed>  $predictions
     * @return array<int, array{0: string, 1: string}>
     */
    private function formatPredictionRows(array $predictions): array
    {
        return array_map(static function ($prediction) {
            if (! is_array($prediction)) {
                return [(string) $prediction, ''];
            }

            $sequence = $prediction['sequence']
                ?? $prediction['value']
                ?? $prediction['path']
                ?? (isset($prediction[0]) && ! is_array($prediction[0]) ? (string) $prediction[0] : 'n/a');

            $probability = $prediction['probability'] ?? null;

            if ($probability === null) {
                return [$sequence, ''];
            }

            return [
                $sequence,
                is_numeric($probability) ? number_format((float) $probability, 4) : (string) $probability,
            ];
        }, $predictions);
    }

    /**
     * @return array<int, string>
     */
    private function corpusFromFile(string $file): array
    {
        if (! File::exists($file)) {
            $this->error("File {$file} was not found.");

            return [];
        }

        return preg_split('/\r?\n/', File::get($file) ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return array<int, string>
     */
    private function corpusFromModel(string $model, string $field): array
    {
        if ($field === '') {
            $this->error('You must provide the --field option when analyzing a model.');

            return [];
        }

        if (! class_exists($model)) {
            $this->error("Model {$model} was not found.");

            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $model;

        return $instance
            ->newQuery()
            ->pluck($field)
            ->filter()
            ->map(static fn ($value) => (string) $value)
            ->all();
    }
}
