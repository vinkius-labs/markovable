<?php

namespace VinkiusLabs\Markovable\Test\Feature\Casts;

use VinkiusLabs\Markovable\Casts\MarkovableCast;
use VinkiusLabs\Markovable\Facades\Markovable;
use VinkiusLabs\Markovable\MarkovableChain;
use VinkiusLabs\Markovable\Test\TestCase;

class MarkovableCastTest extends TestCase
{
    public function test_get_returns_null_when_value_is_null(): void
    {
        $cast = new MarkovableCast;

        $chain = $cast->get(null, 'content', null, []);

        $this->assertNull($chain);
    }

    public function test_get_returns_trained_chain_from_value(): void
    {
        $cast = new MarkovableCast;

        /** @var MarkovableChain $chain */
        $chain = $cast->get(null, 'content', 'hello world', []);

        $this->assertInstanceOf(MarkovableChain::class, $chain);
        $this->assertNotEmpty($chain->toProbabilities());
    }

    public function test_set_returns_last_generated_when_chain_generated(): void
    {
        $cast = new MarkovableCast;

        $chain = Markovable::train(['phpunit coverage is important']);
        $generated = $chain->generate(3);

        $value = $cast->set(null, 'content', $chain, []);

        $this->assertSame($generated, $value);
    }

    public function test_set_returns_string_from_tokens_when_chain_not_generated(): void
    {
        $cast = new MarkovableCast;

        $chain = Markovable::train(['laravel testing']);

        $value = $cast->set(null, 'content', $chain, []);

        $this->assertSame('', $value);
    }

    public function test_set_casts_other_values_to_string(): void
    {
        $cast = new MarkovableCast;

        $this->assertSame('123', $cast->set(null, 'content', 123, []));
    }
}
