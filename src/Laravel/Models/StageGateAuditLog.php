<?php

declare(strict_types=1);

namespace StageGate\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StageGateAuditLog extends Model
{
    protected $fillable = ['import_batch_id', 'source', 'approved_by', 'change_counts', 'published_row_keys'];

    protected $casts = [
        'change_counts' => 'array',
        'published_row_keys' => 'array',
    ];

    public function getTable(): string
    {
        return config('stage-gate.tables.audit_logs');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
