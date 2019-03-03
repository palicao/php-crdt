<?php

namespace CRDT_GCounter;

interface GCounterInterface
{
    public function increment(): void;

    public function get(): array;

    public function count(): int;

    public function join(array $remoteCount): void;
}
