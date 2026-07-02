<?php

declare(strict_types=1);

use StageGate\Approve;
use StageGate\Batch;
use StageGate\BatchStatus;
use StageGate\ChangeClass;
use StageGate\ClassifiedRow;
use StageGate\Publish;
use StageGate\PublishBlockedException;
use StageGate\Row;

it('rejects publishing an unapproved batch', function () {
    $batch = new Batch('batch-1', [], BatchStatus::Staged);
    $classifiedRows = [classifiedRow('LEAGUE-R01-M01', ChangeClass::OverwriteRisk)];
    $approval = Approve::approve($batch, $classifiedRows, [], 'jane@example.com');

    Publish::plan($classifiedRows, $approval, 'fixtures-2026.xlsx');
})->throws(PublishBlockedException::class);

it('builds a write plan and audit entry for an approved batch, skipping unchanged rows', function () {
    $batch = new Batch('batch-1', [], BatchStatus::Staged);
    $classifiedRows = [
        classifiedRow('LEAGUE-R01-M01', ChangeClass::New),
        classifiedRow('LEAGUE-R01-M02', ChangeClass::Unchanged),
        classifiedRow('LEAGUE-R01-M03', ChangeClass::OverwriteRisk),
    ];
    $approval = Approve::approve($batch, $classifiedRows, ['LEAGUE-R01-M03'], 'jane@example.com');

    $plan = Publish::plan($classifiedRows, $approval, 'fixtures-2026.xlsx');

    expect($plan->writes)->toHaveCount(2)
        ->and($plan->audit->source)->toBe('fixtures-2026.xlsx')
        ->and($plan->audit->approvedBy)->toBe('jane@example.com')
        ->and($plan->audit->changeCounts)->toBe(['New' => 1, 'OverwriteRisk' => 1])
        ->and($plan->audit->publishedRowKeys)->toBe(['LEAGUE-R01-M01', 'LEAGUE-R01-M03']);
});
