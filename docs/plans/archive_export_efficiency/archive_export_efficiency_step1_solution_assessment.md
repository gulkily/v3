> **Feature plan:** [Step 1](./archive_export_efficiency_step1_solution_assessment.md) · [Step 2](./archive_export_efficiency_step2_feature_description.md) · [Step 3](./archive_export_efficiency_step3_development_plan.md) · [Step 4](./archive_export_efficiency_step4_implementation_summary.md)

Debug mode: enabled

Approval: APPROVED — user — 2026-10-26T01:21:01-04:00 — scope v1, recommended Option C (identity fix A sequenced first, then streaming build B); split to A-only permitted if coupling proves large. Explicit "Approved Step 1" in session.

Effort study: `railspike-effort` / `v3/archive_export_efficiency`; [ledger](../effort_estimation_trial.md), standing enrollment P2. Extended estimates: not enabled.

## Original Query

"I want the archive generator to be as efficient or close to as efficient as `tar -cf .. ; gzip`."

Context: production `qdb.us` returns HTTP 500 after ~120s on `GET /downloads/repository.tar.gz` (and this blocks `./v3 import-instance qdb.us`, which downloads that URL). The server log shows `PHP Fatal error: Maximum execution time of 120 seconds exceeded in src/ForumRewrite/Images/ExportIdentity.php on line 40`.

## Understood Intent

Make repository/zip/image archive export cost approximately what a single `tar -czf` pass costs, eliminating the redundant full-tree work that currently makes a large instance exceed the 120s request limit. Preserve the existing integrity guarantees (never serve a partial or stale archive; revision/identity matching) while removing the per-request full-repository hashing and the extra tree copy.

## Problem (measured from code)

Per `/downloads/repository.tar.gz` request, `ImageBackup::export()` currently performs several full passes over the content tree; the `tar -czf` itself is only one of them:

1. **`ExportIdentity::capture()` SHA-256-hashes every file in the repository** (line 40, `self::digest()` in the tree loop) to compute the cache freshness identity. This is the line that fatals at 120s on qdb.us.
2. **`capture()` runs on the cache-hit path too** — `export()`'s first statement is `$cache->open($this->identity(...))`, and `identity()` calls `capture()`. So even a warm cache hit re-hashes the entire repository before it can serve the prebuilt archive. On a large instance the hit path alone exceeds 120s, so *every* download fatals regardless of cache state.
3. `ImageBackup::treeBytes()` walks the whole tree for size/count (also called inside `capture()`).
4. `copyTree()` copies the **entire** repository into a workspace before archiving.
5. `references()` reads and parses every post record.
6. `tar -czf` / `zip` — the actual archive (the one pass the target wants to keep).
7. On publish/open, the whole archive is hashed again (`hash_file`, `hash_update_stream`).

The freshness key is as expensive to compute as the archive itself, and that cost is paid on every request. That is the root cause of the 500s and the import failures.

## Key insight

Canonical records are committed to git — `export()` already requires a clean `git status --porcelain -- records` and captures `git rev-parse HEAD`. **The git revision (HEAD) already uniquely identifies repository content.** The per-file content hash in `ExportIdentity` is largely redundant with HEAD for the records tree. A freshness key built from HEAD (plus the image inventory and a digest of the small set of *generator sources*) identifies the archive without hashing the whole content tree — turning an O(repository bytes) operation into O(1)–O(generator files).

## Option A — Cheap identity from git HEAD + inventory + generator digest (no full-tree content hash)

Replace `ExportIdentity::capture()`'s per-file content hash with: git `HEAD`, the image inventory (already computed), and a hash of the fixed generator-source list it already digests. Keep the existing "clean records working tree" precondition so HEAD is authoritative. Cache hits then cost an O(1) key computation; misses build once.

- Pros: removes the dominant cost on both hit and miss paths; smallest change that fixes the 120s fatal; cache correctness preserved because HEAD + clean-records precondition already guarantees content identity; no change to archive bytes or format.
- Cons: must prove the git-HEAD-based identity is at least as strict as the content hash for the cases that matter (records changes are caught by HEAD + clean-tree; non-records files and image objects need explicit coverage via inventory/snapshot). Cache-invalidation correctness is the real work.
- Rough size: the smallest of the three; the verification surface (freshness equivalence) is the main cost.

## Option B — Stream the archive in one pass; drop the workspace copy

Beyond the identity fix, eliminate `copyTree()` by having `tar`/`zip` archive directly from the source working tree (excluding `.git`) into the cache, rather than copying the tree first and archiving the copy. Approaches the literal `tar -czf` cost.

- Pros: removes pass #4 (full tree copy), the second-largest cost; gets closest to the stated `tar -czf` target.
- Cons: the copy currently exists to (a) add `repository-snapshot.json` / README and (b) archive a stable snapshot while writers are excluded. Archiving the live tree in place needs the writer-lock/`git status` guarantees to hold across the `tar` run, and the injected snapshot files must be handled without a full copy (e.g. `tar` with `--transform`/extra `--append`, or a tmp overlay of just those files). Integrity-under-concurrency is the risk.

## Option C — Combined: cheap identity + streaming build (recommended scope)

Do A and B together as one coherent efficiency cycle: O(1) freshness identity *and* a single-pass archive with no workspace copy, so both hit and miss paths approach `tar -czf` cost. Keep all integrity guarantees (no partial/stale archive, revision match, bounded storage, safe eviction).

- Pros: actually meets the stated target ("close to `tar -cf ; gzip`") for both cache states, not just the identity half; fixes the production outage for downloads and `import-instance`.
- Cons: largest surface; couples the identity redesign with the build-path change. Builds on top of the now-implemented `archive_export_contention` work (reuse/lock/recovery already on `origin/main`), so no merge decision remains — but the changes touch the same files and must not regress that cycle's guarantees.

## Recommendation

**Option C**, sequencing the identity fix (A) first, because A alone resolves the 120s fatal currently taking down all archive downloads and `import-instance` on qdb.us. B then removes the remaining copy pass to reach the `tar -czf` target. If the coupling proves large, split C into A (ship immediately as the outage fix) and B (follow-up to hit the full efficiency target).

Relationship to `archive_export_contention` (**implemented — Step 4 complete on `origin/main`** as of 2026-10-26): that cycle made exports *reuse completed builds, wait a bounded time, and recover safely* under lock contention. Verified by reading `origin/main`: it did **not** change the freshness-identity cost — `ExportIdentity::capture()` still SHA-256-hashes every repository file (line 24/40), and `ImageBackup::export()` still computes that identity on its first line, i.e. on the **cache-hit path too**. So the outage root cause is untouched by that cycle. This efficiency cycle therefore **layers on top** of the shipped contention work rather than merging with it: reuse/lock/recovery stay as-is; only the identity computation (A) and the build pass (B) get cheaper. No merge-vs-sequence decision is outstanding.

Verify: byte-identical archive contents and format before/after; a cache hit serves without hashing the whole tree (instrument/trace the hit path); freshness still invalidates on a records change, a non-records change, an image-object change, a retraction, and a generator-source change; revision-mismatch still returns 409; busy still returns 503; bounded storage/eviction unchanged; `import-instance` against a local fixture round-trips. Measure export wall-time on a large fixture before/after and compare to a bare `tar -czf` baseline.

## Follow ups and scope

In scope: `ExportIdentity`, `ImageBackup::export/build`, and the `ArchiveCache` hit/publish paths, across tar.gz / zip / images. Out of scope: changing archive format or contents, the lock/retry/recovery behavior owned by `archive_export_contention`, and any read-model work. The 120s PHP limit is not to be raised as a fix — the point is to drop below it structurally.

## Effort snapshot and approval

- Cycle `v3/archive_export_efficiency`, scope v1; starting revision: current `main` at branch time; reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-26. Extended estimates not enabled.
- Intake: recorded after reading `ExportIdentity`, `ImageBackup`, `ArchiveCache`, `ArchiveLocks`, `RepositorySnapshot`, the production fatal/log evidence, and `origin/main` after pulling the implemented `archive_export_contention` cycle (confirmed it did not change the full-tree identity hash). No pristine pre-inspection estimate claimed.
- Step 1 handoff projected total (Option C, against R1): **≈0.8** — an identity-model change with a real cache-freshness-equivalence verification surface, plus a streaming-build change with integrity-under-concurrency risk, across three archive formats. Option A alone (the urgent identity fix) is **≈0.35**. Single exposed, non-blind rater; not measured effort, an approval, or a completed outcome.
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` (recorded in this document) before Step 2; recommendation may be revised without advancing.
