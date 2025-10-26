<?php

namespace VinkiusLabs\Markovable\Storage;

use VinkiusLabs\Markovable\Contracts\Storage;

class FileStorage implements Storage
{
    public function put(string $key, array $payload, ?int $ttl = null): void
    {
        $path = $this->path($key);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, json_encode([
            'payload' => $payload,
            'ttl' => $ttl,
            'stored_at' => time(),
        ], JSON_PRETTY_PRINT));
    }

    public function get(string $key): ?array
    {
        $path = $this->path($key);

        if (! file_exists($path)) {
            return null;
        }

        $content = json_decode(file_get_contents($path) ?: '', true);

        if (! $content) {
            return null;
        }

        $ttl = $content['ttl'] ?? null;
        $storedAt = $content['stored_at'] ?? null;

        if ($ttl && $storedAt && (time() - $storedAt) > $ttl) {
            $this->forget($key);

            return null;
        }

        return $content['payload'] ?? null;
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function path(string $key): string
    {
        $filename = str_replace(['/', '\\'], '-', $key).'.json';
        $base = function_exists('storage_path') ? storage_path('app/markovable') : sys_get_temp_dir().'/markovable';

        return $base.DIRECTORY_SEPARATOR.$filename;
    }
}
