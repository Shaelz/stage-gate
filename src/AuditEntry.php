<?php

declare(strict_types=1);

namespace StageGate;

final class AuditEntry
{
    /**
     * @param array<string, int> $changeCounts keyed by ChangeClass case name
     * @param string[] $publishedRowKeys
     */
    public function __construct(
        public readonly string $source,
        public readonly string $approvedBy,
        public readonly array $changeCounts,
        public readonly array $publishedRowKeys,
    ) {
    }
}
