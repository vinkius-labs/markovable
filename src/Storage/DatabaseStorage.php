<?php

namespace VinkiusLabs\Markovable\Storage;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use VinkiusLabs\Markovable\Contracts\Storage;

class DatabaseStorage implements Storage
{
    private string $table = 'markovable_models';

    public function put(string $key, array $payload, ?int $ttl = null): void
    {
        $now = Carbon::now();
        $expiresAt = $ttl ? $now->copy()->addSeconds($ttl) : null;

        $context = $payload['context'] ?? 'text';
        $meta = $payload['meta'] ?? [];

        DB::table($this->table)->updateOrInsert(
            ['name' => $key],
            [
                'context' => $context,
                'markovable_type' => $meta['type'] ?? null,
                'markovable_id' => $meta['id'] ?? null,
                'payload' => json_encode($payload),
                'ttl' => $ttl,
                'expires_at' => $expiresAt,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function get(string $key): ?array
    {
        $record = DB::table($this->table)->where('name', $key)->first();

        if (! $record) {
            return null;
        }

        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            DB::table($this->table)->where('name', $key)->delete();

            return null;
        }

        return json_decode($record->payload, true) ?: null;
    }

    public function forget(string $key): void
    {
        DB::table($this->table)->where('name', $key)->delete();
    }
}
