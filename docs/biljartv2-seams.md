# biljartv2 seams (step 1 mapping)

Read-only survey of where the five stages currently live in `BiljartV2` (path:
`C:/CODE/biljart/BiljartV2`), what's generic versus fixture-specific, and what
state crosses each stage boundary. This is the source material for step 2's
extraction — nothing here has been changed.

## Where each stage lives today

Each stage is already its own service class, with a console command as entry
point:

| Stage | Class | Path |
|---|---|---|
| Proof | `FixturesImportProofService::analyze()` | `app/Importing/Fixtures/FixturesImportProofService.php` |
| Stage | `FixturesImportStagingService::stage()` | `app/Importing/Fixtures/FixturesImportStagingService.php` |
| Review | `FixturesImportReviewService::review()` | `app/Importing/Fixtures/FixturesImportReviewService.php` |
| Diff (classifier) | `FixturesImportDiffService::diff()` | `app/Importing/Fixtures/FixturesImportDiffService.php` |
| Approve | `FixturesImportApprovalService::approve()` | `app/Importing/Fixtures/FixturesImportApprovalService.php` |
| Publish | `FixturesImportPublicationService::publish()` | `app/Importing/Fixtures/FixturesImportPublicationService.php` |

Console commands: `app/Console/Commands/ImportFixtures{Proof,Stage,Review,Diff,Approve,Publish}Command.php`.

Web upload entry point: `app/Filament/Pages/UploadImportWorkbook.php` (lines 124-151).

Supporting/fixture-specific parsers: `FixturesWorkbookParser`, `LeagueVisualSheetParser`,
`CupVisualSheetParser`, `FixturesImportNormalizer`, `FixturesImportValidator`,
`CanonicalSeasonTeamResolver`, `XlsxWorkbookReader` — all under `app/Importing/`.

## What each stage does

- **Proof** — reads the XLSX, detects sheets by name (`competitie` / `beker`,
  case-insensitive), resolves team references against published
  `season_teams`, and outputs normalized JSON + a markdown report. No DB writes.
- **Stage** — re-runs proof, then if valid: creates an `import_batches` row
  (`status = validated`) and persists parsed rows into
  `import_batch_league_fixtures` / `import_batch_cup_fixtures`. Duplicate
  uploads are caught via checksum.
- **Review** — reads the staged batch back and checks it's ready for diffing
  (`status = validated`, no blocking errors, fixture count > 0). Read-only;
  produces a markdown summary, no state change.
- **Diff / classify** — the real overwrite-risk logic. Compares staged rows
  against canonical `league_fixtures` / `cup_fixtures` and assigns one of:
  `added`, `removed`, `unchanged`, `schedule_changed`, `team_reference_changed`,
  `score_status_changed`, `mixed_changed`.
- **Approve** — transitions `validated → approved` if there are no blocking
  errors. Writes an audit log entry. No data changes.
- **Publish** — re-runs the diff as a final safety check; rejects if
  `overwrite_risk_count > 0` unless `--allow-result-overwrite` is passed.
  Otherwise: upserts competitions, deletes existing fixtures in scope
  (season + competition), inserts the staged rows, and writes an audit log
  entry (`fixture_import.published` or `.republished`). Single transaction,
  all-or-nothing.

## State passed between stages

**Proof output** (consumed by stage):

```php
[
    'normalized' => [...],
    'json_path' => '...',
    'report_path' => '...',
    'import_type' => 'fixture_import',
    'target_season_code' => '2025/2026',
    'league_fixtures' => [...],   // normalized fixture objects
    'cup_fixtures' => [...],
    'issues' => [...],            // parsing/validation warnings & errors
    'summary' => [...],           // error_count, warning_count, counts, valid flag
]
```

**Staged row** (`import_batch_league_fixtures` table) — the shape everything
downstream operates on:

```php
[
    'import_batch_id' => int,
    'source_sheet' => 'competitie' | 'beker',
    'source_row' => int,
    'fixture_key' => 'LEAGUE-2025-2026-R01-M02',
    'season_code' => '2025/2026',
    'competition_code' => 'RHS',
    'round_number' => int,
    'match_date' => date,
    'match_type' => 'match' | 'bye' | 'placeholder',
    'home_team_code' => 'T_STUUPKE' | null,
    'away_team_code' => string | null,
    'home_season_team_id' => int | null,
    'away_season_team_id' => int | null,
    'home_score' => int | null,
    'away_score' => int | null,
    'status' => 'scheduled' | 'played' | 'postponed',
    // ...
]
```

The cup variant carries the same shape plus `stage_type`, `stage_label`,
`group_code`, `match_number`, `home_qualifier`/`away_qualifier`, `is_placeholder`.

**Diff output** — grouped counts and row lists per classification, e.g.:

```php
[
    'league' => [
        'added_count' => int, 'removed_count' => int, 'unchanged_count' => int,
        'schedule_changed_count' => int, 'team_reference_changed_count' => int,
        'score_status_changed_count' => int, 'mixed_changed_count' => int,
        'overwrite_risk_count' => int, 'manual_edit_detected_count' => int,
        'added_rows' => [...], 'overwrite_risk_rows' => [...], // etc.
    ],
    'cup' => [ /* same shape */ ],
    'totals' => [...],
]
```

Each row in a classification bucket carries `field_changes` (old/new pairs),
`overwrite_risk` (bool), and `manual_edit_detected` (bool).

## The overwrite-risk / diff classification rules

From `FixturesImportDiffService::classifyUpdatedRow()`. Fields are grouped
into three buckets:

```php
$scheduleFields  = ['match_date', 'round_number', 'match_type', 'is_bye'];        // + stage fields for cup
$referenceFields = ['home_season_team_id', 'away_season_team_id',
                     'home_special_token', 'away_special_token'];                  // + qualifiers for cup
$resultFields    = ['home_score', 'away_score', 'status'];
```

Classification is a straight match on which buckets changed:

```php
$changeClass = match (true) {
    !$scheduleChanged && !$referenceChanged && !$resultChanged => 'unchanged',
    $scheduleChanged && !$referenceChanged && !$resultChanged  => 'schedule_changed',
    !$scheduleChanged && $referenceChanged && !$resultChanged  => 'team_reference_changed',
    !$scheduleChanged && !$referenceChanged && $resultChanged  => 'score_status_changed',
    default => 'mixed_changed',
};

$overwriteRisk = $resultChanged; // any of home_score, away_score, status changed
```

**Manual-edit flag**: separately, the diff queries `audit_logs` for a prior
`league_fixture.result_updated` / `cup_fixture.result_updated` entry against
the canonical row. If one exists, `manual_edit_detected = true` — this marks
overwrite-risk rows that would clobber a human's manual correction as
especially dangerous, on top of the base classification.

Field comparison normalizes both sides to display strings first (null →
`'none'`, booleans → `'yes'/'no'`, team IDs resolved to display names) before
comparing, so the diff is stable regardless of storage representation.

## Audit trail

Table: `audit_logs` (migration `2026_04_28_012200_create_audit_logs_table.php`),
model `App\Models\AuditLog`, written via `SimpleAuditLogger`.

Fields: `actor_user_id`, `action` (e.g. `fixture_import.approved`,
`fixture_import.published`, `fixture_import.republished`,
`league_fixture.result_updated`), `auditable_type`, `auditable_id`,
`batch_id`, `before_json`, `after_json`, `message`, timestamps.

Approve and publish both write a before/after audit entry keyed to the batch;
manual result edits outside the pipeline also write here, which is what the
diff stage cross-checks for the manual-edit flag.

## Generic vs. fixture-specific

**Fixture-specific:**
- Sheet names (`competitie`/`beker`) and column layout per sheet
- Fixture key format (`LEAGUE-...`/`CUP-...`), competition codes (`RHS`, `RTS_CUP`)
- Team resolution against `season_teams` + `team_aliases`, special tokens (`VRIJ`)
- Two divergent canonical schemas: `league_fixtures` vs `cup_fixtures`
- Scope-based replacement semantics (delete-by-season+competition, not upsert-by-key)

**Already generic:**
- The six-stage service architecture and naming
- `ImportBatch` model/tables — already carry an `import_type` column
  supporting multiple import kinds (`fixture_import`, `team_import`,
  `all_in_one_compat` all exist today)
- `import_batch_issues` (severity/code/message) is import-type-agnostic
- Audit logging (`SimpleAuditLogger`/`AuditLog`) — no fixture awareness at all
- The diff classifier's structure — field buckets (schedule/reference/result)
  are just arrays, i.e. already parameter-shaped even though not yet parameterized
- Filament `ImportBatchResource` UI — generic enough to already list multiple import types

## Existing tests worth porting

`tests/Feature/ImportFixturesCommandTest.php` already covers the scenarios
the roadmap calls out as core to the library, not hypothetical:

- Proof: team alias resolution, season mismatch blocking, unknown/ambiguous
  team detection, determinism across repeated runs
- Stage: staging without canonical writes
- Review: readiness checks
- Diff: all six classification classes, overwrite-risk + manual-edit
  detection, determinism
- Approve: audit logging, validation (blocking errors, wrong batch type)
- Publish: canonical writes, transaction rollback on validation failure,
  idempotent republish, cross-batch scope replacement

These are the test cases step 2 should port, per the roadmap ("port
biljartv2's real test cases... not new hypothetical tests").

## Answers this gives to the roadmap's open questions

- **Overwrite-risk beyond fixtures**: today it's exactly "did any field in
  the `resultFields` bucket change." The bucket itself (schedule/reference/
  result) is already the right level of abstraction — a generic classifier
  likely just needs the three bucket definitions to be caller-supplied.
- **Approval identity**: `actor_user_id` on the audit log, nullable. The core
  can likely take an opaque "approver id" and let the host resolve it.
- **Storage boundary** — decided: publish currently does upsert-competitions +
  delete-by-scope + insert-staged directly against Eloquent inside one
  transaction. Rather than exposing a repository interface for the core to
  call, the core returns a plan/diff describing what should be written, and
  the host application (the thin Laravel wrapper, step 3) executes that plan
  inside its own transaction. Keeps the core storage-agnostic without asking
  every host to implement an interface, and leaves transactional semantics
  where they already work today: in Eloquent.
- **Manual-edit detection** was not called out in the original open
  questions but is a real, load-bearing behavior: publish safety depends on
  checking the audit log for edits made *outside* the pipeline. The generic
  core needs some hook for "has this canonical record been touched since
  last published," not just a staged-vs-canonical diff.
