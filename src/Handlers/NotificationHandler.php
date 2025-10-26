<?php

namespace VinkiusLabs\Markovable\Handlers;

use VinkiusLabs\Markovable\Contracts\NotificationChannel;

class NotificationHandler
{
    /** @var array<int, NotificationChannel> */
    private array $channels = [];

    /**
     * @param  iterable<int, NotificationChannel>  $channels
     */
    public function __construct(iterable $channels = [])
    {
        foreach ($channels as $channel) {
            $this->addChannel($channel);
        }
    }

    public function addChannel(NotificationChannel $channel): void
    {
        $this->channels[] = $channel;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notify(array $payload): void
    {
        foreach ($this->channels as $channel) {
            $channel->send($payload);
        }
    }
}
