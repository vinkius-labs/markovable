<?php

namespace VinkiusLabs\Markovable\Models;

use Illuminate\Contracts\Support\Arrayable;

class PageRankResult implements Arrayable
{
    /** @var array<string, PageRankNode> */
    private array $nodes;

    /** @var array<string, mixed> */
    private array $metadata;

    /** @var array<string, array<string, array<string, float>>> */
    private array $groups;

    /**
     * @param  array<string, PageRankNode>  $nodes
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, array<string, float>>>  $groups
     */
    public function __construct(array $nodes, array $metadata = [], array $groups = [])
    {
        $this->nodes = $nodes;
        $this->metadata = $metadata;
        $this->groups = $groups;
    }

    /**
     * @return array<string, PageRankNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, array<string, array<string, float>>>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function withoutMetadata(): self
    {
        return new self($this->nodes, [], $this->groups);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toPayload(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(bool $includeMetadata = true): array
    {
        $payload = [
            'pagerank' => $this->serializeNodes($this->nodes),
        ];

        if ($this->groups !== []) {
            $payload['groups'] = $this->groups;
        }

        if ($includeMetadata && $this->metadata !== []) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }

    /**
     * @param  array<string, PageRankNode>  $nodes
     * @return array<string, array<string, float>>
     */
    private function serializeNodes(array $nodes): array
    {
        $serialized = [];

        foreach ($nodes as $identifier => $node) {
            $serialized[$identifier] = $node->toArray();
        }

        return $serialized;
    }
}
