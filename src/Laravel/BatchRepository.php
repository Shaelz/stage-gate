<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use Illuminate\Support\Facades\DB;
use StageGate\ApprovalResult;
use StageGate\Batch;
use StageGate\BatchStatus;
use StageGate\Laravel\Models\ImportBatch;
use StageGate\Laravel\Models\ImportBatchRow;
use StageGate\ProofError;
use StageGate\Row;

final class BatchRepository
{
    public static function save(Batch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $model = ImportBatch::query()->updateOrCreate(
                ['key' => $batch->key],
                ['status' => strtolower($batch->status->name)],
            );

            $model->rows()->delete();

            foreach ($batch->rows as $row) {
                ImportBatchRow::query()->create([
                    'import_batch_id' => $model->id,
                    'row_key' => $row->key,
                    'data' => $row->data,
                ]);
            }
        });
    }

    public static function find(string $key): ?Batch
    {
        $model = ImportBatch::query()->with('rows')->where('key', $key)->first();

        if ($model === null || $model->status === 'proof_failed') {
            return null;
        }

        $rows = $model->rows
            ->map(fn (ImportBatchRow $row) => new Row($row->row_key, $row->data))
            ->all();

        $status = match ($model->status) {
            'staged' => BatchStatus::Staged,
            'approved' => BatchStatus::Approved,
            'published' => BatchStatus::Published,
        };

        return new Batch($model->key, $rows, $status);
    }

    /**
     * Persists an approval decision so a later, separately-dispatched
     * publish job can re-check gating with fresh data rather than trusting
     * a decision made in a different request.
     */
    public static function saveApproval(string $batchKey, ApprovalResult $approval): void
    {
        ImportBatch::query()->where('key', $batchKey)->update([
            'status' => strtolower($approval->batch->status->name),
            'approved_by' => $approval->approvedBy,
            'approved_row_keys' => $approval->approvedRowKeys,
        ]);
    }

    /** @param ProofError[] $errors */
    public static function saveProofFailure(string $batchKey, array $errors): void
    {
        ImportBatch::query()->updateOrCreate(
            ['key' => $batchKey],
            [
                'status' => 'proof_failed',
                'errors' => array_map(
                    fn (ProofError $error) => ['source_row' => $error->sourceRow, 'message' => $error->message],
                    $errors,
                ),
            ],
        );
    }

    /** @return array{approvedRowKeys: string[], approvedBy: string} */
    public static function loadApproval(string $batchKey): array
    {
        $model = ImportBatch::query()->where('key', $batchKey)->firstOrFail();

        return [
            'approvedRowKeys' => $model->approved_row_keys ?? [],
            'approvedBy' => $model->approved_by ?? '',
        ];
    }
}
