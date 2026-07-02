# stage-gate

A typed import pipeline as a standalone library: proof, stage, review, approve, publish.

## Why

Most import features skip straight from "parse the file" to "write the rows." That works until someone re-uploads a file that overlaps with data already in the system, and something gets silently overwritten. The fix isn't a bigger try/catch, it's a pipeline with named stages, an explicit diff-classification step before any write, and a publish step that is all-or-nothing.

This pattern was built once, inside a client project (biljartv2), for importing competition fixtures from Excel. It has been running in production since the 2026/2027 season: 241 fixtures, zero silent overwrites, every publish traceable to a file upload, an approval action, and a named account. stage-gate is that pattern, pulled out and made generic over what's being imported.

## What it does

Five stages, each with an explicit outcome:

1. **Proof** — parse the source file against a typed schema. Malformed rows fail here, before anything else runs.
2. **Stage** — hold the parsed rows in a pending state, not yet visible to the rest of the system.
3. **Review** — classify every staged row against what already exists. Each row gets a category: new, unchanged, updated, or overwrite-risk.
4. **Approve** — a human (or a rule) explicitly acknowledges overwrite-risk rows. Nothing with an overwrite-risk classification can publish silently.
5. **Publish** — a single transaction. Partial publishes are not possible: either every approved row lands, or none do.

Every publish leaves an audit trail: which rows changed, what they changed from and to, which file and which account it came from.

## What it isn't

Not a general ETL framework. It doesn't move data between systems, transform arbitrary formats, or schedule jobs. It's specifically the safety layer between "someone uploaded a file" and "the database changed": diffing, gating, and accountability. Bring your own parser and your own storage; stage-gate owns the stages in between.

## Suggested stack

- **PHP 8.2+** for the core. Matches the reference implementation (biljartv2) and keeps extraction close to a refactor instead of a rewrite.
- **Pest or PHPUnit** for the pipeline's own test suite: overwrite classification, transactional publish, and the "no partial writes" guarantee all need direct coverage, not just app-level tests.
- **Thin Laravel package wrapper** (spatie/laravel-package-tools for the scaffold) around a framework-agnostic core. Keep the core free of Laravel dependencies so the stage contract could theoretically be consumed outside Laravel later, even if the only real consumer for now is Laravel.
- **Composer** for distribution, private or public depending on whether this stays client-specific tooling or goes fully open source like ship-notes.

See [ROADMAP.md](ROADMAP.md) for the planned build sequence.

## Status

Not started. Extracting from biljartv2's Laravel implementation. Target: a small, framework-agnostic core (the stage contract, the diff classifier, the transactional publish) with a thin Laravel package on top, since that's where the reference implementation lives.

## Shape of the API (draft, subject to change)

```
pipeline
  .proof(rows, schema)       // validate, return typed rows or errors
  .stage(rows)                // hold pending, not yet visible
  .review(rows, existing)     // classify: new | unchanged | updated | overwrite_risk
  .approve(rowIds)            // explicit acknowledgment of overwrite-risk rows
  .publish()                  // transactional write, full audit trail
```

## Proof this works before it's a library

biljartv2's case study: [gersvelte-portfolio/src/routes/projects/biljartv2](../gersvelte-portfolio/src/routes/projects/biljartv2)
