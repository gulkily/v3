# Import planning speed Step 3 development plan

> **Feature plan:** [Step 1](./import_planning_speed_step1_solution_assessment.md) · [Step 2](./import_planning_speed_step2_feature_description.md) · [Step 3](./import_planning_speed_step3_development_plan.md) · [Step 4](./import_planning_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_planning_speed`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55**

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (planning uses the cache). Stage 3 adds a **Signature check cache** entry to `docs/concepts/`.

## Completion Contract

- Normal entry: `./v3 import-instance <host>`, with and without `--dry-run` and `--database-path`.
- End-to-end outcome: same import report as today. A repeat import of zenmemes.com plans in under 2 s in cloud Linux. A cold import starts about 1.4 GPG processes per new signature instead of 4.
- Recovery: `--resume` works unchanged. A missing, locked or unreadable cache means GPG runs again, with the same result.
- Deployment: no data migration. `signature_checks.sqlite3` is derived and safe to delete. Deploy as usual.
- Release: focused tests and the smoke set pass, and the maintainer confirms WSL timings (Stage 4).

## Key Risks

- **High risk:** a reused GPG home accepts a signature from a different key. Early validation: Stage 2 tests with two keys and a forged signature, written before callers use the reuse. Mitigation: one GPG home per expected key and key bytes; keep the `VALIDSIG` fingerprint check on every verify.
- The cache opens beside the wrong database. Early validation: Stage 1 test with an explicit `--database-path`. Mitigation: open it from the same database path the runner publishes to.
- Reports change after the speedup. Early validation: Stage 3 compares saved `report.json` counts before and after. Mitigation: any difference blocks the stage.

## Stage 1

**Estimate: 0.15**

- Goal: import planning reads and fills the existing signature check cache (Option A).
- Dependencies: none.
- Expected changes: `ContentImportRunner::run()` calls `SignatureCheckCache::useBeside($this->databasePath)` before planning, in both normal and dry-run paths. Update the `--dry-run` line in `docs/reference/v3_cli.md`: a preview may also save successful signature checks.
- Verification approach: new runner test with signed labels. The first import adds rows to the cache. A second import, run with a logging stand-in for `gpg` on `PATH`, starts no GPG process. A test with an explicit database path writes the cache beside that file only. Run `ContentImportPlannerTest`, `ImportInstanceCommandTest`, `SignatureCheckCacheTest` and the smoke set.
- Estimate details: driver: a reliable way to count GPG launches in tests; uncertainty: whether dry-run should write the cache or only read it.
- Risks or open questions:
  - Impact: a preview that writes the cache contradicts the dry-run promise. Early warning: CLI doc review. Mitigation: the cache is derived state like lock files, so document it. If the maintainer prefers a read-only preview, add that in this stage.
  - Impact: in-process state from one test leaks into the next. Early warning: run the tests in both orders. Mitigation: call `SignatureCheckCache::disable()` in test cleanup.
- Canonical components/API contracts touched: `SignatureCheckCache.php` reused unchanged; `ContentImportRunner.php` wiring only; no new cache.

## Stage 2

**Estimate: 0.30**

- Goal: uncached checks reuse one GPG home per signer key within a process (Option B).
- Dependencies: Stage 1 (so measurements reflect the cache already working).
- Expected changes: `OpenPgpSignatureVerifier` keeps prepared homes keyed by fingerprint and key-byte hash. The first check per key runs packet inspection, import and key lookup; later checks run only verify. The per-call 10 s budget and output limits stay. Add `OpenPgpSignatureVerifier::releasePreparedKeys(): void`, called at process end and by tests. `ReactionSignature::status()` uses one shared verifier instance instead of a new one per call.
- Verification approach: new verifier tests. A second check with the same key launches one GPG process. A signature from key B fails when verified against prepared key A. Changed key bytes for the same fingerprint get a fresh home. A tampered record fails after a good one. Temp homes are removed on release and after a failure. Run `SignatureBudgetTest`, `ContentImportPlannerTest`, `SignatureCheckCacheTest`, signed-post and reaction tests, and the smoke set.
- Estimate details: driver: isolation tests and cleanup on every exit path; uncertainty: whether other callers (`SignedPostIdentity.php`, `AuthApiController.php`) should share the reuse. This plan leaves them on today's one-off path.
- Risks or open questions:
  - Impact: wrong-signer acceptance (Key Risks). Early warning: the two-key test fails. Mitigation: per-key homes; fingerprint check kept.
  - Impact: temp homes pile up in `/tmp` after a crash. Early warning: test counts directories before and after. Mitigation: release on shutdown; the existing name prefix makes manual cleanup easy.
  - Impact: GPG state from an earlier verify changes a later result, such as trust database updates. Early warning: repeated-verify test with a tampered record. Mitigation: keep `--no-options` and the same arguments as today.
- Canonical components/API contracts touched: `OpenPgpSignatureVerifier::verifyDetached()` keeps its signature and result shape; `ReactionSignature::status()` unchanged for callers. No second verifier class.

## Stage 3

**Estimate: 0.08**

- Goal: confirm the ordinary flow in cloud Linux and record the cache concept.
- Dependencies: Stages 1–2.
- Expected changes: add `docs/concepts/signature_check_cache.md`; link it from `instance_content_import.md`. Add a short note to `docs/runbooks/instance_content_import.md` that repeat imports reuse saved checks.
- Verification approach: on the live zenmemes.com archive, record planning time and GPG launch counts for a cold import into a fresh checkout and a repeat import. Compare `report.json` counts with the baseline run. Check `--resume` after an interruption with the existing deadline test. Run `git diff --check`.
- Estimate details: driver: a clean before/after comparison; uncertainty: live archive changes between runs.
- Risks or open questions: the live archive changes between runs. Mitigation: compare against a baseline run on the same downloaded archive.
- Canonical components/API contracts touched: concept inventory and runbook only.

## Stage 4

**Estimate: 0.03**

- Goal: replay the reported stall on the maintainer's WSL machine.
- Dependencies: Stage 3 and a merged or checked-out branch.
- Expected changes: none in code. Record the maintainer's timings in the Step 4 summary.
- Verification approach: the maintainer runs `time ./v3 import-instance zenmemes.com` twice on WSL. The second run's planning lines should finish in a few seconds. Cloud timings do not satisfy this stage.
- Estimate details: driver: waiting on the maintainer; uncertainty: WSL process start cost.
- Risks or open questions: the cold run is still slow on WSL. Mitigation: record it; a follow-up cycle can target cold imports.
- Canonical components/API contracts touched: none.

## Study snapshots

- Step 3 handoff: 0.55 reaffirmed, scope v1, baseline `05486f22`, R1, P2. Claude single exposed rater, 2026-11-13 UTC. Stage-work originals: S1 0.15, S2 0.30, S3 0.08, S4 0.03. These are separate stage annotations, not a split of the cycle total. R1 hashes not re-verified this session.

*— Claude · 2026-11-13 UTC · Model: Claude Opus 5.5*
