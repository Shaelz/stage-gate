<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class StageGateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('stage-gate')
            ->hasConfigFile()
            ->hasMigrations([
                'create_stage_gate_import_batches_table',
                'create_stage_gate_import_batch_rows_table',
                'create_stage_gate_audit_logs_table',
            ]);
    }
}
