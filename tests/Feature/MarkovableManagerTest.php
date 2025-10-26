<?php

namespace VinkiusLabs\Markovable\Test\Feature;

use BadMethodCallException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use VinkiusLabs\Markovable\Contracts\Analyzer;
use VinkiusLabs\Markovable\Contracts\Generator;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\MarkovableManager;
use VinkiusLabs\Markovable\Test\TestCase;

class MarkovableManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        MarkovableManager::flushMacros();

        parent::tearDown();
    }

    public function test_chain_uses_default_text_builder(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        $chain = $manager->chain('text');

        $this->assertInstanceOf(MarkovableChain::class, $chain);
        $this->assertSame('text', $chain->getContext());
        $this->assertSame(2, $manager->config('default_order'));
    }

    public function test_extend_methods_register_custom_resolvers(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        $manager->extendAnalyzer('custom', function () {
            return new class implements Analyzer
            {
                public function analyze(MarkovableChain $chain, array $model, array $options = []): array
                {
                    return ['predictions' => []];
                }
            };
        });

        $manager->extendGenerator('custom', function () {
            return new class implements Generator
            {
                public function generate(array $model, int $length, array $options = []): string
                {
                    return 'generated';
                }
            };
        });

        $manager->extendStorage('memory', function () {
            return new class implements \VinkiusLabs\Markovable\Contracts\Storage
            {
                public array $payloads = [];

                public function put(string $key, array $payload, ?int $ttl = null): void
                {
                    $this->payloads[$key] = $payload;
                }

                public function get(string $key): ?array
                {
                    return $this->payloads[$key] ?? null;
                }

                public function forget(string $key): void
                {
                    unset($this->payloads[$key]);
                }
            };
        });

        $this->assertArrayHasKey('predictions', $manager->analyzer('custom')->analyze($manager->chain(), [], []));
        $this->assertSame('generated', $manager->generator('custom')->generate([], 1));

        $storage = $manager->storage('memory');
        $storage->put('key', ['value' => 'test']);

        $this->assertSame(['value' => 'test'], $storage->get('key'));
    }

    public function test_extend_builder_creates_custom_chain(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        $manager->extendBuilder('custom', \VinkiusLabs\Markovable\Builders\TextBuilder::class);

        $customChain = $manager->chain('custom');

        $this->assertSame('custom', $customChain->getContext());
    }

    public function test_has_analyzer_checks_configuration(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        $this->assertTrue($manager->hasAnalyzer('text'));
        $this->assertFalse($manager->hasAnalyzer('missing'));
    }

    public function test_extend_alias_registers_analyzer(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        $manager->extend('alias', function () {
            return new class implements Analyzer
            {
                public function analyze(MarkovableChain $chain, array $model, array $options = []): array
                {
                    return ['predictions' => []];
                }
            };
        });

        $this->assertTrue($manager->hasAnalyzer('alias'));
    }

    public function test_generator_throws_when_not_defined(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->generator('missing');
    }

    public function test_dynamic_call_forwards_to_chain_or_macros(): void
    {
        $manager = $this->app->make(MarkovableManager::class);

        Cache::forget('markovable::dynamic-call');

        $manager->train(['dynamic call works via manager'])->cache('dynamic-call');

        $generated = $manager->cache('dynamic-call')->generate(3);

        $this->assertIsString($generated);

        MarkovableManager::macro('macroStub', fn () => 'macro result');

        $this->assertSame('macro result', $manager->macroStub());

        $this->expectException(BadMethodCallException::class);
        $manager->nonExistingMethod();
    }
}
