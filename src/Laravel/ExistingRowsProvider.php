<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use StageGate\Batch;
use StageGate\Row;

interface ExistingRowsProvider
{
    /**
     * Query whatever canonical storage this import type writes to, scoped to
     * this batch, and return it as core Rows for the classifier to diff
     * against. Scope (e.g. season + competition) is entirely up to the
     * implementation — the core has no notion of it.
     *
     * @return Row[]
     */
    public function existingRows(Batch $batch): array;
}
