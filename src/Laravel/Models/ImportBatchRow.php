<?php

declare(strict_types=1);

namespace StageGate\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ImportBatchRow extends Model
{
    protected $table = 'stage_gate_import_batch_rows';

    protected $fillable = ['import_batch_id', 'row_key', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
