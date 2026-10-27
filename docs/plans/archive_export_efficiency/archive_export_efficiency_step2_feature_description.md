> **Feature plan:** [Step 1](./archive_export_efficiency_step1_solution_assessment.md) · [Step 2](./archive_export_efficiency_step2_feature_description.md) · [Step 3](./archive_export_efficiency_step3_development_plan.md) · [Step 4](./archive_export_efficiency_step4_implementation_summary.md)

Debug mode: enabled

Approval: APPROVED — user — 2026-10-26T01:26:19-04:00 — scope v1 (explicit "Approved Step 2" in session)

Effort study: `railspike-effort` / `v3/archive_export_efficiency`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled.

## Feature

Make archive export (`/downloads/repository.tar.gz`, `.zip`, `/downloads/images.tar`) cost approximately a single `tar -czf` pass instead of several full-repository passes, so a large instance (qdb.us) stops exceeding the 120s request limit. Two behavior-neutral efficiency changes, layered on top of the already-shipped `archive_export_contention` reuse/lock/recovery work:

- **A (identity):** compute the cache freshness key from `git HEAD` + a whole-tree clean check + the image inventory + the generator-source digest, instead of SHA-256-hashing every file in the repository. This is the outage fix — it removes the dominant cost on both the cache-hit and cache-miss paths.
- **B (build pass):** produce the archive in a single pass from the source tree instead of copying the whole tree into a workspace first.

Archive bytes, format, filenames, and all integrity guarantees (never serve a partial or stale archive; revision/identity matching; bounded storage; safe eviction; busy/conflict responses) are unchanged.

## Problem (measured)

Per download, `ImageBackup::export()` does ~4 full tree passes + 2 full-archive hashes; `tar -czf` is one of them. The dominant, outage-causing cost is `ExportIdentity::capture()`, which SHA-256-hashes **every file under the repo except `.git`** (plus perms), and is computed on `export()`'s **first line** — so even a cache **hit** re-hashes the whole repository before serving a prebuilt archive. On qdb.us that single pass exceeds `max_execution_time=120` → `PHP Fatal error … ExportIdentity.php on line 40` → HTTP 500 after ~120s, and the same failure blocks `./v3 import-instance qdb.us`.

## Freshness-equivalence requirement (the core correctness surface)

The new identity must invalidate in **exactly** the cases the current full-tree hash does. Established by reading the code:

- The archive content is the working tree **excluding `.git`** (`copyTree(excludeGit: true)`), i.e. `records/` + a tracked top-level `README.md` on current instances. The identity currently hashes that same set plus file permissions.
- The existing clean-tree precondition is `git status --porcelain -- records` — it guards **only `records/`**. A `HEAD`-only key would therefore miss an uncommitted change to a non-`records` tracked file (e.g. top-level `README.md`) or an untracked file that would be archived.

Therefore the identity must be: **`git rev-parse HEAD`** + a **whole-tree** porcelain cleanliness signal (`git status --porcelain` with no `-- records` restriction, excluding nothing that the archive includes) + the **image inventory** (already computed cheaply) + the **generator-source digest** (the existing small fixed file list). If `git status --porcelain` reports anything, the key must differ from any committed-state key (or the export must refuse, matching today's "uncommitted records" refusal generalized to the archived set). This keeps the key O(small git status) instead of O(repository bytes) while remaining at least as strict as the per-file hash for every archived file.

## User stories

- As anyone downloading a content/image archive on a large instance, I want it to start promptly instead of timing out at 120s, with the same bytes I'd get today.
- As an operator running `./v3 import-instance <host>`, I want the source archive to download rather than fail, so imports work against large instances.
- As a maintainer, I want the freshness key to be provably as strict as the old content hash, so cache correctness (never stale, never partial) is preserved.

## Core requirements

- **A1:** `ExportIdentity::capture()` no longer hashes every repository file. It derives identity from `HEAD`, a whole-tree porcelain clean signal, the image inventory, and the generator-source digest. Same return type (a `sha256` string); a changed derivation is expected to change the key namespace (bump the internal `export-v1` tag to force a one-time cache miss after deploy).
- **A2:** The clean-tree precondition that currently checks `-- records` is widened to cover everything the archive includes (or the key incorporates the porcelain output), so a dirty non-`records` file cannot produce a stale archive.
- **A3:** `identity()` on the cache-**hit** path is cheap (no full-tree hash). A warm hit serves without O(repository) work.
- **B1:** `ImageBackup::build()` assembles the archive without first copying the whole tree into `work/repository`; the injected `repository-snapshot.json` and README are added to the archive without a full copy (e.g. `tar` append/transform or a minimal overlay of just those files). Images-tar build (PharData) is adjusted equivalently if it shares the copy.
- **Invariant:** byte-identical archive contents/format/filenames; identical revision/conflict (409) and busy (503) behavior; bounded storage/eviction and reuse semantics from the contention cycle unchanged; no schema change; the 120s limit is not raised.

## Delivery scope

- Work type: **application change** in `src/ForumRewrite/Images/` (`ExportIdentity`, `ImageBackup`, touching `ArchiveCache` only where the key/hit path requires). Across tar.gz / zip / images.
- Not documentation-only (though the export recovery runbook may get a note). No read-model schema change.

## Completion boundary

- Normal entry: `GET /downloads/repository.tar.gz` (and `.zip`, `images.tar`), and `./v3 import-instance` against an instance exposing that URL.
- End-to-end outcome: the archive downloads with byte-identical content, in roughly `tar -czf` time; a warm cache hit serves without hashing the whole tree; a large instance no longer fatals at 120s.
- Recovery: a repository changed mid-request still yields 409; a concurrent build still yields the contention cycle's bounded-wait/503; a dirty working tree still refuses rather than serving a stale archive.
- Release condition: freshness-equivalence proven (below); byte-identical archives before/after; measured export wall-time on a large fixture close to a bare `tar -czf` baseline; affected export tests green; full-suite delta zero new failures vs. baseline.

## Known user or environment

Production `qdb.us` is the motivating instance (large imported archive). No per-user specifics required.

## Risks

- **High: freshness equivalence.** A HEAD-based key that misses a case the content hash caught would serve a stale archive. Earliest validation: a test matrix asserting the new key changes on — a `records` change, a non-`records` tracked change (README), an untracked archived file, an image-object change (inventory), a retraction, and a generator-source change; and does **not** change across identical committed state. Mitigation: incorporate the whole-tree porcelain signal + inventory + generator digest; refuse on dirty tree as today.
- **Medium: single-pass build integrity under concurrency (B).** Archiving the live tree instead of a snapshot copy must keep the writer-lock / clean-tree guarantees across the `tar` run, and inject snapshot/README files without a copy. Earliest validation: concurrent-write test during export; verify injected files present and archive still matches identity. Mitigation: keep the existing revalidation of identity after build under the writer lock; if in-place archiving can't guarantee integrity cheaply, keep a minimal overlay (snapshot/README only) rather than a full copy.
- **Low: deploy cache invalidation.** Changing the key derivation invalidates existing cached archives once. Mitigation: bump the `export-v1` namespace tag deliberately; first post-deploy request rebuilds.

## Shared component inventory

- `src/ForumRewrite/Images/ExportIdentity.php` — `capture()` reworked (A). Core of the change.
- `src/ForumRewrite/Images/ImageBackup.php` — `identity()` becomes cheap (A3); `build()` drops the full `copyTree` (B). `export()` flow otherwise unchanged (reuse/lock from the contention cycle stays).
- `src/ForumRewrite/Images/ArchiveCache.php` — only if the hit/key path needs adjustment; reuse/eviction/publish semantics unchanged.
- `src/ForumRewrite/Images/RepositorySnapshot.php` — `write()` still injects the snapshot manifest; its own `fingerprint()` (records-only hash) is separate and out of scope unless B's injection path touches it.
- No route, UI, or API surface change; no change to `InstancePageController` exception mapping.

## Success criteria

- `ExportIdentity::capture()` performs no per-file content hash of the repository; a large-fixture export completes well under 120s and close to a `tar -czf` baseline.
- A cache hit serves the prebuilt archive without O(repository) work (traced/instrumented).
- Freshness-equivalence matrix passes (all six change cases invalidate; identical committed state does not).
- Byte-identical archive contents/format vs. pre-change for tar.gz, zip, images.
- `./v3 import-instance` round-trips against a local fixture; 409/503 behavior unchanged; full-suite delta zero new failures.

## Effort snapshot and approval

- Cycle `v3/archive_export_efficiency`, scope v1 (Option C, A first); reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-26. Extended estimates not enabled.
- Step 2 handoff projected total: **≈0.8 reaffirmed** (A ≈0.35 alone). Reading the code sharpened the freshness-equivalence surface (whole-tree vs. records-only clean check) but added no scope. Not measured effort, an approval, or a completed outcome.
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping; possibly a note in `docs/runbooks/archive_export_recovery.md`. Planning writes remain uncommitted.
- Await `Approved Step 2` (recorded in this document) before Step 3; requirements may be revised without advancing. If B's integrity-under-concurrency proves large, ship A first (the outage fix) and split B out.
