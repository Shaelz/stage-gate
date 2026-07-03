# stage-gate

[![tests](https://github.com/Shaelz/stage-gate/actions/workflows/tests.yml/badge.svg)](https://github.com/Shaelz/stage-gate/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/shaelz/stage-gate)](https://packagist.org/packages/shaelz/stage-gate)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A typed import pipeline for PHP: **proof, stage, review, approve, publish.** The safety layer between "someone uploaded a file" and "the database changed."

## Why

Most import features skip straight from "parse the file" to "write the rows." That works until someone re-uploads a file that overlaps with data already in the system, and something gets silently overwritten. The fix isn't a bigger try/catch — it's a pipeline with named stages, an explicit diff-classification step before any write, and a publish step that's all-or-nothing.

This pattern was built once inside a real Laravel app, for importing competition fixtures from Excel. It's been running in production since the 2025/2026 season, and has since been swapped in to replace that app's own hand-rolled diff and publish logic — verified against a real production database dump before the swap went live (see [ROADMAP.md](ROADMAP.md) for the details). stage-gate is that pattern, pulled out and made generic over what's being imported.

## What it does

Five stages, each with an explicit outcome:

1. **Proof** — validate the parsed rows against a typed schema. Malformed rows fail here, before anything else runs.
2. **Stage** — hold the valid rows in a pending state, not yet visible to the rest of the system.
3. **Review** — classify every staged row against what already exists: `new`, `unchanged`, `updated`, `overwrite_risk`, or `removed`.
4. **Approve** — a human (or a rule) explicitly acknowledges overwrite-risk rows. Nothing classified as overwrite-risk can publish without that acknowledgment.
5. **Publish** — returns a plan of writes and an audit entry. Your app executes the plan inside its own transaction: either every approved row lands, or none do.

## What it isn't

Not a general ETL framework. It doesn't parse your source files, move data between systems, or schedule jobs — bring your own parser and your own storage. stage-gate isn't a storage layer either: the core never touches a database. `Publish::plan()` hands back a list of writes and deletes plus an audit entry; your app is what executes them, inside its own transaction. This is deliberate — it's what keeps the core usable outside Laravel, and it's why "the host owns storage" runs through every stage.

## Installation

```bash
composer require shaelz/stage-gate
```

The core (`Proof`, `Stage`, `Classifier`, `Approve`, `Publish`) has no dependencies beyond PHP 8.2. The optional Laravel wrapper (`StageGate\Laravel\*` — a service provider, migrations, Eloquent models, queueable jobs) needs `spatie/laravel-package-tools`, `illuminate/support`, and `illuminate/database` in your app; see the `suggest` entries in [composer.json](composer.json).

## Quick example

```php
use StageGate\{Field, FieldGroup, Schema, Proof, Stage, Classifier, Approve, Publish};

// 1. Define what a valid row looks like, and which fields make a change risky.
$schema = new Schema('sku', [
    new Field('sku'),
    new Field('price', validate: fn ($v) => is_numeric($v)),
]);

$fieldGroups = [
    new FieldGroup('metadata', ['name', 'category']),
    new FieldGroup('price', ['price'], isRisk: true),
];

// 2. Proof: validate raw rows against the schema.
$proof = Proof::analyze($rawRows, $schema);
if (! $proof->isValid()) {
    // handle $proof->errors and stop here
}

// 3. Stage: hold the valid rows as a pending batch.
$batch = Stage::stage('import-2026-07-03', $proof->rows);

// 4. Review: classify staged rows against what your app already has.
$existingRows = /* your own query, e.g. WHERE category IN (...) */ [];
$classified = Classifier::classifyAll($batch->rows, $existingRows, $fieldGroups);

// 5. Approve: acknowledge overwrite-risk rows explicitly (or none, to block them).
$approval = Approve::approve($batch, $classified, approvedRowKeys: [], approvedBy: 'jane@example.com');

// 6. Publish: get a plan back, then execute it yourself, inside your own transaction.
$plan = Publish::plan($classified, $approval, source: 'products-2026-07.csv');

DB::transaction(function () use ($plan) {
    foreach ($plan->writes as $write) {
        // $write->changeClass tells you upsert vs. delete (ChangeClass::Removed)
        MyModel::query()->updateOrCreate(['sku' => $write->row->key], $write->row->data);
    }

    AuditLog::create([
        'source' => $plan->audit->source,
        'approved_by' => $plan->audit->approvedBy,
        'change_counts' => $plan->audit->changeCounts,
    ]);
});
```

Using Laravel? The wrapper gives you an `ImportDefinition` interface to bundle the schema/field groups/existing-row query/write logic per import type, plus queueable `ProofAndStageJob`/`PublishJob` classes — see `src/Laravel/`.

## Status

`v0.1.0`. The core and Laravel wrapper are built, fully tested, and proven against a real Laravel app's production fixture-import pipeline — both its diff and publish stages are swapped over to this package. See [ROADMAP.md](ROADMAP.md) for what's done, what's left, and the extraction notes in [docs/biljartv2-seams.md](docs/biljartv2-seams.md) for the design decisions behind the core (why publish returns a plan instead of touching storage, how overwrite-risk generalizes past one field-group split, and so on).

A written case study, covering that migration in a real production app, is planned once it's run through a real import cycle on this path.

## License

MIT — see [LICENSE](LICENSE).
