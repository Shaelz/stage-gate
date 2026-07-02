<?php

declare(strict_types=1);

namespace StageGate;

final class Row
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $key,
        public readonly array $data,
    ) {
    }

    public function get(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }
}
