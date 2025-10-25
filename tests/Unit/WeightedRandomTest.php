<?php

namespace VinkiusLabs\Markovable\Test\Unit;

use PHPUnit\Framework\TestCase;
use VinkiusLabs\Markovable\Support\WeightedRandom;

class WeightedRandomTest extends TestCase
{
    public function test_choose_returns_null_for_empty_weights(): void
    {
        $this->assertNull(WeightedRandom::choose([]));
    }

    public function test_choose_returns_uniform_key_when_total_is_zero(): void
    {
        mt_srand(1234);

        $choice = WeightedRandom::choose([
            'first' => 0,
            'second' => 0,
            'third' => 0,
        ]);

        $this->assertContains($choice, ['first', 'second', 'third']);
    }

    public function test_choose_honors_weights(): void
    {
        mt_srand(5);

        $choice = WeightedRandom::choose([
            'common' => 90,
            'rare' => 10,
        ]);

        $this->assertSame('common', $choice);
    }
}
