# Import planning speed Step 1 solution assessment

> **Feature plan:** [Step 1](./import_planning_speed_step1_solution_assessment.md) · [Step 2](./import_planning_speed_step2_feature_description.md) · [Step 3](./import_planning_speed_step3_development_plan.md) · [Step 4](./import_planning_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_planning_speed`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

```
wsl@Lenovo:~/v3$ time ./v3 import-instance zenmemes.com
...
Importing content and publishing local views...
Planning public records, dependencies, and conflicts...
```

Seems to stall here for a while, needs more progress report. Also, please start a Railspike to make this much faster.

## Understood Intent

`./v3 import-instance` should finish planning in a few seconds, not tens of seconds. The progress part of the request was handled as separate direct work (see Enhancement requests).

## Problem

Planning verifies every signed thread label and reaction with GPG on every run, even when the result is already known, and each check starts four GPG processes.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (planning only), [Operator command progress](../../concepts/operator_command_progress.md). Candidate new concept for all options: "signature check cache", which today exists only as `SignatureCheckCache.php`.

## Evidence

Measured in a cloud Linux checkout against the live zenmemes.com archive (6,910 source files, 6,044 local files), baseline `05486f22`:

- Planning took 16–17 s. Of that, 16 s went to the first pass over signed `thread-labels` and `post-reactions` (190 signatures).
- Each signature check ran `gpg` four times in a fresh home directory: import, verify, list packets, list keys. One planning run started 760 GPG processes.
- CPU time was about half of wall time, so most of the wait is process startup. WSL starts processes more slowly, so the stall is likely longer there.
- `ReactionSignature.php` already saves successful checks in `signature_checks.sqlite3` beside the read model. Import planning never opens that cache, because only `ReadModelBuilder` and `IncrementalReadModelUpdater` call `SignatureCheckCache::useBeside()`.
- With the cache opened first, the same plan took 1.3 s instead of 16.5 s.
- Failed checks are never cached, by design. Repeated imports re-check any signature that fails every time.

## Option A — Open the existing signature cache during import
**Estimate: 0.15** — driver: choosing the right database path for the import's destination; uncertainty: how many signatures fail and stay uncached.

Point `SignatureCheckCache` at the destination database before planning, and keep it open through publication. Repeat imports skip GPG for every signature already checked locally.

- Smallest change; reuses an existing, tested cache with the same keys.
- Repeat imports from the same source drop from about 17 s to about 1 s of planning.
- A first import into a fresh destination still pays the full GPG cost.
- Signatures that fail are still re-checked on every run.

## Option B — Check many signatures in one GPG session
**Estimate: 0.45** — driver: keeping each key's check isolated while sharing GPG processes; uncertainty: whether one keyring per key can be reused safely across records.

Change `OpenPgpSignatureVerifier.php` to reuse one GPG home directory per signer key for the whole run. Import each key once, then run one verify per signature. Uncached checks drop from four processes to about one.

- Helps the cold case: first imports, fresh destinations and new archives.
- Also speeds `./v3 rebuild` and other callers of `ReactionSignature::status()`.
- Touches signature verification, so it needs careful tests for wrong-key, revoked-key and tampered-record cases.
- A shared keyring must never accept a signature from a key other than the expected fingerprint.

## Option C — Verify OpenPGP signatures in PHP
**Estimate: 1.20** — driver: parsing OpenPGP packets and keys without GPG; uncertainty: coverage of real key types in the archive.

Replace GPG for detached signature checks with PHP code using `sodium` for Ed25519 keys. No processes at all.

- Fastest possible result.
- Large new security surface and a second verification path that could disagree with GPG.
- Likely needs a third-party OpenPGP library, which requires dependency approval.
- Too large for one cycle.

## Recommendation

Option A, then Option B, in one cycle. Option A is the first vertical slice: small, useful alone, and it removes the slowdown for repeat imports. Option B follows as a separate stage to cut the cold cost by about four times. Measure on WSL after Option A before committing to Option B. Reject Option C.

## Enhancement requests

- **Request:** more progress output during planning. **Disposition:** direct work outside this cycle; a small bounded change. **Approval:** requested directly in the same message. **Outcome:** local commit `51732a57` on branch `import-planning-progress` reports each planning phase, a throttled record count with the current record family, and completion lines. It adds `ContentImportPlannerTest::testPlanningReportsEachPhaseWithSourceProgress`.

## Study snapshots

- Intake (after code inspection and profiling; no pristine intake): 0.55 projected total for Option A then B. Baseline `05486f2255c44b72e70e3c181b4cd5780d8ed042`, scope v1, R1, P2; Claude single exposed rater, 2026-11-13 UTC. R1 hashes not re-verified this session.
- Step 1 handoff: 0.55 reaffirmed. Option estimates A 0.15, B 0.45, C 1.20 are separate and not summed.

*— Claude · 2026-11-13 UTC · Model: Claude Opus 5.5*
