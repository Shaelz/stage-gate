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
            // Laravel's migrator runs migrations sorted by filename, not in
            // the order listed here. The date prefixes force import_batches
            // to be created before the two tables that hold foreign keys to
            // it; without them MySQL/MariaDB fails with errno 150 (SQLite
            // allows forward FK references, so tests never catch it).
            ->hasMigrations([
                '2026_07_03_000001_create_stage_gate_import_batches_table',
                '2026_07_03_000002_create_stage_gate_import_batch_rows_table',
                '2026_07_03_000003_create_stage_gate_audit_logs_table',
            ])
            ->runsMigrations();
    }
}
