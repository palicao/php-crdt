<?php

namespace CRDT_GCounter;

class GCounter implements GCounterInterface
{
    /**
     * The Map holding the values for each instance
     * @var array
     */
    private $count = [];

    /**
     * @var string
     */
    private $selfIdentifier;

    public function __construct(string $selfIdentifier, array $identifiers)
    {
        $this->selfIdentifier = $selfIdentifier;
        foreach ($identifiers as $id) {
            $this->count[$id] = 0;
        }
    }

    public function increment(): void
    {
        $this->count[$this->selfIdentifier]++;
    }

    public function get(): array
    {
        return $this->count;
    }

    public function count(): int
    {
        return array_sum(array_values($this->count));
    }

    public function join(array $remoteCount): void
    {
        foreach ($this->count as $key => $value) {
            $this->count[$key] = max($this->count[$key], $remoteCount[$key] ?? 0);
        }
    }
}
