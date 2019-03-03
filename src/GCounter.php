<?php

namespace CRDT_GCounter;

class GCounter implements GCounterInterface
{
    /**
     * @var array
     */
    private $count = [];

    /**
     * @var string
     */
    private $thisPort;

    public function __construct(string $thisPort, array $ports)
    {
        $this->thisPort = $thisPort;
        foreach ($ports as $port) {
            $this->count[$port] = 0;
        }
    }

    public function increment(): void
    {
        $this->count[$this->thisPort]++;
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
            $this->count[$key] = max($this->count[$key], isset($remoteCount[$key]) ? $remoteCount[$key] : 0);
        }
    }
}
