<?php

declare(strict_types=1);

namespace StageGate;

final class Field
{
    public function __construct(
        public readonly string $name,
        public readonly bool $required = true,
        /** @var (callable(mixed): bool)|null */
        public readonly mixed $validate = null,
    ) {
    }
}
