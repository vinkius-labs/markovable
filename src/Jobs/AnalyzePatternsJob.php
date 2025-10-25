<?php

namespace VinkiusLabs\Markovable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\Markovable\Events\PredictionMade;
use VinkiusLabs\Markovable\MarkovableManager;

class AnalyzePatternsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<int, string> */
    private array $corpus;

    private int $order;

    private string $context;

    private ?string $cacheKey;

    private ?int $cacheTtl;

    private ?string $storageName;

    /** @var array<string, mixed> */
    private array $options;

    public ?array $result = null;

    public function __construct(
        array $corpus,
        int $order,
        string $context,
        ?string $cacheKey,
        ?int $cacheTtl,
        ?string $storageName,
        array $options = []
    ) {
        $this->corpus = $corpus;
        $this->order = $order;
        $this->context = $context;
        $this->cacheKey = $cacheKey;
        $this->cacheTtl = $cacheTtl;
        $this->storageName = $storageName;
        $this->options = $options;
    }

    public function handle(MarkovableManager $manager): void
    {
        $chain = $manager->chain($this->context)->order($this->order)->options($this->options);

        if ($this->storageName) {
            $chain->useStorage($this->storageName);
        }

        if (isset($this->options['analyzer'])) {
            $chain->analyzer((string) $this->options['analyzer']);
        }

        if (! empty($this->options['broadcast'])) {
            $chain->broadcast((string) $this->options['broadcast']);
        }

        if (! empty($this->corpus)) {
            $chain->trainFrom($this->corpus);
        }

        if ($this->cacheKey) {
            $chain->cache($this->cacheKey, $this->cacheTtl, $this->storageName);
        }

        $seed = (string) ($this->options['seed'] ?? '');
        $limit = (int) ($this->options['limit'] ?? 3);

        $this->result = $chain->predict($seed, $limit, $this->options);

        if (! empty($this->options['broadcast'])) {
            event(new PredictionMade($chain, $seed, is_array($this->result) ? $this->result : ['predictions' => $this->result], $this->options['broadcast']));
        }
    }

    public function cache(?string $key, ?int $ttl = null, ?string $storage = null): self
    {
        $this->cacheKey = $key;
        $this->cacheTtl = $ttl;
        $this->storageName = $storage ?? $this->storageName;

        return $this;
    }
}



