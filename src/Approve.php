<?php

declare(strict_types=1);

namespace StageGate;

final class Approve
{
    /**
     * @param ClassifiedRow[] $classifiedRows
     * @param string[] $approvedRowKeys row keys the caller explicitly acknowledges as overwrite risks
     */
    public static function approve(
        Batch $batch,
        array $classifiedRows,
        array $approvedRowKeys,
        string $approvedBy,
    ): ApprovalResult {
        $blockedRowKeys = [];

        foreach ($classifiedRows as $classified) {
            $isOverwriteRisk = $classified->changeClass === ChangeClass::OverwriteRisk;
            $isAcknowledged = in_array($classified->row->key, $approvedRowKeys, true);

            if ($isOverwriteRisk && ! $isAcknowledged) {
                $blockedRowKeys[] = $classified->row->key;
            }
        }

        $resultBatch = $blockedRowKeys === [] ? $batch->withStatus(BatchStatus::Approved) : $batch;

        return new ApprovalResult($resultBatch, $approvedRowKeys, $blockedRowKeys, $approvedBy);
    }
}
