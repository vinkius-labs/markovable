<?php

namespace VinkiusLabs\Markovable\Test\Feature\Storage;

use Illuminate\Support\Facades\Cache;
use VinkiusLabs\Markovable\Storage\CacheStorage;
use VinkiusLabs\Markovable\Test\TestCase;

class CacheStorageTest extends TestCase
{
    public function test_put_get_and_forget_round_trip(): void
    {
        Cache::clear();

        $storage = new CacheStorage();
        $payload = ['order' => 2, 'model' => ['state' => ['next' => 1.0]]];

        $storage->put('key', $payload, 60);

        $this->assertSame($payload, $storage->get('key'));

        $storage->forget('key');

        $this->assertNull($storage->get('key'));
        $this->assertFalse(Cache::has('markovable::key'));
    }
}
