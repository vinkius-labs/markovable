<?php

namespace VinkiusLabs\Markovable\Contracts;

interface Storage
{
    public function put(string $key, array $payload, ?int $ttl = null): void;

    public function get(string $key): ?array;

    public function forget(string $key): void;
}
