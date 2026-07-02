<?php

declare(strict_types=1);

namespace StageGate\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ImportBatch extends Model
{
    protected $fillable = ['key', 'status'];

    public function getTable(): string
    {
        return config('stage-gate.tables.import_batches');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class);
    }
}
