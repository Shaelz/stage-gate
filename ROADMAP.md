# Roadmap

1. **Map the seams in biljartv2.** Go through the existing import code and mark exactly where the five stages currently live, what's generic versus fixture-specific, and what state each stage actually needs from the last. This is read-only work; nothing gets extracted until the seams are clear.
   - Locate the current import code path in BiljartV2 (controller, job, service — wherever proof/stage/review/approve/publish actually happen today).
   - Annotate each of the five stages against the real code: which class/method does it, what it takes in, what it returns.
   - List everything that's fixture-specific (Excel parsing, competition/fixture field names) versus everything that's generic (the diff classification logic, the pending/approved state machine, the transactional write).
   - Write down what state crosses each stage boundary (e.g. what "staged rows" look like going into review, what a classified row looks like going into approve).
   - Capture the existing overwrite-risk classification rules verbatim — this becomes the spec for the generic classifier in step 2.

2. **Build the framework-agnostic core.** Proof, stage, review, approve, publish as plain PHP with no Laravel dependency. Port biljartv2's real test cases (overwrite classification, blocked publish, transactional all-or-nothing) as the core's own test suite, not new hypothetical tests.
   - Define the core's data contracts (schema, typed row, classified row, publish result) independent of any storage engine.
   - Implement proof: schema validation, typed rows or errors, no partial success.
   - Implement stage: hold pending rows, not visible to callers outside the pipeline.
   - Implement review: diff classifier producing new / unchanged / updated / overwrite_risk, using the rules captured in step 1.
   - Implement approve: explicit per-row acknowledgment, with nothing overwrite_risk publishable without it.
   - Implement publish: all-or-nothing transaction plus audit trail (what changed, from/to, source file, approving account).
   - Port biljartv2's real test cases for each stage, adapted to the generic types — not new tests invented for the library.

3. **Build the thin Laravel wrapper.** Queue integration, Eloquent-friendly hooks, whatever glue biljartv2 actually needs. Keep this layer as small as possible; if it's doing real work, that work probably belongs in the core.
   - Package scaffold via spatie/laravel-package-tools.
   - Eloquent adapters for whatever storage the core's publish step needs (repository interface implementations, not core changes).
   - Queue/job wiring for async proof/publish if biljartv2 currently runs import as a queued job.
   - Audit trail persistence (migration + model) living in the Laravel layer, not the core.

4. **Swap biljartv2 over to consume the package.** This is the real proof step: not a demo of the extracted library, but the production system running on it. If this migration is painful, the extraction boundary was wrong and step 2 needs revisiting.
   - Add the package as a local/path Composer dependency in BiljartV2.
   - Replace the existing import code path with calls into the package, stage by stage, keeping the current UI/behavior unchanged.
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

- **Overwrite-risk beyond fixtures.** Fixture overwrite-risk is presumably keyed on schedule/score fields. What does "risk" mean for a domain with no obvious natural key — does the classifier need pluggable comparison logic, or a fixed set of strategies?
- **Approval identity.** biljartv2 records approvals against a named account. Does the core assume an opaque "approver id" string, or does it need an interface the host app implements?
- **Storage boundary.** The core owns proof/stage/review/approve/publish; storage is "bring your own." Where exactly is that boundary — does the core call a repository interface, or does it return instructions (a diff/plan) for the host app to execute inside its own transaction?
- **Partial re-review.** If new rows are staged while older staged rows are still pending approval, does review re-run against the whole pending set, or only the newly staged rows?
- **Schema evolution.** If proof's schema changes between the time a file is staged and reviewed, is that a new proof run required, or does stage/review just trust whatever proof already validated?
