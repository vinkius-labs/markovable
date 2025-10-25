<?php

namespace VinkiusLabs\Markovable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use VinkiusLabs\Markovable\Commands\AnalyzeCommand;
use VinkiusLabs\Markovable\Commands\GenerateCommand;
use VinkiusLabs\Markovable\Commands\TrainCommand;
use VinkiusLabs\Markovable\Observers\AutoTrainObserver;
use VinkiusLabs\Markovable\MarkovableManager;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/markovable.php', 'markovable');

        $this->app->singleton(MarkovableManager::class, static fn($app) => new MarkovableManager($app));
        $this->app->alias(MarkovableManager::class, 'markovable.manager');
        $this->app->alias(MarkovableManager::class, 'markovable');
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMacros();
        $this->bootAutoTrainObservers();
        $this->loadMigrations();
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/markovable.php' => $this->app->configPath('markovable.php'),
        ], 'markovable-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => $this->app->databasePath('migrations'),
        ], 'markovable-migrations');
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            TrainCommand::class,
            GenerateCommand::class,
            AnalyzeCommand::class,
        ]);
    }

    private function registerMacros(): void
    {
        if (! Collection::hasMacro('trainMarkovable')) {
            Collection::macro('trainMarkovable', function (?callable $callback = null) {
                $chain = app(MarkovableManager::class)->trainFrom($this);

                if ($callback) {
                    $callback($chain);
                }

                return $chain;
            });
        }
    }

    private function bootAutoTrainObservers(): void
    {
        $config = $this->app['config']->get('markovable.auto_train', []);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $models = $config['models'] ?? [];
        $field = $config['field'] ?? null;

        foreach ($models as $modelClass) {
            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $modelClass::observe(new AutoTrainObserver($field));
        }
    }

    private function loadMigrations(): void
    {
        $migrationsPath = __DIR__ . '/../database/migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }
}
