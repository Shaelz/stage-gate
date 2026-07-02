<?php

declare(strict_types=1);

namespace StageGate;

final class ProofResult
{
    /**
     * @param Row[] $rows
     * @param ProofError[] $errors
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
