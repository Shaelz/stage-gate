<?php

declare(strict_types=1);

namespace StageGate;

final class ProofError
{
    public function __construct(
        public readonly int $sourceRow,
        public readonly string $message,
    ) {
    }
}
