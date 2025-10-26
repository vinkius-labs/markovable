<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Contracts\Support\Arrayable;

final class RecommendationAction implements Arrayable
{
    /** @var array<string, mixed> */
    private array $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public function channel(): ?string
    {
        return $this->attributes['notification_channel'] ?? null;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
