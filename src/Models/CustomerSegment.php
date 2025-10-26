<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Contracts\Support\Arrayable;

final class CustomerSegment implements Arrayable
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

    public function label(): ?string
    {
        return $this->attributes['label'] ?? null;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
