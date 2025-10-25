<?php

namespace VinkiusLabs\Markovable\Test;

use Illuminate\Foundation\Testing\Concerns\InteractsWithConsole;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use VinkiusLabs\Markovable\ServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use InteractsWithConsole;

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--force' => true]);
    }
}


