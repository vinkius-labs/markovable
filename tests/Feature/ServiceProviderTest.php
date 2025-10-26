<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use Closure;
use Illuminate\Support\Collection;
use VinkiusLabs\Markovable\Observers\AutoTrainObserver;
use VinkiusLabs\Markovable\ServiceProvider;
use VinkiusLabs\Markovable\Test\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_register_binds_singleton_and_aliases(): void
    {
        $provider = new ServiceProvider($this->app);
        $provider->register();

        $manager = $this->app->make('markovable');

        $this->assertSame($manager, $this->app->make('markovable.manager'));
        $this->assertSame($manager, $this->app->make('markovable'));
    }

    public function test_boot_handles_console_and_non_console_paths(): void
    {
        $provider = new ServiceProvider($this->app);

        $this->invokePrivate($provider, 'registerPublishing', $this->fakeApp(false));
        $this->invokePrivate($provider, 'registerPublishing', $this->app);
        $this->invokePrivate($provider, 'registerCommands', $this->fakeApp(false));

        $provider->boot();

        $this->assertTrue(Collection::hasMacro('trainMarkovable'));

        $published = ServiceProvider::pathsToPublish(ServiceProvider::class, 'markovable-config');
        $this->assertNotEmpty($published);
    }

    public function test_auto_train_observer_bootstraps_configured_models(): void
    {
        config()->set('markovable.auto_train', [
            'enabled' => true,
            'models' => [\stdClass::class, ServiceProviderObservedModel::class],
            'field' => 'content',
        ]);

        ServiceProviderObservedModel::$observerRegistered = false;

        $provider = new ServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(ServiceProviderObservedModel::$observerRegistered);

        config()->set('markovable.auto_train', [
            'enabled' => false,
            'models' => [],
            'field' => null,
        ]);
    }

    private function invokePrivate(ServiceProvider $provider, string $method, $app): void
    {
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $property = $reflection->getProperty('app');
        $property->setAccessible(true);
        $original = $property->getValue($provider);
        $property->setValue($provider, $app);

        $callable = Closure::bind(function () use ($method) {
            return $this->{$method}();
        }, $provider, ServiceProvider::class);

        $callable();

        $property->setValue($provider, $original);
    }

    private function fakeApp(bool $console)
    {
        return new class($console, $this->app)
        {
            private bool $console;

            private $delegate;

            public function __construct(bool $console, $delegate)
            {
                $this->console = $console;
                $this->delegate = $delegate;
            }

            public function runningInConsole(): bool
            {
                return $this->console;
            }

            public function __call($method, $parameters)
            {
                return $this->delegate->{$method}(...$parameters);
            }
        };
    }
}

class ServiceProviderObservedModel extends \Illuminate\Database\Eloquent\Model
{
    public static bool $observerRegistered = false;

    protected $table = 'service_provider_models';

    protected $guarded = [];

    public $timestamps = false;

    public static function observe($class)
    {
        self::$observerRegistered = $class instanceof AutoTrainObserver;
    }
}
