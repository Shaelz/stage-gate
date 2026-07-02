<?php

declare(strict_types=1);

namespace StageGate;

final class Publish
{
    /**
     * @param ClassifiedRow[] $classifiedRows
     *
     * @throws PublishBlockedException when the batch isn't approved or has unacknowledged overwrite-risk rows
     */
    public static function plan(array $classifiedRows, ApprovalResult $approval, string $source): PublishPlan
    {
        if (! $approval->canPublish()) {
            throw new PublishBlockedException($approval->blockedRowKeys);
        }

        $writes = [];
        $changeCounts = [];

        foreach ($classifiedRows as $classified) {
            if ($classified->changeClass === ChangeClass::Unchanged) {
                continue;
            }

            $writes[] = new PublishWrite($classified->row, $classified->changeClass);

            $name = $classified->changeClass->name;
            $changeCounts[$name] = ($changeCounts[$name] ?? 0) + 1;
        }

        $audit = new AuditEntry(
            $source,
            $approval->approvedBy,
            $changeCounts,
            array_map(fn (PublishWrite $write) => $write->row->key, $writes),
        );

        return new PublishPlan($writes, $audit);
    }
}
