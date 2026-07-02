<?php

declare(strict_types=1);

namespace StageGate;

final class Stage
{
    /** @param Row[] $rows */
    public static function stage(string $key, array $rows): Batch
    {
        return new Batch($key, $rows, BatchStatus::Staged);
    }
}
