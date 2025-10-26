<?php

namespace VinkiusLabs\Markovable\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use VinkiusLabs\Markovable\Facades\Markovable;

class GenerateCommand extends Command
{
    protected $signature = 'markovable:generate
        {--model= : Fully qualified model class to load data from}
        {--field= : Model attribute or accessor to consume}
        {--file= : Path to a newline-delimited text file}
        {--words=100 : Number of words to generate}
        {--start= : Seed string for the generated text}
        {--cache-key= : Cache key for a previously trained model}
        {--order= : Chain order to use while generating}
        {--output= : Path to store the generated text}
        {--queue : Dispatch generation to the queue}';

    protected $description = 'Generate content from a Markovable chain.';

    public function handle(): int
    {
        $chain = Markovable::chain('text');

        if ($order = $this->option('order')) {
            $chain->order((int) $order);
        }

        if ($start = $this->option('start')) {
            $chain->startWith($start);
        }

        $dataset = $this->corpus();
        $cacheKey = $this->option('cache-key');

        if (! empty($dataset)) {
            $chain->trainFrom($dataset);
        }

        if ($cacheKey) {
            $chain->cache($cacheKey);
        }

        $words = (int) $this->option('words');

        if ($this->option('queue')) {
            Bus::dispatch($chain->generateAsync($words));
            $this->info('Generation dispatched to the queue.');

            return Command::SUCCESS;
        }

        $text = $chain->generate($words, ['seed' => $start]);

        if ($output = $this->option('output')) {
            File::put($output, $text);
            $this->info("Content saved to {$output}.");
        } else {
            $this->line($text);
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
            $this->error('You must provide the --field option when using --model.');

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
