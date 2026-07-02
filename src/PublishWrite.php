<?php

declare(strict_types=1);

namespace StageGate;

final class PublishWrite
{
    public function __construct(
        public readonly Row $row,
        public readonly ChangeClass $changeClass,
    ) {
    }
}
