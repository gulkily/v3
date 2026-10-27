> **Feature plan:** [Step 1](./archive_export_efficiency_step1_solution_assessment.md) · [Step 2](./archive_export_efficiency_step2_feature_description.md) · [Step 3](./archive_export_efficiency_step3_development_plan.md) · [Step 4](./archive_export_efficiency_step4_implementation_summary.md)

Debug mode: enabled

Approval: N/A — Step 4 is the implementation record; Steps 1–3 are APPROVED for scope v1 (full Option C).

Effort study: `railspike-effort` / `v3/archive_export_efficiency`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled.

# Step 4 — Implementation Summary

Feature branch: `feat/archive-export-efficiency`. Planning commit: `696df0d4`.

## Outcome

Fixes the production outage: `GET /downloads/repository.tar.gz` (and `.zip`, `images.tar`) no longer SHA-256-hashes the whole repository per request, so large instances (qdb.us) stop hitting the 120s PHP fatal in `ExportIdentity`. `./v3 import-instance` against such instances is unblocked. Export cost is now ~git-archive + compression.

## Stage 1 — cheap git-HEAD identity (A) — `70aeb083`
- `ExportIdentity::capture()` derives identity from `git HEAD` + whole-tree `git status --porcelain` + image inventory (by content-addressed filename+size) + generator-source digest; no per-file or per-image content hash. `ImageStore::identityListing()` added for the cheap hit path. Namespace tag `export-v1`→`export-v2`.
- Per user decision, identity is **committed-data-only**: file permissions and untracked-file content no longer affect it. Rewrote the identity test accordingly.

## Stage 2 — acceptance of A — `3928576d`
- Added a large-content (50 records + 4 MiB file) test: a miss builds, a second request reuses the warm archive without hashing the content, and a new commit forces a fresh build.

## Stage 3 — single-pass staging (B) — `b22b3868`
- `ImageBackup::build()` stages the archive content from git's object store (`git archive -o <tmp>.tar` + `PharData` extract) instead of a recursive working-tree copy. Committed-content-only; existing injection (image refs + legacy sidecars, snapshot manifest, recipient README override) runs against the staged tree unchanged. Recreates an empty `records/` after staging (git omits empty dirs) so a repo with all posts removed still exports.

## Pivots during implementation (recorded per request)

This stage pivoted twice; both are documented here and in the debug log so the history is auditable.

1. **Identity semantics narrowed (Stage 1).** The original `ExportIdentity` made identity sensitive to file permissions and untracked-file *content*. The cheap git-based key cannot cheaply reproduce those. User decided permissions and untracked files should not matter (data-only, committed-content archive). The identity test was rewritten to the new, intentionally weaker-but-correct semantics. Not an accidental regression — a deliberate, approved narrowing.

2. **Build mechanism: overlay → `git archive | tar -x` → `git archive` + PharData (Stage 3).**
   - Step 3 as approved specified **`git archive` + `--add-file` overlay** (no staged tree). On implementation this collided with two real contracts: the archive's README must *override* the repo's tracked README (recipient guide, not the instance's own), and `repository-snapshot.json`'s `records_sha256` must match the extracted `records/` (which includes generated legacy sidecars). The overlay composition made both fragile (double README; fingerprint-source mismatch). 9/… tests failed.
   - Pivoted to **(ii): stage the committed tree, then inject exactly as before** — preserving every contract — using `git archive | tar -x`. This was a mechanism change from the approved plan; the user approved it explicitly ("git archive | tar -x is fine") and asked that the pivots be recorded here.
   - A sub-pivot: `git archive | tar -x` added a second `tar` invocation *inside* the writer lock, which broke the kill/conflict test's single-`tar`-gate harness (deadlock or wrong-step kill). Resolved by staging with **`git archive -o <tmp>.tar` + PharData extraction** (no `tar` shell-out), leaving the compression as the sole external `tar` — so the existing test harness needed **no** change (the attempted test edits were reverted).
   - Root cause of the final failing test turned out to be a genuine product edge, not the harness: `git archive` omits empty directories, so a repo with all posts removed produced no `records/` and failed snapshotting. Fixed by recreating `records/` after staging.

## Deferred follow-up (user)

- **Keep the canonical archive README committed in the repository** so the export need not override a tracked README at build time. The user prefers this as the longer-term design; deferred out of this cycle. To be filed as a backlog item alongside the export work. (Would remove the README-override step entirely.)

## Verification

- `ArchiveExportTest` 10/10 (identity, reuse, concurrency, kill/conflict-no-publish, busy/409/500, instance-downloader round-trip).
- Export/import/restore/legacy group: `ArchiveExportTest RepositoryContentExportTest RepositoryArchiveValidationTest RepositoryArchiveImportCommandTest LegacyTimestampImportTest ImportInstanceCommandTest ContentImportPlannerTest` — 42/42.
- Full `./v3 test`: 1528 passed, 8 failed — all pre-existing on baseline (AgentIdentityService, ApprovalSeedCommand, FeatureFlagChangePage, two LocalAppSmoke, PlatformDocsStatic, ReadModelBuilderTiming, WriteApiSmoke::HistoricalReactionSignatures; plus the slow real-git `CheckoutsCommandTest::testRealGitOperatorAcceptance` which passes in isolation and is environmental). **Zero new failures from this cycle.**
- Performance (measured earlier on a 60 MB / 5001-file synthetic repo): bare `git archive | gzip` ≈0.45s; legacy-date detection read-pass ≈0.035s; old `cp -r + tar` ≈0.57s. The 120s fatal was the removed full-tree hash, not the copy — Stage 1 alone fixed the outage; Stage 3 is the memory/streaming + committed-only improvement.

## Completion Contract — result

- Normal entry / outcome: met — downloads and import work; no full-repository hash; identity cheap on hit and build paths.
- Recovery: met — 409 on revision mismatch, 503 on busy, refusal on dirty records, workspace cleanup on kill/throw, empty-`records/` repo still exports.
- Release condition: met — export contracts byte-equivalent (file set + contents + snapshot/README), 42/42 focused, zero new full-suite failures.

## Retrospective (study closeout)

- Scope v1 / Option C delivered across three stages. Two mechanism pivots in Stage 3 (above); no scope change to the *goal*. Retrospective whole-cycle ≈0.9 — above the ≈0.8 projection, driven by the Stage 3 mechanism churn (overlay contracts, test-harness interaction, empty-dir edge) that the plan under-weighted. Single exposed, non-blind rater; UTC 2026-10-26. Not measured effort or an independent rating.
- Maintainer comfortable-fit: pending review/merge.
