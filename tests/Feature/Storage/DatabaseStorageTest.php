<?php

namespace VinkiusLabs\Markovable\Test\Feature\Storage;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use VinkiusLabs\Markovable\Storage\DatabaseStorage;
use VinkiusLabs\Markovable\Test\TestCase;

class DatabaseStorageTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_put_get_and_forget_record(): void
    {
        $storage = new DatabaseStorage;
        $payload = [
            'context' => 'navigation',
            'model' => ['state' => ['link' => 1]],
            'meta' => ['type' => 'App\\Models\\Page', 'id' => 10],
        ];

        Carbon::setTestNow('2024-01-01 10:00:00');

        $storage->put('db-key', $payload, 60);

        $record = DB::table('markovable_models')->where('name', 'db-key')->first();

        $this->assertNotNull($record);
        $this->assertSame('navigation', $record->context);
        $this->assertSame('App\\Models\\Page', $record->markovable_type);
        $this->assertSame(10, (int) $record->markovable_id);

        $this->assertSame($payload, $storage->get('db-key'));

        Carbon::setTestNow('2024-01-02 11:00:00');

        $this->assertNull($storage->get('db-key'));
        $this->assertSame(0, DB::table('markovable_models')->where('name', 'db-key')->count());

        $storage->forget('missing-key');
        $this->assertSame(0, DB::table('markovable_models')->count());
    }
}
