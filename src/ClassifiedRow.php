<?php

declare(strict_types=1);

namespace StageGate;

final class ClassifiedRow
{
    /** @param FieldChange[] $fieldChanges */
    public function __construct(
        public readonly Row $row,
        public readonly ChangeClass $changeClass,
        public readonly array $fieldChanges,
    ) {
    }
}
