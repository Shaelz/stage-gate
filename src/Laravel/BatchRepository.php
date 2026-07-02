<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use Illuminate\Support\Facades\DB;
use StageGate\Batch;
use StageGate\BatchStatus;
use StageGate\Laravel\Models\ImportBatch;
use StageGate\Laravel\Models\ImportBatchRow;
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

        if ($model === null) {
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
}
