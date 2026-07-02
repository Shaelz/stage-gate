<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use StageGate\PublishWrite;

interface PublishWriter
{
    /**
     * Apply a single write from a PublishPlan — an upsert for
     * New/Updated/OverwriteRisk rows, a delete for Removed rows.
     */
    public function write(PublishWrite $write): void;
}
