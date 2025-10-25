<?php

namespace VinkiusLabs\Markovable;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;
use VinkiusLabs\Markovable\Contracts\Analyzer as AnalyzerContract;
use VinkiusLabs\Markovable\Contracts\Generator as GeneratorContract;
use VinkiusLabs\Markovable\Contracts\Storage as StorageContract;
use VinkiusLabs\Markovable\Events\ContentGenerated;
use VinkiusLabs\Markovable\Events\ModelTrained;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\Jobs\AnalyzePatternsJob;
use VinkiusLabs\Markovable\Jobs\GenerateContentJob;
use VinkiusLabs\Markovable\Jobs\TrainMarkovableJob;
use VinkiusLabs\Markovable\Support\Tokenizer;

class MarkovableChain
{
    use Macroable;

    private MarkovableManager $manager;

    private string $context;

    private int $order;

    /** @var array<int, string> */
    private array $corpus = [];

    /** @var array<string, array<string, float>> */
    private array $model = [];

    /** @var array<int, string> */
    private array $initialStates = [];

    private bool $trained = false;

    private ?string $cacheKey = null;

    private ?int $cacheTtl = null;

    private ?string $storageName = null;

    private ?string $analyzerName = null;

    private bool $includeProbabilities = false;

    private bool $debug = false;

    private ?string $broadcastChannel = null;

    private ?string $lastGenerated = null;

    /** @var array<string, mixed> */
    private array $lastAnalysis = [];

    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(MarkovableManager $manager, string $context = 'text')
    {
        $this->manager = $manager;
        $this->context = $context;
        $this->order = (int) ($this->manager->config('default_order', 2) ?: 2);
        $this->cacheTtl = $this->manager->config('cache.ttl');
        $this->storageName = $this->manager->config('storage', 'cache');
    }

    public function context(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function order(int $order): self
    {
        if ($order < 1) {
            throw new RuntimeException('Order must be at least 1.');
        }

        $this->order = $order;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function train($value): self
    {
        $this->corpus = Tokenizer::corpus($value);
        $this->buildModel();
        $this->persistModelIfNeeded();

        Event::dispatch(new ModelTrained($this));

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function trainFrom($value): self
    {
        return $this->train($value);
    }

    public function useStorage(string $name): self
    {
        $this->storageName = $name;

        return $this;
    }

    public function cache(string $key, ?int $ttl = null, ?string $storage = null): self
    {
        $this->cacheKey = $key;
        $this->cacheTtl = $ttl ?? $this->cacheTtl;

        if ($storage) {
            $this->useStorage($storage);
        }

        if ($this->trained) {
            $this->persistModelIfNeeded();
        }

        return $this;
    }

    public function generate(?int $words = null, array $options = []): string
    {
        $this->ensureModel();

        $length = $words ?? (int) ($this->manager->config('generate_default_words', 100) ?: 100);
        $generator = $this->resolveGenerator();
        $result = $generator->generate($this->model, $length, array_merge($this->options, $options, [
            'initial_states' => $this->initialStates,
            'order' => $this->order,
        ]));
        $this->lastGenerated = $result;

        if ($this->debug) {
            Log::debug('Markovable generated content', [
                'context' => $this->context,
                'length' => $length,
                'seed' => $options['seed'] ?? null,
            ]);
        }

        Event::dispatch(new ContentGenerated($this, $result));

        return $result;
    }

    public function generateSequence(?int $length = null): array
    {
        $output = $this->generate($length ?? (int) ($this->manager->config('generate_default_words', 100) ?: 100));

        return preg_split('/\s+/u', trim($output)) ?: [];
    }

    public function analyze($subject = null, array $options = [])
    {
        if (is_string($subject) && $this->manager->hasAnalyzer($subject)) {
            $this->analyzerName = $subject;

            return $this;
        }

        if ($subject !== null && empty($this->corpus)) {
            $this->train($subject);
        }

        $this->ensureModel();

        $analyzer = $this->resolveAnalyzer();
        $result = $analyzer->analyze($this, $this->model, array_merge($this->options, $options, [
            'order' => $this->order,
            'initial_states' => $this->initialStates,
            'with_probabilities' => $this->includeProbabilities,
        ]));
        $this->lastAnalysis = $result;

        return $this->includeProbabilities ? $result : ($result['predictions'] ?? $result);
    }

    public function predict(string $seed, int $limit = 3, array $options = [])
    {
        $this->ensureModel();

        $analyzer = $this->resolveAnalyzer();
        $result = $analyzer->analyze($this, $this->model, array_merge($this->options, $options, [
            'seed' => $seed,
            'limit' => $limit,
            'with_probabilities' => $this->includeProbabilities,
            'order' => $this->order,
            'initial_states' => $this->initialStates,
        ]));

        $this->lastAnalysis = $result;

        if ($this->broadcastChannel) {
            Event::dispatch(new PredictionMade($this, $seed, $result, $this->broadcastChannel));
        }

        if ($this->debug) {
            Log::debug('Markovable prediction computed', [
                'context' => $this->context,
                'seed' => $seed,
                'limit' => $limit,
            ]);
        }

        return $this->includeProbabilities ? $result : ($result['predictions'] ?? $result);
    }

    public function withProbabilities(bool $flag = true): self
    {
        $this->includeProbabilities = $flag;

        return $this;
    }

    public function debug(bool $flag = true): self
    {
        $this->debug = $flag;

        return $this;
    }

    public function explain(): self
    {
        return $this->debug(true);
    }

    public function broadcast(string $channel): self
    {
        $this->broadcastChannel = $channel;

        return $this;
    }

    public function queue(): TrainMarkovableJob
    {
        return new TrainMarkovableJob(
            $this->corpus,
            $this->order,
            $this->context,
            $this->cacheKey,
            $this->cacheTtl,
            $this->storageName,
            $this->options
        );
    }

    public function generateAsync(?int $words = null, array $options = []): GenerateContentJob
    {
        $length = $words ?? (int) ($this->manager->config('generate_default_words', 100) ?: 100);

        return new GenerateContentJob(
            $this->corpus,
            $this->order,
            $this->context,
            $this->cacheKey,
            $this->cacheTtl,
            $this->storageName,
            array_merge($this->options, $options, ['length' => $length])
        );
    }

    public function analyzeAsync(string $seed, int $limit = 3, array $options = []): AnalyzePatternsJob
    {
        return new AnalyzePatternsJob(
            $this->corpus,
            $this->order,
            $this->context,
            $this->cacheKey,
            $this->cacheTtl,
            $this->storageName,
            array_merge($this->options, $options, [
                'seed' => $seed,
                'limit' => $limit,
                'analyzer' => $this->getAnalyzer(),
                'with_probabilities' => $this->includeProbabilities,
            ])
        );
    }

    public function toArray(): array
    {
        if ($this->lastGenerated === null) {
            return [];
        }

        return preg_split('/\s+/u', trim($this->lastGenerated)) ?: [];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '[]';
    }

    public function toProbabilities(): array
    {
        $this->ensureModel();

        return $this->model;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function option(string $key, $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function when($value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    public function unless($value, callable $callback, ?callable $default = null): self
    {
        return $this->when(! $value, $callback, $default);
    }

    public function getLastGenerated(): ?string
    {
        return $this->lastGenerated;
    }

    public function getLastAnalysis(): array
    {
        return $this->lastAnalysis;
    }

    public function export(string $path): self
    {
        $this->ensureModel();

        $payload = [
            'order' => $this->order,
            'model' => $this->model,
            'initial_states' => $this->initialStates,
        ];

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));

        return $this;
    }

    public function dd(): void
    {
        if (! function_exists('dd')) {
            throw new RuntimeException('The dd helper is not available.');
        }

        dd($this->toArray());
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getAnalyzer(): string
    {
        return $this->analyzerName ?? 'text';
    }

    public function setAnalyzer(string $name): self
    {
        $this->analyzerName = $name;

        return $this;
    }

    public function analyzer(string $name): self
    {
        return $this->setAnalyzer($name);
    }

    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    private function ensureModel(): void
    {
        if ($this->trained) {
            return;
        }

        if ($this->cacheKey) {
            $storage = $this->resolveStorage();
            $payload = $storage->get($this->cacheKey);

            if ($payload) {
                $this->order = (int) ($payload['order'] ?? $this->order);
                $this->model = $payload['model'] ?? [];
                $this->initialStates = $payload['initial_states'] ?? [];
                $this->context = $payload['context'] ?? $this->context;
                $this->trained = true;

                return;
            }
        }

        if (! empty($this->corpus)) {
            $this->buildModel();

            return;
        }

        throw new RuntimeException('No trained model available. Train or load a cached model first.');
    }

    private function buildModel(): void
    {
        $this->model = [];
        $this->initialStates = [];
        $this->trained = false;

        foreach ($this->corpus as $index => $record) {
            $tokens = Tokenizer::tokenize($record);

            if (empty($tokens)) {
                continue;
            }

            $tokens = array_merge($this->startTokens(), $tokens, ['__END__']);

            if (! in_array(implode(' ', array_slice($tokens, 0, $this->order)), $this->initialStates, true)) {
                $this->initialStates[] = implode(' ', array_slice($tokens, 0, $this->order));
            }

            $totalTokens = count($tokens);

            for ($i = $this->order; $i < $totalTokens; $i++) {
                $prefixTokens = array_slice($tokens, $i - $this->order, $this->order);
                $prefix = implode(' ', $prefixTokens);
                $next = $tokens[$i];

                if (! isset($this->model[$prefix])) {
                    $this->model[$prefix] = [];
                }

                $this->model[$prefix][$next] = ($this->model[$prefix][$next] ?? 0) + 1;
            }
        }

        foreach ($this->model as $prefix => $counts) {
            $sum = array_sum($counts);

            if ($sum <= 0) {
                continue;
            }

            foreach ($counts as $token => $count) {
                $this->model[$prefix][$token] = $count / $sum;
            }
        }

        $this->trained = true;

        if ($this->debug) {
            Log::debug('Markovable model built', [
                'context' => $this->context,
                'states' => count($this->model),
                'order' => $this->order,
            ]);
        }
    }

    private function persistModelIfNeeded(): void
    {
        if (! $this->cacheKey) {
            return;
        }

        $storage = $this->resolveStorage();
        $storage->put($this->cacheKey, [
            'order' => $this->order,
            'model' => $this->model,
            'initial_states' => $this->initialStates,
            'context' => $this->context,
            'meta' => $this->options['meta'] ?? [],
        ], $this->cacheTtl);
    }

    private function resolveStorage(): StorageContract
    {
        return $this->manager->storage($this->storageName);
    }

    private function resolveAnalyzer(): AnalyzerContract
    {
        $name = $this->analyzerName ?? $this->context;

        if (! $this->manager->hasAnalyzer($name)) {
            $name = 'text';
        }

        $this->analyzerName = $name;

        return $this->manager->analyzer($name);
    }

    private function resolveGenerator(): GeneratorContract
    {
        $name = $this->options['generator'] ?? ($this->context === 'text' ? 'text' : 'sequence');

        return $this->manager->generator($name);
    }

    /**
     * @return array<int, string>
     */
    private function startTokens(): array
    {
        return array_fill(0, $this->order, '__START__');
    }
}
