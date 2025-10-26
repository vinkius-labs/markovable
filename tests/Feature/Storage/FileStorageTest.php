<?php

namespace VinkiusLabs\Markovable\Test\Feature\Storage;

use Illuminate\Filesystem\Filesystem;
use VinkiusLabs\Markovable\Storage\FileStorage;
use VinkiusLabs\Markovable\Test\TestCase;

class FileStorageTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
    }

    public function test_put_get_expire_and_forget_cycle(): void
    {
        $storage = new FileStorage;
        $payload = ['context' => 'text', 'model' => ['state' => ['next' => 1]]];

        $storage->put('file/key', $payload, 5);

        $path = storage_path('app/markovable/file-key.json');
        $this->assertFileExists($path);

        $this->assertSame($payload, $storage->get('file/key'));

        $content = json_decode($this->files->get($path), true);
        $content['stored_at'] = time() - 10;
        $content['ttl'] = 1;
        $this->files->put($path, json_encode($content));

        $this->assertNull($storage->get('file/key'));

        $storage->forget('file/key');
        $this->assertFileDoesNotExist($path);
    }
}
