<?php

declare(strict_types=1);

namespace StageGate;

final class ApprovalResult
{
    /**
     * @param string[] $approvedRowKeys
     * @param string[] $blockedRowKeys row keys with an unacknowledged overwrite-risk classification
     */
    public function __construct(
        public readonly Batch $batch,
        public readonly array $approvedRowKeys,
        public readonly array $blockedRowKeys,
        public readonly string $approvedBy,
    ) {
    }

    public function canPublish(): bool
    {
        return $this->blockedRowKeys === [] && $this->batch->status === BatchStatus::Approved;
    }
}
