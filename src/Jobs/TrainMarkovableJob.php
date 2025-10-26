<?php

namespace VinkiusLabs\Markovable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\Markovable\MarkovableManager;

class TrainMarkovableJob implements ShouldQueue
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

    private bool $incremental;

    /**
     * @param  array<int, string>  $corpus
     * @param  array<string, mixed>  $options
     */
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
        $this->incremental = (bool) ($options['incremental'] ?? false);
        unset($options['incremental']);
        $this->options = $options;
    }

    public function handle(MarkovableManager $manager): void
    {
        $chain = $manager->chain($this->context)->order($this->order)->options($this->options);

        if ($this->storageName) {
            $chain->useStorage($this->storageName);
        }

        if ($this->incremental) {
            $chain->incremental();
        }

        $chain->trainFrom($this->corpus);

        if ($this->cacheKey) {
            $chain->cache($this->cacheKey, $this->cacheTtl, $this->storageName);
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
