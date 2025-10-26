<?php

namespace VinkiusLabs\Markovable\Test\Feature\Testing;

use VinkiusLabs\Markovable\Test\TestCase;
use VinkiusLabs\Markovable\Testing\MarkovableAssertions;

class MarkovableAssertionsTest extends TestCase
{
    use MarkovableAssertions;

    public function test_assert_markovable_generated_and_trained_helpers(): void
    {
        $chain = $this->markovableChain(['helpers improve tests']);

        $this->assertMarkovableTrained($chain);

        $generated = $chain->generate(3);

        $this->assertMarkovableGenerated($generated, 1);
    }

    public function test_markovable_chain_helper_respects_custom_order(): void
    {
        $chain = $this->markovableChain(['custom order coverage'], 3);

        $this->assertSame(3, $chain->getOrder());
        $this->assertNotEmpty($chain->toProbabilities());
    }
}
