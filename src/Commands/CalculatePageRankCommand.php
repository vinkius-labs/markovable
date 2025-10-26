<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Models\PageRankResult;
use VinkiusLabs\Markovable\Models\PageRankSnapshot;
use function collect;

class CalculatePageRankCommand extends Command
{
    protected $signature = 'markovable:pagerank {baseline : Cache key for the trained baseline}
        {--context=navigation : Context to use when loading the baseline}
        {--damping=0.85 : Damping factor (0-1)}
        {--threshold=1e-6 : Convergence threshold}
        {--iterations=100 : Maximum iterations}
        {--top=0 : Limit output to the top N nodes}
        {--group-by= : Group results by callable rule or segment alias}
        {--metadata : Include metadata in the console output}
        {--export= : Export format or file path}
        {--snapshot : Persist the result as a model snapshot}
        {--tag= : Optional snapshot tag}
        {--description= : Optional snapshot description}';

    protected $description = 'Calculate PageRank scores from a Markovable baseline model.';

    public function handle(): int
    {
        $baseline = (string) $this->argument('baseline');
        $context = (string) $this->option('context');

        $builder = Markovable::chain($context)
            ->cache($baseline)
            ->pageRank()
            ->dampingFactor((float) $this->option('damping'))
            ->convergenceThreshold((float) $this->option('threshold'))
            ->maxIterations((int) $this->option('iterations'));

        if ($this->option('metadata')) {
            $builder->includeMetadata();
        }

        $top = (int) $this->option('top');

        if ($top > 0) {
            $builder->topNodes($top);
        }

        if ($groupBy = $this->option('group-by')) {
            $builder->groupBy((string) $groupBy);
        }

        $result = $builder->result();
        $payload = $builder->calculate();

        if ($this->option('snapshot')) {
            $this->storeSnapshot($baseline, $result);
        }

        if ($export = $this->option('export')) {
            $this->export($payload, (string) $export);

            return Command::SUCCESS;
        }

        $this->renderTable($payload);

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderTable(array $payload): void
    {
        $rows = [];

        foreach ($payload['pagerank'] ?? [] as $node => $scores) {
            $rows[] = [
                'Node' => $node,
                'Raw' => number_format((float) ($scores['raw_score'] ?? 0.0), 6),
                'Normalized' => number_format((float) ($scores['normalized_score'] ?? 0.0), 2),
                'Percentile' => number_format((float) ($scores['percentile'] ?? 0.0), 1),
            ];
        }

        if ($rows === []) {
            $this->warn('No PageRank scores were produced.');

            return;
        }

        $this->table(array_keys($rows[0]), $rows);

        if (isset($payload['metadata'])) {
            $metaLines = collect($payload['metadata'])
                ->map(fn ($value, $key) => sprintf('%s: %s', Str::of($key)->headline(), is_bool($value) ? ($value ? 'true' : 'false') : (string) $value))
                ->implode(PHP_EOL);

            $this->line(PHP_EOL.'Metadata'.PHP_EOL.$metaLines);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function export(array $payload, string $export): void
    {
        $format = strtolower($export);

        if (in_array($format, ['json', 'csv'], true)) {
            $this->outputFormat($payload, $format);

            return;
        }

        $path = $export;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'json');

        if ($extension === 'csv') {
            $contents = $this->toCsv($payload);
        } else {
            $contents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $directory = dirname($path);

        if ($directory && ! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $contents);
        $this->info("PageRank data exported to {$path}.");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputFormat(array $payload, string $format): void
    {
        if ($format === 'csv') {
            $this->line($this->toCsv($payload));

            return;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toCsv(array $payload): string
    {
        $header = ['node', 'raw_score', 'normalized_score', 'percentile'];
        $rows = [implode(',', $header)];

        foreach ($payload['pagerank'] ?? [] as $node => $scores) {
            $rows[] = implode(',', [
                $this->escapeCsv($node),
                $this->escapeCsv((string) ($scores['raw_score'] ?? '0')),
                $this->escapeCsv((string) ($scores['normalized_score'] ?? '0')),
                $this->escapeCsv((string) ($scores['percentile'] ?? '0')),
            ]);
        }

        return implode(PHP_EOL, $rows);
    }

    private function escapeCsv(string $value): string
    {
        $needsQuotes = str_contains($value, ',') || str_contains($value, '"') || str_contains($value, '\n');
        $escaped = str_replace('"', '""', $value);

        return $needsQuotes ? '"'.$escaped.'"' : $escaped;
    }

    private function storeSnapshot(string $baseline, PageRankResult $result): void
    {
        $attributes = array_filter([
            'tag' => $this->option('tag') ?: null,
            'description' => $this->option('description') ?: null,
        ]);

        PageRankSnapshot::capture($baseline, $result, $attributes);
        $this->info('PageRank snapshot stored successfully.');
    }
}
