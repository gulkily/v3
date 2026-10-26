> **Feature plan:** [Step 1](./archive_export_efficiency_step1_solution_assessment.md) · [Step 2](./archive_export_efficiency_step2_feature_description.md) · [Step 3](./archive_export_efficiency_step3_development_plan.md) · [Step 4](./archive_export_efficiency_step4_implementation_summary.md)

Debug mode: enabled

Approval: APPROVED — user — 2026-10-26T02:29:54-04:00 — scope v1, full Option C (A+B, Stages 1–4) as written. Explicit "Approved Step 3" in session ("just rip it"). A-only re-scope was offered and not taken.

Effort study: `railspike-effort` / `v3/archive_export_efficiency`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled (no per-stage stage-work values).

## Completion Contract

- Normal entry: `GET /downloads/repository.tar.gz` (`.zip`, `images.tar`); `./v3 import-instance` against an instance exposing that URL.
- End-to-end outcome: byte-identical archive content/format in ~`tar -czf` time; cache hits serve without O(repository) work; a large instance no longer fatals at 120s.
- Required recovery: repository changed mid-request → 409; concurrent build → contention cycle's bounded wait/503; dirty working tree → refuse rather than serve stale.
- Release condition: freshness-equivalence matrix passes; byte-identical archives before/after (tar.gz/zip/images); measured large-fixture export close to a bare `tar -czf`; affected export tests green; full-suite delta zero new failures vs. baseline; 120s limit not raised.

## Key Risks

- **Freshness equivalence (high).** New key must invalidate in exactly the cases the full-tree + inventory hash did. Validated by the Stage 1 matrix. Mitigation: key = `HEAD` + whole-tree porcelain signal + inventory-by-filename + generator digest; refuse on dirty tree.
- **Hidden O(bytes) cost in the inventory (high, newly found).** `ImageStore::inventory()` calls `inspect()` → `hash_file()` per image, so identity re-hashes every image even after the tree-hash is removed. The image filenames *are* their content sha256 (`^[a-f0-9]{64}\.(png|jpg)$`, verified on write and on the inventory mismatch check). Mitigation: the identity must derive image identity from the **filenames + sizes via `scandir`**, not a content re-hash. Do **not** change `inventory()`'s own integrity role on the build path; add a cheap identity-only listing.
- **Single-pass build integrity under concurrency (medium, Stage 3).** Archiving the live tree instead of a snapshot copy must preserve the writer-lock/clean-tree guarantees and still inject `repository-snapshot.json` + README. Mitigation: minimal-overlay fallback (inject only those files) if in-place proves unsafe; keep the post-build identity revalidation under the writer lock.
- **Deploy cache invalidation (low).** New derivation invalidates existing cached archives once; bump the `export-v1` namespace tag deliberately.

## Stage 1 — Cheap freshness identity (Option A; the outage fix)

- Goal: `ExportIdentity::capture()` computes identity without hashing repository files or re-hashing images.
- Dependencies: none.
- Expected changes:
  - Replace the per-file tree hash in `capture()` with: `revision` (already passed in), a **whole-tree** `git status --porcelain` cleanliness signal (widen beyond `-- records`; incorporate its output into the key or refuse when non-empty), an **image identity derived from filenames+sizes** (cheap `scandir`, not `hash_file`), and the existing generator-source digest. Bump the internal `export-v1` tag to `export-v2` to force a one-time post-deploy cache miss.
  - Ensure `ImageBackup::identity()` (hit path, `export()` first line) uses this cheap `capture()` so a warm hit does no O(bytes) work. Keep the existing "uncommitted records" refusal, generalized to the whole archived tree.
- Verification: **freshness-equivalence matrix** — the key changes on each of: a `records` change, a non-`records` tracked change (top-level README), an untracked archived file, an image add/remove/replace (inventory), a retraction, a generator-source change; and is **unchanged** across two captures of identical committed state. A trace/timer asserts a cache hit performs no per-file content hash (no `hash_file` over repo/images) and completes in O(git status) time. Existing `ArchiveExportTest`, `RepositoryContentExportTest`, `RepositoryArchiveValidationTest`, `ImportInstanceCommandTest` still pass.
- Canonical components touched: `src/ForumRewrite/Images/ExportIdentity.php`, `ImageBackup.php` (`identity()`), a cheap image-listing helper (new, in `ImageStore` or `ExportIdentity`); new `tests/ExportIdentityTest.php`.

## Stage 2 — Confirm A fixes the outage end-to-end

- Goal: prove a large instance exports within the time budget and `import-instance` round-trips, with byte-identical output.
- Dependencies: Stage 1.
- Expected changes: none to source; verification + a large synthetic fixture (many/large files) in a test.
- Verification: build a large temp repository fixture; measure `ImageBackup::export('tar.gz')` wall-time before (full-tree hash) vs. after (cheap identity), and against a bare `tar -czf` baseline; assert the archive bytes are identical to the pre-change build for the same content; run `import-instance` against a local server serving the fixture archive and confirm round-trip. (This stage is the acceptance of the A half; if A alone is being shipped as the urgent fix, the cycle can pause here.)
- Canonical components touched: tests only.

## Stage 3 — Single-pass build without the full tree copy (Option B)

- Goal: `ImageBackup::build()` assembles the archive without `copyTree(...)` of the whole repository; inject snapshot/README without a full copy.
- Dependencies: Stages 1–2.
- Expected changes: for tar.gz/zip, archive from the source tree (excluding `.git`) directly, adding `repository-snapshot.json` and the README via a minimal overlay (stage only those two files, append/transform into the archive) rather than copying every file first. Preserve the existing pre/post-build identity revalidation under `forum-rewrite.lock`. Keep the images-tar (PharData) path equivalent (it already adds files individually; ensure no redundant copy). Retain all size/count bounds.
- Verification: byte-identical archive contents vs. Stage 2's post-A build (so B changes cost, not output); the injected files are present and correct; a concurrent-write-during-export test still yields 409/conflict (no torn archive); `ArchiveExportTest`/`RepositoryContentExportTest` pass. Measure wall-time now approaches `tar -czf`.
- Canonical components touched: `src/ForumRewrite/Images/ImageBackup.php` (`build()`); possibly `RepositorySnapshot`/`RepositoryArchiveReadme` injection path.

## Stage 4 — Full verification + runbook note

- Goal: confirm the whole batch is behavior-neutral and record operator-facing note.
- Dependencies: Stages 1–3.
- Expected changes: none to export source; a note in `docs/runbooks/archive_export_recovery.md` that identity is now git-HEAD-based (no full-tree hash) if operator guidance references it.
- Verification: full affected export suite together; `./v3 rebuild`/download smoke if relevant; full `./v3 test` baseline-differenced (zero new failures); record before/after export timings and the `tar -czf` comparison in Step 4.
- Canonical components touched: docs only.

## Effort snapshot and approval

- Step 3 handoff projected total: **≈0.8 reaffirmed** (A ≈0.35). Reading `ImageStore::inventory()`/`inspect()` surfaced a second hidden O(bytes) cost (per-image re-hash) the identity fix must also avoid — accounted for in Stage 1, no scope added. Single exposed, non-blind rater; UTC 2026-10-26; not measured effort, an approval, or a completed outcome.
- Stages 1–2 constitute the shippable outage fix (Option A); Stage 3 (Option B) reaches the full `tar -czf` target and may be split out if its concurrency surface proves large.
- Documentation boundary: this cycle's plans/debug log, a possible runbook note, and ledger bookkeeping. Planning writes remain uncommitted until the implementing commits.
- Await `Approved Step 3` (recorded in this document) before creating the feature branch, the planning commit, or implementing.
