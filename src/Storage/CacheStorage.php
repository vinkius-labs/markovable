<?php

namespace VinkiusLabs\Markovable\Storage;

use Illuminate\Support\Facades\Cache;
use VinkiusLabs\Markovable\Contracts\Storage;

class CacheStorage implements Storage
{
    public function put(string $key, array $payload, ?int $ttl = null): void
    {
        Cache::put($this->prefix($key), $payload, $ttl);
    }

    public function get(string $key): ?array
    {
        return Cache::get($this->prefix($key));
    }

    public function forget(string $key): void
    {
        Cache::forget($this->prefix($key));
    }

    private function prefix(string $key): string
    {
        return "markovable::{$key}";
    }
}
