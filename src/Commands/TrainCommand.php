<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use VinkiusLabs\Markovable\Console\Concerns\FormatsBytes;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Support\ModelMetrics;
use VinkiusLabs\Markovable\Support\Tokenizer;

class TrainCommand extends Command
{
    use FormatsBytes;

    protected $signature = 'markovable:train
        {model : Identifier of the model to train}
        {--source=eloquent : Training data source (eloquent, csv, json, api, database)}
        {--data= : Source-specific information (class name, path, or connection:table:column)}
        {--order=2 : Markovable order (context size)}
        {--cache : Persist the trained model}
        {--storage= : Storage driver for persisting the model}
        {--incremental : Append training data to the existing cached model}
        {--async : Dispatch training as a queued job}
        {--notify= : Notification channel (log, email:recipient, webhook:url)}
        {--tag= : Version tag applied to the cached model}
        {--context=text : Model context (text, navigation, ...)}
        {--meta=* : Additional metadata key=value pairs}';

    protected $description = 'Train a Markovable chain model from multiple data sources.';

    public function handle(): int
    {
        $modelKey = (string) $this->argument('model');
        $source = strtolower((string) $this->option('source'));
        $dataOption = $this->option('data');
        $incremental = (bool) $this->option('incremental');

        if ($modelKey === '') {
            $this->error('You must provide a model identifier.');

            return Command::FAILURE;
        }

        try {
            [$dataset, $sourceLabel] = $this->loadDataset($source, $dataOption, $modelKey);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($dataset === []) {
            $this->error('No training data found.');

            return Command::FAILURE;
        }

        $cacheKey = $this->determineCacheKey($modelKey);

        if ($incremental && ! $cacheKey) {
            $this->error('Incremental training requires the model to be cached. Use --cache or provide a tag.');

            return Command::FAILURE;
        }

        $context = (string) $this->option('context') ?: 'text';
        $order = (int) $this->option('order');
        $storage = $this->option('storage');
        $meta = $this->parseMeta();

        $this->info("🚀 Training model: {$modelKey}");
        $this->info("📊 Source: {$sourceLabel}");
        $this->info('📈 Processing: '.number_format(count($dataset)).' sequences');

        $chain = Markovable::chain($context)->order($order);

        if ($storage) {
            $chain->useStorage($storage);
        }

        if ($incremental) {
            $chain->incremental();
        }

        $cacheKey ? $chain->cache($cacheKey, null, $storage) : null;

        $options = ['meta' => array_merge($meta, [
            'model_key' => $modelKey,
            'tag' => $this->option('tag'),
            'source' => $sourceLabel,
        ])];

        $chain->options($options);

        $start = microtime(true);

        if ($this->option('async')) {
            $job = new TrainMarkovableJob(
                $dataset,
                $order,
                $context,
                $cacheKey,
                null,
                $storage,
                array_merge($options, ['incremental' => $incremental])
            );

            Bus::dispatch($job);
            $this->info('⏰ Training queued asynchronously');

            return Command::SUCCESS;
        }

        $chain->trainFrom($dataset);

        $duration = microtime(true) - $start;
        $metrics = ModelMetrics::fromChain($chain);
        $sizeBytes = $this->calculateModelSize($chain, $cacheKey);

        $this->info('⏱️ Training time: '.number_format($duration, 2).'s');
        $this->info('💾 Model size: '.$this->formatBytes($sizeBytes));

        if ($cacheKey) {
            $this->info("✅ Model cached: {$cacheKey}");
        }

        $this->displayStatistics($metrics);

        if ($channel = $this->option('notify')) {
            $this->sendNotification($channel, $modelKey, $metrics, $cacheKey, $options['meta']);
        }

        $this->info('✅ Training complete!');

        return Command::SUCCESS;
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function loadDataset(string $source, ?string $configuration, string $modelKey): array
    {
        return match ($source) {
            'eloquent' => $this->loadFromEloquent($configuration),
            'csv' => $this->loadFromCsv($configuration),
            'json' => $this->loadFromJson($configuration),
            'api' => $this->loadFromApi($configuration),
            'database' => $this->loadFromDatabase($configuration),
            default => throw new InvalidArgumentException("Unsupported source [{$source}] for model {$modelKey}.")
        };
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function loadFromEloquent(?string $class): array
    {
        if (! $class) {
            throw new InvalidArgumentException('Provide --data with an Eloquent model class when using the eloquent source.');
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Eloquent model {$class} was not found.");
        }

        $records = app($class)->newQuery()->get();

        return [Tokenizer::corpus($records), $class];
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function loadFromCsv(?string $path): array
    {
        if (! $path || ! is_file($path)) {
            throw new InvalidArgumentException('Provide --data with a readable CSV file path.');
        }

        $rows = [];
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file {$path}.");
        }

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = array_filter(array_map('trim', $row), static fn ($value) => $value !== '');
            }
        } finally {
            fclose($handle);
        }

        return [Tokenizer::corpus($rows), $path];
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function loadFromJson(?string $path): array
    {
        if (! $path || ! is_file($path)) {
            throw new InvalidArgumentException('Provide --data with a readable JSON file path.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read JSON file {$path}.");
        }

        $decoded = json_decode($contents, true);

        if ($decoded === null) {
            throw new InvalidArgumentException("The JSON file {$path} is invalid.");
        }

        return [Tokenizer::corpus($decoded), $path];
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function loadFromApi(?string $url): array
    {
        if (! $url) {
            throw new InvalidArgumentException('Provide --data with an API endpoint URL.');
        }

        $response = Http::acceptJson()->get($url);

        if ($response->failed()) {
            throw new RuntimeException("API request to {$url} failed with status {$response->status()}.");
        }

        $payload = $response->json();

        return [Tokenizer::corpus($payload), $url];
    }

    /**
     * @return array{0: array<int, string>, 1: string}
     */
    private function loadFromDatabase(?string $configuration): array
    {
        if (! $configuration) {
            throw new InvalidArgumentException('Provide --data in the form connection:table:column when using the database source.');
        }

        [$connection, $table, $column] = array_pad(explode(':', $configuration, 3), 3, null);

        if (! $table || ! $column) {
            throw new InvalidArgumentException('Database source requires connection:table:column.');
        }

        $builder = DB::connection($connection ?? config('database.default'))
            ->table($table)
            ->whereNotNull($column)
            ->select($column);

        $values = $builder->pluck($column)->map(static fn ($value) => (string) $value)->all();

        return [Tokenizer::corpus($values), $configuration];
    }

    private function determineCacheKey(string $modelKey): ?string
    {
        $tag = $this->option('tag');

        if ($this->option('cache') || $tag) {
            return $modelKey.':'.($tag ?: 'latest');
        }

        return null;
    }

    private function parseMeta(): array
    {
        $meta = [];

        foreach ((array) $this->option('meta') as $entry) {
            if (is_string($entry) && str_contains($entry, '=')) {
                [$key, $value] = explode('=', $entry, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        return $meta;
    }

    private function calculateModelSize($chain, ?string $cacheKey): int
    {
        $storageName = $chain->getStorageName();
        $manager = $this->resolveManager();
        $payload = null;

        if ($cacheKey) {
            $payload = $manager->storage($storageName)->get($cacheKey);
        }

        if ($payload === null) {
            $payload = [
                'order' => $chain->getOrder(),
                'model' => $chain->toProbabilities(),
                'meta' => $chain->getModelMeta(),
                'records' => $chain->getRecords(),
                'corpus' => $chain->getCorpus(),
            ];
        }

        return strlen(serialize($payload));
    }

    private function displayStatistics(ModelMetrics $metrics): void
    {
        $this->newLine();
        $this->info('Model Statistics:');
        $this->line('  - Unique states: '.number_format($metrics->stateCount()));
        $this->line('  - Total transitions: '.number_format($metrics->transitionCount()));
        $this->line('  - Average probability: '.number_format($metrics->averageProbability(), 4));
        $this->line('  - Max probability: '.number_format($metrics->maxProbability(), 4));
        $this->line('  - Confidence score: '.number_format($metrics->confidenceScore(), 2));
    }

    private function sendNotification(string $option, string $modelKey, ModelMetrics $metrics, ?string $cacheKey, array $meta): void
    {
        [$channel, $target] = array_pad(explode(':', $option, 2), 2, null);
        $channel = strtolower($channel);

        $payload = array_merge($metrics->toArray(), [
            'model' => $modelKey,
            'cache_key' => $cacheKey,
            'meta' => $meta,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            match ($channel) {
                'log' => Log::info('Markovable training completed', $payload),
                'email' => $this->sendEmailNotification($target, $payload),
                'webhook' => $this->sendWebhookNotification($target, $payload),
                default => $this->warn("Unknown notification channel: {$channel}"),
            };
        } catch (Throwable $exception) {
            $this->warn('Failed to send notification: '.$exception->getMessage());
        }
    }

    private function sendEmailNotification(?string $recipients, array $payload): void
    {
        if (! $recipients) {
            $this->warn('Email notification skipped: no recipients provided.');

            return;
        }

        $addresses = array_filter(array_map('trim', explode(',', $recipients)));

        if ($addresses === []) {
            $this->warn('Email notification skipped: no valid recipients provided.');

            return;
        }

        $body = "Markovable model {$payload['model']} trained successfully.\n".
            'States: '.$payload['states']."\n".
            'Transitions: '.$payload['transitions']."\n".
            'Confidence: '.$payload['confidence'];

        Mail::raw($body, static function ($message) use ($addresses, $payload): void {
            $message->to($addresses);
            $message->subject('Markovable model trained: '.$payload['model']);
        });
    }

    private function sendWebhookNotification(?string $url, array $payload): void
    {
        if (! $url) {
            $this->warn('Webhook notification skipped: no URL provided.');

            return;
        }

        Http::asJson()->post($url, $payload)->throw();
    }

    private function resolveManager(): MarkovableManager
    {
        return app(MarkovableManager::class);
    }
}
