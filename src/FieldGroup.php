<?php

declare(strict_types=1);

namespace StageGate;

final class FieldGroup
{
    /** @param string[] $fields */
    public function __construct(
        public readonly string $name,
        public readonly array $fields,
        public readonly bool $isRisk = false,
    ) {
    }
}
