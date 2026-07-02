<?php

declare(strict_types=1);

namespace StageGate;

final class PublishPlan
{
    /** @param PublishWrite[] $writes */
    public function __construct(
        public readonly array $writes,
        public readonly AuditEntry $audit,
    ) {
    }
}
