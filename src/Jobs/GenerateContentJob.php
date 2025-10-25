<?php

namespace VinkiusLabs\Markovable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\Markovable\Events\ContentGenerated;
use VinkiusLabs\Markovable\MarkovableManager;

class GenerateContentJob implements ShouldQueue
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

    private int $length;

    public ?string $result = null;

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
        $this->length = (int) ($options['length'] ?? 100);
    }

    public function handle(MarkovableManager $manager): void
    {
        $chain = $manager->chain($this->context)->order($this->order)->options($this->options);

        if ($this->storageName) {
            $chain->useStorage($this->storageName);
        }

        if (! empty($this->corpus)) {
            $chain->trainFrom($this->corpus);
        }

        if ($this->cacheKey) {
            $chain->cache($this->cacheKey, $this->cacheTtl, $this->storageName);
        }

        $this->result = $chain->generate($this->length, $this->options);

        event(new ContentGenerated($chain, $this->result));
    }

    public function cache(?string $key, ?int $ttl = null, ?string $storage = null): self
    {
        $this->cacheKey = $key;
        $this->cacheTtl = $ttl;
        $this->storageName = $storage ?? $this->storageName;

        return $this;
    }
}



