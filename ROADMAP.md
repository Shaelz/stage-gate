# Roadmap

1. **(Done) Map the seams in biljartv2.** Go through the existing import code and mark exactly where the five stages currently live, what's generic versus fixture-specific, and what state each stage actually needs from the last. This is read-only work; nothing gets extracted until the seams are clear. See [docs/biljartv2-seams.md](docs/biljartv2-seams.md).
   - Locate the current import code path in BiljartV2 (controller, job, service — wherever proof/stage/review/approve/publish actually happen today).
   - Annotate each of the five stages against the real code: which class/method does it, what it takes in, what it returns.
   - List everything that's fixture-specific (Excel parsing, competition/fixture field names) versus everything that's generic (the diff classification logic, the pending/approved state machine, the transactional write).
   - Write down what state crosses each stage boundary (e.g. what "staged rows" look like going into review, what a classified row looks like going into approve).
   - Capture the existing overwrite-risk classification rules verbatim — this becomes the spec for the generic classifier in step 2.

2. **(Done) Build the framework-agnostic core.** Proof, stage, review, approve, publish as plain PHP with no Laravel dependency. Port biljartv2's real test cases (overwrite classification, blocked publish, transactional all-or-nothing) as the core's own test suite, not new hypothetical tests.
   - Define the core's data contracts (schema, typed row, classified row, publish result) independent of any storage engine.
   - Implement proof: schema validation, typed rows or errors, no partial success.
   - Implement stage: hold pending rows, not visible to callers outside the pipeline.
   - Implement review: diff classifier producing new / unchanged / updated / overwrite_risk / removed, using the rules captured in step 1.
   - Implement approve: explicit per-row acknowledgment, with nothing overwrite_risk publishable without it.
   - Implement publish: returns a plan (writes + audit entry) for the host to execute inside its own transaction — see the storage-boundary decision in [docs/biljartv2-seams.md](docs/biljartv2-seams.md).
   - Ported the *scenarios* biljartv2's real test cases cover (classification outcomes, blocked publish, determinism, idempotent republish), expressed against the generic types rather than literal ports — biljartv2's tests are written against Eloquent fixtures and don't translate 1:1 to a Laravel-free core.

3. **(Scaffolded) Build the thin Laravel wrapper.** Queue integration, Eloquent-friendly hooks, whatever glue biljartv2 actually needs. Keep this layer as small as possible; if it's doing real work, that work probably belongs in the core.
   - Package scaffold via spatie/laravel-package-tools — done.
   - Eloquent adapters for whatever storage the core's publish step needs (repository interface implementations, not core changes) — done: `BatchRepository`, `PublishExecutor`, `PublishWriter`, `ExistingRowsProvider`.
   - Queue/job wiring for async proof/publish if biljartv2 currently runs import as a queued job — done: `ProofAndStageJob`, `PublishJob`, both taking only serializable primitives (a class-string `ImportDefinition`, not the definition itself, since `Schema`'s validators are closures and can't survive queue serialization).
   - Audit trail persistence (migration + model) living in the Laravel layer, not the core — done: `stage_gate_audit_logs` + `StageGateAuditLog`.
   - Not yet done: actually wiring a real `ImportDefinition` for biljartv2's fixtures (schema, field groups, existing-row query, write logic) — that naturally belongs in step 4, since it's biljartv2-specific glue, not generic wrapper code.

4. **Swap biljartv2 over to consume the package.** This is the real proof step: not a demo of the extracted library, but the production system running on it. If this migration is painful, the extraction boundary was wrong and step 2 needs revisiting.
   - Add the package as a local/path Composer dependency in BiljartV2 — done.
   - **(4a, done)** Write a real `ImportDefinition` for league fixtures (`LeagueFixturesImportDefinition`, `LeagueFixturesExistingRowsProvider`, `LeagueFixturesPublishWriter`) and verify it against BiljartV2's actual schema in a new, additive test — nothing existing touched or replaced. Found and fixed a real bug this surfaced: `StageGateServiceProvider` lives under `src/Laravel/`, one level deeper than spatie/laravel-package-tools assumes, so `config('stage-gate')` and the package's migrations silently failed to load until the base path was set explicitly and `runsMigrations()` was added. This was a step-3 wrapper bug, not an extraction-boundary problem — step 2 doesn't need revisiting.
   - **(4b, done)** Replace the existing import code path with calls into the package, stage by stage, keeping the current UI/behavior unchanged.
     - Diff (league + cup fixtures): done — both branches of `FixturesImportDiffService` classify via `StageGate\Classifier` and each type's `ImportDefinition` field groups instead of locally duplicated field-list copies. `classifyUpdatedRow()` (the old hardcoded per-type version) is gone entirely.
     - Publish (league + cup fixtures): done — `FixturesImportPublicationService` no longer builds full insert-row arrays and bulk-deletes the whole season+competition scope before reinserting everything. `StageGate\Classifier` diffs staged rows against real existing rows, `StageGate\Publish::plan()` turns that into upserts and deletes (deletes only for `ChangeClass::Removed` rows — no longer staged), and the existing `PublishWriter`s apply them. `--allow-result-overwrite` maps onto the core's per-row approval model: set → every overwrite-risk row is pre-approved; unset → none are — same all-or-nothing gate as before, same error message.
     - One real behavior difference the publish swap introduces: unchanged rows keep their original `id`/`created_at` instead of being destroyed and recreated on every publish. This didn't break any test (`canonicalFixtureSnapshot()` already strips `id`/`created_at`), but the cross-batch-replacement test does require every row in scope — including unchanged ones — to carry the latest batch's provenance (`import_batch_id`/`published_from_batch_id`). Added an explicit re-stamp step after the diff-driven writes to preserve that, since the writer-driven diff alone wouldn't touch untouched rows.
     - Audit logging is untouched: still goes through BiljartV2's existing `SimpleAuditLogger` into its own `audit_logs` table, not stage-gate's — the plan's own audit entry is unused here, since biljartv2 already has an audit trail with existing consumers (manual-edit detection, the Filament UI).
     - Proof: intentionally *not* swapped — its real validation (team alias resolution, season-mismatch checks) is bespoke business logic the generic `Proof::analyze()` can't replicate; swapping it would be pure risk for no gain.
     - Approve: doesn't get its own swap either — production's `FixturesImportApprovalService` gates on whole-batch validation errors only, not per-row overwrite-risk acknowledgment. Folded into the Publish swap instead, as described above.
     - All 323 tests pass throughout every sub-step, including transactional-failure, idempotent-republish, cross-batch-scope-replacement, and overwrite-risk-gating assertions — step 4b's real proof that this preserves behavior end to end.
   - Run a real fixture import through the new path in a non-production environment before cutting over.
   - Cut over production and watch at least one real import cycle end-to-end.

5. **Open source and write the case study**, once biljartv2 has run on the package through at least one real import cycle. The proof section should cite that migration, not just biljartv2's original numbers.
   - Update README's "Proof this works" section with post-migration numbers (imports run, rows processed, any issues hit).
   - Decide license and public repo visibility.
   - Write the case study as its own piece (or extend the existing biljartv2 portfolio writeup) covering the extraction, not just the original feature.

6. **Stretch: publish to Packagist**, add a second reference implementation (something that isn't fixtures/results) to prove the abstraction generalizes past one domain.
   - Tag a first semver release and register on Packagist.
   - Pick a second import domain (something with different overwrite semantics than fixtures) to stress-test the generic classifier.

## Open questions

- **Overwrite-risk beyond fixtures** — resolved in step 2: `FieldGroup`s (with an `isRisk` flag) are caller-supplied, so risk is defined per import type, not hardcoded. See [docs/biljartv2-seams.md](docs/biljartv2-seams.md).
- **Approval identity** — resolved in step 2/3: the core takes an opaque `approvedBy` string; the Laravel wrapper persists it verbatim, no interface required.
- **Storage boundary** — resolved: `Publish::plan()` returns writes + an audit entry; the host executes them. See [docs/biljartv2-seams.md](docs/biljartv2-seams.md).
- **Partial re-review.** If new rows are staged while older staged rows are still pending approval, does review re-run against the whole pending set, or only the newly staged rows? Still open — `Classifier::classifyAll()` currently takes the full staged set each call, so the host decides what "staged" means at call time, but this hasn't been exercised against a real incremental-staging scenario yet.
- **Schema evolution.** If proof's schema changes between the time a file is staged and reviewed, is that a new proof run required, or does stage/review just trust whatever proof already validated? Still open.
