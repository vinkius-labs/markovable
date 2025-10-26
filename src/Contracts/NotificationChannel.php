<?php

namespace VinkiusLabs\Markovable\Contracts;

interface NotificationChannel
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): void;
}
