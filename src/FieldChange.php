<?php

declare(strict_types=1);

namespace StageGate;

final class FieldChange
{
    public function __construct(
        public readonly string $field,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
    ) {
    }
}
