<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use VinkiusLabs\Markovable\Facades\Markovable;

class DetectAnomaliesCommand extends Command
{
    protected $signature = 'markovable:detect-anomalies
        {--model= : Cached baseline key to compare against}
        {--storage= : Storage driver to use}
        {--input= : Path to newline-delimited file with current sequences}
        {--threshold=0.05 : Probability threshold for unseen sequences}
        {--min-frequency=10 : Minimum frequency to flag emerging patterns}';

    protected $description = 'Detect anomalies for a cached Markovable baseline model.';

    public function handle(): int
    {
        $baselineKey = (string) $this->option('model');

        if ($baselineKey === '') {
            throw new InvalidArgumentException('The --model option is required.');
        }

        $storage = $this->option('storage');
        $dataset = $this->resolveDataset();

        if (empty($dataset)) {
            $this->error('No current dataset provided. Use --input to point to a file with sequences.');

            return self::FAILURE;
        }

        $chain = Markovable::train($dataset);

        if ($storage) {
            $chain->useStorage((string) $storage);
        }

        $detector = $chain->detect($baselineKey)
            ->unseenSequences()
            ->emergingPatterns()
            ->detectSeasonality()
            ->threshold((float) $this->option('threshold'))
            ->minimumFrequency((int) $this->option('min-frequency'));

        $results = $detector->get();

        if (empty($results)) {
            $this->info('No anomalies detected.');

            return self::SUCCESS;
        }

        $rows = collect($results)->map(function (array $result) {
            $sequence = $result['sequence'] ?? ($result['pattern'] ?? []);
            $sequence = is_array($sequence) ? implode(' → ', $sequence) : (string) $sequence;

            return [
                strtoupper((string) ($result['type'] ?? 'unknown')),
                $result['severity'] ?? 'n/a',
                $sequence,
                $result['count'] ?? 1,
            ];
        })->all();

        $this->table(['Type', 'Severity', 'Description', 'Count'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveDataset(): array
    {
        $path = $this->option('input');

        if (! $path) {
            return [];
        }

        $path = (string) $path;

        if (! is_file($path)) {
            throw new InvalidArgumentException("Dataset file [{$path}] could not be found.");
        }

        $contents = trim((string) file_get_contents($path));

        if ($contents === '') {
            return [];
        }

        if (str_starts_with($contents, '[')) {
            $decoded = json_decode($contents, true);

            if (is_array($decoded)) {
                return array_map(static fn ($item) => is_array($item) ? implode(' ', $item) : (string) $item, $decoded);
            }
        }

        return preg_split('/\r?\n/', $contents) ?: [];
    }
}
