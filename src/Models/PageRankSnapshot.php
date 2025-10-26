<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Support\Arr;

class PageRankSnapshot extends MarkovableModelSnapshot
{
    public static function capture(string $modelKey, PageRankResult $result, array $attributes = []): self
    {
        $payloadArray = $result->toPayload(true);
        $payload = json_encode($payloadArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadString = $payload ?: '{}';

        $metadata = array_merge(['type' => 'pagerank'], $result->metadata(), (array) ($attributes['metadata'] ?? []));

        $defaults = [
            'model_key' => $modelKey,
            'payload' => $payloadString,
            'metadata' => $metadata,
            'storage' => $attributes['storage'] ?? 'database',
            'compressed' => (bool) ($attributes['compressed'] ?? false),
            'encrypted' => (bool) ($attributes['encrypted'] ?? false),
            'original_size' => strlen($payloadString),
            'stored_size' => strlen($payloadString),
        ];

        $attributes = array_merge($defaults, Arr::except($attributes, ['metadata']));

        return static::create($attributes);
    }
}
