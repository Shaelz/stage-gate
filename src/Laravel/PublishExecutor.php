<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use Illuminate\Support\Facades\DB;
use StageGate\Laravel\Models\ImportBatch;
use StageGate\Laravel\Models\StageGateAuditLog;
use StageGate\PublishPlan;

final class PublishExecutor
{
    /**
     * Executes a PublishPlan's writes inside a single transaction, then
     * records the audit trail. The core never touches storage itself; this
     * is the one place that turns a plan into real writes.
     */
    public static function execute(PublishPlan $plan, PublishWriter $writer, ?string $batchKey = null): StageGateAuditLog
    {
        return DB::transaction(function () use ($plan, $writer, $batchKey) {
            foreach ($plan->writes as $write) {
                $writer->write($write);
            }

            $batch = $batchKey !== null ? ImportBatch::query()->where('key', $batchKey)->first() : null;
            $batch?->update(['status' => 'published']);

            return StageGateAuditLog::create([
                'import_batch_id' => $batch?->id,
                'source' => $plan->audit->source,
                'approved_by' => $plan->audit->approvedBy,
                'change_counts' => $plan->audit->changeCounts,
                'published_row_keys' => $plan->audit->publishedRowKeys,
            ]);
        });
    }
}
