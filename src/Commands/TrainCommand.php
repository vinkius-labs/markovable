<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use VinkiusLabs\Markovable\Facades\Markovable;

class TrainCommand extends Command
{
    protected $signature = 'markovable:train
        {--model= : Fully qualified model class to ingest}
        {--field= : Model attribute or accessor to extract}
        {--file= : Path to a newline-delimited text file}
        {--order=2 : Markovable order (context size)}
        {--cache-key= : Cache key for persisting the trained model}
        {--storage= : Storage driver (cache, database, file)}
        {--queue : Dispatch training as a queued job}';

    protected $description = 'Train a Markovable chain using file contents or model data.';

    public function handle(): int
    {
        $corpus = $this->corpus();

        if (empty($corpus)) {
            $this->error('No training data found.');

            return Command::FAILURE;
        }

        $order = (int) $this->option('order');
        $cacheKey = $this->option('cache-key');
        $storage = $this->option('storage');

        $chain = Markovable::chain('text')->order($order);

        if ($storage) {
            $chain->useStorage($storage);
        }

        if ($this->option('queue')) {
            $job = $chain
                ->trainFrom($corpus)
                ->cache($cacheKey ?? 'markovable:'.Str::uuid()->toString())
                ->queue();
            Bus::dispatch($job);
            $this->info('Training dispatched to the queue.');

            return Command::SUCCESS;
        }

        $chain->trainFrom($corpus);

        if ($cacheKey) {
            $chain->cache($cacheKey);
        }

        $this->info('Model trained successfully.');

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
            $this->error('You must provide the --field option when training from a model.');

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
