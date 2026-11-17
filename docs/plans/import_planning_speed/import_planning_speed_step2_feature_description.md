# Import planning speed Step 2 feature description

> **Feature plan:** [Step 1](./import_planning_speed_step1_solution_assessment.md) · [Step 2](./import_planning_speed_step2_feature_description.md) · [Step 3](./import_planning_speed_step3_development_plan.md) · [Step 4](./import_planning_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_planning_speed`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.55

## Problem

`./v3 import-instance` spends most of its planning time checking OpenPGP signatures with GPG. It repeats checks that already succeeded, and each new check starts four GPG processes.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (planning and publication), [Operator command progress](../../concepts/operator_command_progress.md) (no change expected). Proposed inventory addition: **Signature check cache**, which records successful checks beside the read model so later imports, rebuilds and requests reuse them. Sources: `SignatureCheckCache.php`, `ReactionSignature.php`.

Earlier imports leave the cache empty. Planning runs before the cache is opened, so its results stay in memory only. The later read-model refresh finds every signature in that memory and never reaches the disk cache. A standalone `./v3 rebuild` does fill it.

## User stories

- As the operator, I want a repeat import from the same instance to finish planning in a few seconds, so that pulling new content is quick.
- As the operator, I want a first import into a fresh checkout to take a fraction of today's time, so that setting up a mirror is not a long wait.
- As the operator, I want imports to accept and reject exactly the same signatures as today, so that faster planning never weakens verification.

## Core requirements

- Import planning reuses the existing signature check cache for the destination's read model, in both normal and `--dry-run` runs.
- A cache that is missing, locked or unreadable falls back to checking with GPG; it never causes a failure or a different result.
- Uncached checks need far fewer GPG processes per signature (Option B).
- Every signature still verifies against its expected fingerprint only. A wrong key, a tampered record or a mismatched fingerprint is rejected as today.
- Import results (counts, conflicts, review causes) match the current code for the same inputs.

## Delivery scope

- Work type: application change.
- In scope: import planning's use of the cache, and how `OpenPgpSignatureVerifier.php` runs GPG for detached checks.
- Out of scope: verifying signatures without GPG, caching failed checks, changing which records need signatures, and the progress output (done separately in commit `51732a57`).

## Completion boundary

- Normal entry: `./v3 import-instance <host>` and `--dry-run`, with default or explicit `--database-path`.
- End-to-end outcome: the import completes with the same report as before, and planning time drops as in the success criteria.
- Recovery: `--resume` after an interrupted import still works. Deleting `signature_checks.sqlite3` only makes the next run slower.
- Release condition: focused tests and the smoke set pass, and the maintainer confirms timings on WSL.

## Known user or environment

- User and environment: the maintainer on WSL (`wsl@Lenovo:~/v3`), importing zenmemes.com into the default local repository.
- Reported mismatch: planning stalls with no visible progress for a long time.
- Next action: run `time ./v3 import-instance zenmemes.com` twice after the change. The first run warms the cache.
- Verification: the planning lines show the elapsed time per phase. The second run's planning should finish in a few seconds.
- Evidence: the maintainer's WSL timings. Cloud Linux timings do not satisfy this condition; they are recorded as supporting evidence only.

## Risks

- **A shared GPG session accepts the wrong signer.** Impact: a forged label or reaction imports as verified. Earliest validation: tests with a second key in the same session before any caller uses it. Mitigation: keep one GPG home per expected key, and keep the fingerprint check.
- **The cache opens the wrong file.** Impact: an import with `--database-path` reads or writes another instance's cache. Earliest validation: a test with an explicit database path. Mitigation: open the cache beside the same database the import publishes to.
- **Cold-case gain is smaller than expected on WSL.** Impact: Option B costs more than it saves. Earliest validation: the maintainer's WSL timing after the cache slice. Mitigation: Option B is a separate stage that can be dropped.
- **Stale per-process state between tests.** Impact: tests pass or fail depending on order. Earliest validation: run the signature and import tests together. Mitigation: reuse `SignatureCheckCache::disable()` and clear any new session state per run.

## Shared component inventory

- `src/ForumRewrite/Security/SignatureCheckCache.php`: the existing persistent cache of successful checks. Reuse as is. Import only needs to open it, as `ReadModelBuilder.php` and `IncrementalReadModelUpdater.php` already do.
- `src/ForumRewrite/Security/ReactionSignature.php`: the single entry for label, reaction and retraction signature checks, with an in-process cache. Reuse; no new entry point.
- `src/ForumRewrite/Security/OpenPgpSignatureVerifier.php`: runs GPG in a fresh home directory per check. Extend it to reuse one home per key within a run, rather than adding a second verifier.
- `src/ForumRewrite/Import/ContentImportRunner.php` and `scripts/import_instance.php`: know the destination database path. One of them opens the cache.
- `tests/SignatureCheckCacheTest.php`, `tests/ContentImportPlannerTest.php`, `tests/SignatureBudgetTest.php`: existing tests to extend.

## Simple user flow

1. The operator runs `./v3 import-instance zenmemes.com`.
2. Download and extraction run as before.
3. Planning checks local and source records. Signatures already checked are read from the cache; new ones are checked with GPG and saved.
4. The import installs records and refreshes the read model, which reuses the same cache.
5. A later import of the same instance skips GPG for every unchanged signed record.

## Success criteria

- On the zenmemes.com archive in cloud Linux, a repeat import's planning drops from about 17 s to under 2 s. A warm-cache experiment already measured 1.0 s with no GPG processes.
- A first import into a fresh destination starts at most about 1.5 GPG processes per new signature, down from four.
- The maintainer's WSL repeat-import planning finishes in a few seconds.
- Import reports for the same inputs match before and after, including invalid and conflict counts.

## Enhancement requests

- **Request:** more progress output during planning. **Disposition:** direct work outside this cycle. **Approval:** requested directly. **Outcome:** local commit `51732a57` on branch `import-planning-progress`; not yet pushed.

## Study snapshots

- Step 2 handoff: 0.55 reaffirmed, scope v1, same baseline, R1, P2. Claude single exposed rater, 2026-11-13 UTC. The new measurement (no GPG at all with a warm cache) confirms Option A's gain and adds no scope.

*— Claude · 2026-11-13 UTC · Model: Claude Opus 5.5*
