# archive_export_efficiency — Railspike debug log

## 2026-10-26 UTC — Step 1 (Solution Assessment)

Created from a production incident on `qdb.us`: `GET /downloads/repository.tar.gz`
returns 500 after ~120s; log shows `PHP Fatal error: Maximum execution time of 120
seconds exceeded in src/ForumRewrite/Images/ExportIdentity.php on line 40`. The same
URL is what `./v3 import-instance qdb.us` downloads, so the import failures reported
earlier tonight share this root cause.

### Diagnosis grounded in code (not inference)
- `ExportIdentity::capture()` line 40 hashes **every file in the repository** to build
  the cache freshness identity.
- `ImageBackup::export()`'s first statement computes `identity()` (→ `capture()`) on the
  **cache-hit** path, so even a prebuilt archive can't be served without a full-tree
  hash first. On a large instance this alone exceeds 120s → all downloads fatal.
- The export does ~4 full tree passes + 2 full-archive hashes per request; `tar -czf`
  is only one of them. The user's target is to get to ~`tar -czf` cost.

### Necessary/useful instructions
- "investigate before asserting": traced `capture()` to the hit path by reading
  `export()`, confirming the claim that *every* download (not just misses) pays the
  full-tree hash. This reframed the fix from "speed up the build" to "the freshness
  key itself is O(repository) and runs on every request." Highest-value finding.
- "diagnose root cause rather than patch": resisted the obvious non-fix (raise
  `max_execution_time`), which would only burn a worker longer; recorded it explicitly
  as out of scope.
- Follow-up/distinct-cycle identity: gave this its own cycle but documented the overlap
  with the already-approved `archive_export_contention` (Step 1 Option B) and proposed a
  sequencing/merge decision before Step 2 rather than silently duplicating scope.
- Approval-in-document convention (adopted in `request_path_cheap_wins`): Step 1 carries
  `Approval: PENDING`; no advance until recorded APPROVED.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates: not enabled; no per-option numeric lines beyond the recommended
  total and the A-alone figure.
- Read-model schema-change checklist: no schema change.

### Uncertain assessments
- The core uncertainty is freshness-equivalence: proving a git-HEAD-based identity is at
  least as strict as the current per-file content hash for records, non-records files,
  image objects, retractions, and generator-source changes. This is the real work of
  Option A and the main risk.
- Option B (drop the workspace copy, stream from the live tree) carries
  integrity-under-concurrency risk; the copy currently also injects snapshot/README
  files, which must be handled without a full copy.
- ≈0.8 for Option C / ≈0.35 for Option A alone; single exposed, non-blind rater; not
  measured effort.

### Recommended path
- Ship Option A (cheap identity) first as the urgent outage fix (unblocks downloads +
  `import-instance`), then reconcile Option B with the `archive_export_contention` cycle
  to reach the full `tar -czf` efficiency target.

## 2026-10-26 UTC — Reviewed origin/main after pull; contention cycle implemented, efficiency still open

Pulled and reviewed `origin/main`. The `archive_export_contention` cycle is
**implemented (Step 4 complete)** — commits `84f14eee` reuse / `cf7cb4e1` bounded
lock / `58c57cd3` busy-vs-conflict / `f9fd15b8` bounded storage+recovery /
`94a55e68` tests / `be31486f` handoff, plus `docs/runbooks/archive_export_recovery.md`.

Verified by reading the shipped code that the **efficiency root cause is untouched**:
- `origin/main:src/ForumRewrite/Images/ExportIdentity.php` still `self::digest()`
  (SHA-256) every repository file (line 24) via `hash_file` (line 40).
- `origin/main:src/ForumRewrite/Images/ImageBackup.php` still calls
  `$cache->open($this->identity(...))` on `export()`'s first line → full-tree hash on
  the cache-hit path too.

So "we already implemented that one" is true for *contention* (409/lock/reuse), but the
120s fatal on qdb.us (and the `import-instance` failure) is the *efficiency* problem,
which is a different cycle. User confirmed (option 1) that the efficiency fix is not
done. Updated Step 1: the merge-vs-sequence question is resolved — this cycle **layers
on top of** the shipped contention work; Option A (cheap git-HEAD identity) is the
direct outage fix. Step 1 remains `Approval: PENDING`.

## 2026-10-26 UTC — Approved Step 1 (Option C, A first); Step 2 drafted

Recorded Step 1 approval in the step document (Option C, identity fix A sequenced
first; A-only split permitted). Drafted Step 2 after it read APPROVED, honoring the gate.

### Necessary/useful instructions
- "investigate before asserting": before writing Step 2, checked exactly what the
  current identity hashes vs. what the clean-tree check guards. Finding: `capture()`
  hashes the **whole tree minus .git** (records/ + tracked README + perms), but the
  precondition only checks `git status --porcelain -- records`. So a naive "HEAD is
  enough" key would miss a dirty non-records file. Step 2 now requires a **whole-tree**
  porcelain signal (or refusal) in the key, not records-only. This is the real
  correctness crux and would have been a stale-archive bug if assumed away.
- Verified the repo root is lean (records/ + README under git), bounding the
  equivalence claim so the cheap key is actually sound.
- Approval-in-document gate: Step 2 written only after Step 1 recorded APPROVED.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates: off. Read-model schema-change checklist: no schema change.

### Uncertain assessments
- Option B (single-pass build, no copyTree) integrity-under-concurrency is the main
  open risk; Step 2 keeps a minimal-overlay fallback (inject only snapshot/README)
  rather than a full copy if in-place archiving can't guarantee integrity cheaply.
- ≈0.8 (C) / ≈0.35 (A alone). Single exposed, non-blind rater.

Step 2 carries `Approval: PENDING`; no branch/commit/implementation until it reads APPROVED.

## 2026-10-26 UTC — Approved Step 2; Step 3 (Development Plan) drafted

Recorded Step 2 approval in its document, then drafted the four-stage Step 3 after it read APPROVED.

### Necessary/useful instructions
- "investigate before asserting" again paid off: reading `ImageStore::inventory()` →
  `inspect()` revealed a **second** hidden O(bytes) cost — the inventory `hash_file`s
  every image, so `capture()` re-hashes all images even after the tree hash is removed.
  Since image filenames ARE the content sha256 (regex-enforced on write and checked on
  inventory mismatch), the cheap identity can trust filenames+sizes instead of
  re-hashing. Without this read, Stage 1 would have "removed the full-tree hash" and
  still timed out on an image-heavy instance. Now explicit in Stage 1 + Key Risks.
- Staged so A (Stages 1–2) is the shippable outage fix and B (Stage 3) is separable —
  matches the Step 1 "ship A first if B proves large" option.
- Approval-in-document gate honored.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates / per-stage values: off. Schema-change checklist: none.

### Uncertain assessments
- Stage 1's freshness-equivalence matrix is the acceptance crux; the whole-tree porcelain
  signal must be both strict (catches dirty non-records files) and cheap (one git status
  over a small tree). Stage 3's in-place archiving vs. minimal-overlay is the other open
  design choice, resolved at implementation by whichever preserves integrity cheaply.
- ≈0.8 (C) / ≈0.35 (A). Single exposed, non-blind rater.

Step 3 carries `Approval: PENDING`; no branch/commit/implementation until it reads APPROVED.

## 2026-10-26 UTC — Stage 1 decision: identity ignores permissions and untracked-file content (user)

While implementing Stage 1, the old `ExportIdentity` was found to make identity
sensitive to (1) file permissions and (2) untracked-file *content* — both of which
the cheap git-HEAD + porcelain key does not reproduce. Surfaced as a decision.

User direction: **permissions should not matter (data-only archive); untracked files
should not matter either.** Confirmed against the archive's purpose: it is the
committed content snapshot (export captures `git HEAD`, refuses dirty records, injects
`repository-snapshot.json`). Decision:

- Identity = `git rev-parse HEAD` + `git status --porcelain` (dirtiness present →
  distinct key / refuse) + image inventory by content-addressed filename + size +
  generator-source digest. **No file permissions. No per-untracked-file content hash.**
- Consistency requirement carried into Stage 3 (Option B): the archive must contain
  **committed content only** (e.g. `git archive HEAD` semantics), so untracked files
  are neither archived nor identity-affecting. This removes today's inconsistency where
  `copyTree(excludeGit:true)` swept untracked files into the archive.
- `ArchiveExportTest::testIdentityIncludesUntrackedBytesPermissionsAndImages` encoded
  the old (now-rejected) guarantees; it is rewritten to the new semantics rather than
  preserved. This is a deliberate, user-approved narrowing, not an accidental regression.

Net effect: strictly cheaper identity with guarantees aligned to "committed data only."

## 2026-10-26 UTC — Approved Step 3 (full Option C); implemented Stages 1–3 + Step 4

Recorded Step 3 approval ("just rip it" → full Option C). Branch
`feat/archive-export-efficiency`, planning commit `696df0d4`; stage commits
`70aeb083` (S1), `3928576d` (S2), `b22b3868` (S3). Full suite zero new failures.

### Necessary/useful instructions
- "diagnose root cause rather than patch / step back after repeated failure":
  Stage 3 failed tests three times across two mechanisms. Rather than a fourth
  patch, stepped back and found the actual root cause — `git archive` omits empty
  directories, so a posts-less repo lost `records/` — a real product edge, fixed
  directly. The earlier flailing on the test harness was the signal to stop.
- "surface genuine blockers / don't drift from the plan": caught myself
  mid-pivot implementing mechanism (ii) without approval when the user only asked
  a question; stopped and re-confirmed. User then approved (ii) and asked to record
  pivots in Step 4 — done.
- Baseline-differencing the full suite: the new-looking `CheckoutsCommandTest`
  failure was verified pre-existing/environmental (passes in isolation; branch
  touches only Images/*), preventing a false "I broke it" conclusion before merge.

### Redundant/unnecessary instructions
- No supported findings.

### Uncertain assessments
- Retrospective ≈0.9 vs ≈0.8 projected: the Stage 3 mechanism churn (overlay
  contracts → tar-in-lock harness interaction → empty-dir edge) was
  under-weighted. The README-override and snapshot-fingerprint contracts are the
  entanglement the plan should have flagged earlier.

### Deferred follow-up (user)
- Keep the canonical archive README committed in the repo so export need not
  override a tracked README. Preferred long-term; deferred, filed to backlog.

### Pivots (summary; full detail in Step 4)
1. Identity narrowed to committed-data-only (no perms, no untracked content) — user decision.
2. Build mechanism: approved overlay (`git archive --add-file`) → `git archive | tar -x` → `git archive -o + PharData` (final), to preserve export contracts and the kill/conflict test harness without a tar shell-out in staging.
