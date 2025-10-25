<?php

namespace VinkiusLabs\Markovable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \VinkiusLabs\Markovable\MarkovableChain chain(?string $context = null)
 * @method static \VinkiusLabs\Markovable\MarkovableChain train($value)
 * @method static \VinkiusLabs\Markovable\MarkovableChain trainFrom($value)
 * @method static \VinkiusLabs\Markovable\MarkovableChain order(int $order)
 * @method static \VinkiusLabs\Markovable\MarkovableChain analyze($subject = null, array $options = [])
 */
class Markovable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'markovable';
    }
}
