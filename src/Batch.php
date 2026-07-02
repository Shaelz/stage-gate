<?php

declare(strict_types=1);

namespace StageGate;

final class Batch
{
    /** @param Row[] $rows */
    public function __construct(
        public readonly string $key,
        public readonly array $rows,
        public readonly BatchStatus $status,
    ) {
    }

    public function withStatus(BatchStatus $status): self
    {
        return new self($this->key, $this->rows, $status);
    }
}
