<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\Markovable\MarkovableChain;

class ModelTrained
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public MarkovableChain $chain;

    public function __construct(MarkovableChain $chain)
    {
        $this->chain = $chain;
    }
}



