# Ephemeral read-model testing checklist

Status: The first experiment is complete and was reviewed on October 18, 2026. Follow-up work below is pending. This checklist records findings and proposed work; it does not approve implementation.

Build a disposable UI over an exported database to discover how easily an outside consumer can use the project's data contract. Preserve useful findings in this repository even when the experimental UI is discarded.

## Existing experiment and evidence

- [x] Identify candidate targets and prepare [agent task prompts](../ephemeral_testing_tasks.md); see [candidate targets](../ephemeral_testing_targets.md).
- [x] Build an independent read-only browser in `/home/wsl/v3-test` using Python and plain JavaScript, without requiring the PHP application to run.
- [x] Exercise thread browsing/search, profiles, related identities, approval evidence, an approvals tree, and generic table browsing.
- [x] Review the saved October 10 dump: schema version 15; 11 tables, 742 threads, 1,400 posts, and 132 profiles. SQLite quick check passed; no orphan thread references or non-null author identities lacking profiles were found.
- [x] Run all 22 browser-project tests and syntax checks for both JavaScript files: passed on October 18.
- [x] Query every saved thread and profile, including both profile scopes, and browse each table: no query failures.
- [x] Smoke-test threads, a conversation, approvals, and a profile in Chromium: no JavaScript errors. The sampled 390-pixel mobile view had no horizontal page overflow. This was not exhaustive visual or accessibility testing.
- [x] Review read-only enforcement, parameterized queries, static-file allowlisting, and text rendering of post bodies.

Experiment commits: `2de6660`, `3c7b2a0`, `0a906be`, and `ae471f2` in the separate `v3-test` repository, all dated October 10, 2026. These show development over roughly an hour; they do not establish total effort or what assistance was needed. See that repository's `README.md`, `server.py`, `static/`, and `tests/test_server.py`.

## Priority 1 — Clarify identity and approval semantics

The browser infers approval targets by parsing `activity.label` as `Approve-Identity-ID: …`. It combines same-username identities connected by approval relationships in either direction, including chains. That is a consumer-defined grouping rule, not evidence by itself of shared ownership or effective authorization.

- [ ] Document the distinction between an approval activity record, an accepted grant, current effective approval, and evidence of common ownership.
- [ ] Explain the roles and limits of `identity_id`, `username_token`, `username_routes`, `is_approved`, and `approved_by_identity_id` for outside consumers.
- [ ] Determine whether the export already provides sufficient structured approval evidence. Identify facts requiring canonical records or acceptance receipts and facts unavailable from the dump alone.
- [ ] Assess whether structured approval target/source/status fields or an export view would remove the need to parse display labels. Compare against existing approval-history facilities before proposing a new interface.
- [ ] Define how consumers should handle root approvals, invitations, multiple records, self-links, cycles, missing profiles, and historical uncertainty.
- [ ] Validate grouping rules against disconnected same-username identities and approval records that do not establish an effective grant. Keep unknown facts explicit.
- [ ] Review the experimental UI's “cross-approved” wording and grouping against the documented meaning; record any mismatch before changing it.

Completion evidence: an outside consumer can explain which relationships the export proves, which it merely records, and which it cannot establish, without reading PHP implementation source. If the export cannot answer a question, its documentation states that limit.

## Priority 2 — Define export compatibility and failure behavior

The browser uses a version-15 dump but does not inspect its schema version. Startup checks only that `threads` can be queried. Later requests can fail with a generic database error if required tables or columns are absent. The current schema reference includes retraction fields absent from this older dump; that is a compatibility example, not proof that the October 10 documentation was wrong.

- [ ] State whether a downloaded read-model database is a supported external contract or a best-effort snapshot, and document the compatibility promise accordingly.
- [ ] Document schema-version discovery, snapshot/rebuild time, required tables and columns, joins, ordering, nullability, and JSON fields in a consumer-facing entry point.
- [ ] Explain visibility rules, including hidden posts, identity/bootstrap threads, and retractions; distinguish an archival inspector from a UI reproducing the site's presentation rules.
- [ ] Specify how a consumer should reject incompatible dumps or degrade optional views, with an actionable error naming the incompatibility.
- [ ] Include prior-schema and missing-table/column cases in verification for any implemented compatibility behavior.
- [ ] Re-run the experiment against a fresh export and record changes needed since the saved dump.

Completion evidence: a fresh consumer can identify a compatible dump, explain snapshot limitations, and diagnose an incompatible dump without a source-code tour.

## Priority 3 — Preserve findings and improve the next experiment

The experiment has no `FINDINGS.md`, input manifest, or contemporaneous friction log. Successful output proves practical usability for this snapshot, but does not establish docs-only independence, time to first success, or the amount of intervention.

- [ ] Capture a retrospective findings record from available artifacts, labeling retrospective observations and leaving unrecorded provenance or effort unknown.
- [ ] Record the dump's source, download date, hash, schema version, and snapshot time where recoverable. Do not invent missing provenance.
- [ ] For the next run, record the exact prompt, allowed inputs, environment, start/end times, time to first working result, clarification questions, workarounds, and any implementation-source access.
- [ ] Require a findings log during the run, using the existing task prompts and triage template. Classify each finding as documentation, error-message, or API/contract work.
- [ ] Keep experiment artifacts isolated and disposable; bring confirmed findings and links back into this repository.
- [ ] Give the next consumer only the declared inputs and a concrete goal. Record any intervention rather than silently relaxing the experiment.

Completion evidence: the next experiment produces both a working disposable tool and a traceable account of the friction encountered.

## Backlog reconciliation

- [ ] Update the original `todo.txt` entry to reflect completed target identification and the completed first experiment; link this checklist for remaining follow-up.
- [ ] Replace the stale “draft a protocol” follow-up in `ephemeral_testing_targets.md` with a link to the existing task prompts and this checklist.
- [ ] Select the next experiment after recording the first run's findings. The independent canonical-record parser/signature verifier remains the other originally recommended starting target.
- [ ] Scope any selected implementation through [Railspike](../../railspike/RAILSPIKE.md) and obtain the required step approvals before implementation.
