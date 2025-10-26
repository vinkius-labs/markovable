<?php

namespace VinkiusLabs\Markovable\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\Markovable\MarkovableChain;

class ContentGenerated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public MarkovableChain $chain;

    public string $content;

    public function __construct(MarkovableChain $chain, string $content)
    {
        $this->chain = $chain;
        $this->content = $content;
    }
}
