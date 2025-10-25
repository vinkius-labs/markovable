<?php

namespace VinkiusLabs\Markovable;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Traits\Macroable;
use BadMethodCallException;
use InvalidArgumentException;
use VinkiusLabs\Markovable\Contracts\Analyzer as AnalyzerContract;
use VinkiusLabs\Markovable\Contracts\Generator as GeneratorContract;
use VinkiusLabs\Markovable\Contracts\Storage as StorageContract;
use VinkiusLabs\Markovable\Builders\AnalyticsBuilder;
use VinkiusLabs\Markovable\Builders\TextBuilder;
use VinkiusLabs\Markovable\Generators\SequenceGenerator;
use VinkiusLabs\Markovable\Generators\TextGenerator;

class MarkovableManager
{
    use Macroable;

    private Container $app;

    /** @var array<string, callable|string> */
    private array $customBuilders = [];

    /** @var array<string, AnalyzerContract> */
    private array $resolvedAnalyzers = [];

    /** @var array<string, callable|class-string<AnalyzerContract>> */
    private array $customAnalyzers = [];

    /** @var array<string, GeneratorContract> */
    private array $resolvedGenerators = [];

    /** @var array<string, callable|class-string<GeneratorContract>> */
    private array $customGenerators = [];

    /** @var array<string, StorageContract> */
    private array $resolvedStorages = [];

    /** @var array<string, callable|class-string<StorageContract>> */
    private array $customStorages = [];

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function chain(?string $context = null): MarkovableChain
    {
        $context ??= 'text';

        $resolver = $this->customBuilders[$context]
            ?? $this->defaultBuilders()[$context]
            ?? MarkovableChain::class;

        return $this->app->make($resolver, ['manager' => $this, 'context' => $context]);
    }

    public function analyzer(string $name): AnalyzerContract
    {
        if (! isset($this->resolvedAnalyzers[$name])) {
            $resolver = $this->customAnalyzers[$name]
                ?? $this->app['config']->get("markovable.analyzers.$name");

            if (! $resolver) {
                throw new InvalidArgumentException("Analyzer [{$name}] is not defined.");
            }

            $this->resolvedAnalyzers[$name] = $this->resolve($resolver);
        }

        return $this->resolvedAnalyzers[$name];
    }

    public function generator(string $name): GeneratorContract
    {
        if (! isset($this->resolvedGenerators[$name])) {
            $resolver = $this->customGenerators[$name]
                ?? $this->defaultGenerators()[$name]
                ?? null;

            if (! $resolver) {
                throw new InvalidArgumentException("Generator [{$name}] is not defined.");
            }

            $this->resolvedGenerators[$name] = $this->resolve($resolver);
        }

        return $this->resolvedGenerators[$name];
    }

    public function storage(?string $name = null): StorageContract
    {
        $name ??= $this->app['config']->get('markovable.storage', 'cache');

        if (! isset($this->resolvedStorages[$name])) {
            $resolver = $this->customStorages[$name]
                ?? $this->app['config']->get("markovable.storages.$name");

            if (! $resolver) {
                throw new InvalidArgumentException("Storage [{$name}] is not defined.");
            }

            $this->resolvedStorages[$name] = $this->resolve($resolver);
        }

        return $this->resolvedStorages[$name];
    }

    public function config(string $key, $default = null)
    {
        return $this->app['config']->get("markovable.$key", $default);
    }

    public function extendAnalyzer(string $name, callable|string $resolver): void
    {
        unset($this->resolvedAnalyzers[$name]);
        $this->customAnalyzers[$name] = $resolver;
    }

    public function extendBuilder(string $name, callable|string $resolver): void
    {
        $this->customBuilders[$name] = $resolver;
    }

    public function extend(string $name, callable|string $resolver): void
    {
        $this->extendAnalyzer($name, $resolver);
    }

    public function extendGenerator(string $name, callable|string $resolver): void
    {
        unset($this->resolvedGenerators[$name]);
        $this->customGenerators[$name] = $resolver;
    }

    public function extendStorage(string $name, callable|string $resolver): void
    {
        unset($this->resolvedStorages[$name]);
        $this->customStorages[$name] = $resolver;
    }

    /**
     * @template T
     * @param callable|class-string<T> $resolver
     * @return T
     */
    private function resolve($resolver)
    {
        if (is_callable($resolver)) {
            return $resolver($this->app, $this);
        }

        return $this->app->make($resolver);
    }

    /**
     * @return array<string, class-string<GeneratorContract>>
     */
    private function defaultGenerators(): array
    {
        return [
            'text' => TextGenerator::class,
            'sequence' => SequenceGenerator::class,
        ];
    }

    /**
     * @return array<string, callable|string>
     */
    private function defaultBuilders(): array
    {
        return [
            'text' => TextBuilder::class,
            'navigation' => AnalyticsBuilder::class,
            'analytics' => AnalyticsBuilder::class,
        ];
    }

    public function hasAnalyzer(string $name): bool
    {
        return isset($this->customAnalyzers[$name])
            || (bool) $this->app['config']->get("markovable.analyzers.$name");
    }

    public function __call(string $method, array $parameters)
    {
        if (static::hasMacro($method)) {
            $macro = static::$macros[$method];

            if ($macro instanceof Closure) {
                $macro = $macro->bindTo($this, static::class);
            }

            return $macro(...$parameters);
        }

        if ($method === 'chain') {
            return $this->chain(...$parameters);
        }

        $chain = $this->chain();

        if (! method_exists($chain, $method)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        return $chain->$method(...$parameters);
    }
}
