<?php

declare(strict_types=1);

use StageGate\Approve;
use StageGate\Batch;
use StageGate\BatchStatus;
use StageGate\ChangeClass;

it('approves a batch with no overwrite-risk rows', function () {
    $batch = new Batch('batch-1', [], BatchStatus::Staged);
    $classifiedRows = [classifiedRow('LEAGUE-R01-M01', ChangeClass::Updated)];

    $result = Approve::approve($batch, $classifiedRows, [], 'jane@example.com');

    expect($result->canPublish())->toBeTrue()
        ->and($result->batch->status)->toBe(BatchStatus::Approved);
});

it('blocks approval when an overwrite-risk row is not acknowledged', function () {
    $batch = new Batch('batch-1', [], BatchStatus::Staged);
    $classifiedRows = [classifiedRow('LEAGUE-R01-M01', ChangeClass::OverwriteRisk)];

    $result = Approve::approve($batch, $classifiedRows, [], 'jane@example.com');

    expect($result->canPublish())->toBeFalse()
        ->and($result->blockedRowKeys)->toBe(['LEAGUE-R01-M01'])
        ->and($result->batch->status)->toBe(BatchStatus::Staged);
});

it('approves a batch once every overwrite-risk row is explicitly acknowledged', function () {
    $batch = new Batch('batch-1', [], BatchStatus::Staged);
    $classifiedRows = [classifiedRow('LEAGUE-R01-M01', ChangeClass::OverwriteRisk)];

    $result = Approve::approve($batch, $classifiedRows, ['LEAGUE-R01-M01'], 'jane@example.com');

    expect($result->canPublish())->toBeTrue()
        ->and($result->batch->status)->toBe(BatchStatus::Approved);
});
