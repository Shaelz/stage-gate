<?php

declare(strict_types=1);

use StageGate\BatchStatus;
use StageGate\Row;
use StageGate\Stage;

it('holds staged rows as pending, not yet published', function () {
    $rows = [new Row('LEAGUE-R01-M01', ['home_score' => null])];

    $batch = Stage::stage('2026-batch-1', $rows);

    expect($batch->status)->toBe(BatchStatus::Staged)
        ->and($batch->rows)->toBe($rows);
});
