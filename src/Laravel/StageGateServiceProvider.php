<?php

declare(strict_types=1);

namespace StageGate\Laravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class StageGateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // package-tools resolves config/migrations as basePath/../config,
        // /../database — i.e. it expects basePath to be the package's src/
        // directory (or a Providers/ subfolder, which it special-cases).
        // Our provider lives one level deeper, under src/Laravel/, so the
        // auto-detected basePath (this file's own directory) is wrong by
        // one level and must be corrected explicitly, or config('stage-gate')
        // and the migrations silently fail to load.
        $package->setBasePath(dirname(__DIR__));

        $package
            ->name('stage-gate')
            ->hasConfigFile()
            ->hasMigrations([
                'create_stage_gate_import_batches_table',
                'create_stage_gate_import_batch_rows_table',
                'create_stage_gate_audit_logs_table',
            ])
            ->runsMigrations();
    }
}
