> **Feature plan:** [Step 1](./history_purge_step1_solution_assessment.md) · [Step 2](./history_purge_step2_feature_description.md) · Step 3 · [Step 4](./history_purge_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/history_purge`; [ledger](../effort_estimation_trial.md#cycle-v3history_purge), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.80**

## Completion Contract

- Normal entry: operator runs `./v3 purge-history preview`, then `run`, on the deployed repository with a verified full backup including `.git`.
- End-to-end outcome: every path named by an accepted `delete` receipt is absent from all Git objects; a committed `Key: value` commit map exists; read model and static artifacts are rebuilt; old `/source/blob` and `/source/commits` links for rewritten commits resolve; every surviving record is byte-identical.
- Required recovery: a failed or interrupted `run` restores the original refs and reports; `preview` refuses to run over blockers (dirty tree, interrupted import journal, legacy posts without timestamp records); a completed run is undone only from the operator's backup.
- Deployment and external verification: a recorded timed run on a QDB-derived copy; no live qdb.us run in this cycle. Runbook linked from the retraction references.
- Release condition: Stage 1 gate passed and its engine decision recorded; Stage 7 acceptance passes; zero dependency delta.

## Key Risks

- **High risk: irreversible data change.** A rewrite plus prune cannot be undone in place. Early validation: Stage 3 is built against a throwaway clone and its restore-on-failure path is tested before any real run. Mitigation: `run` requires a clean tree, no interrupted import journal, and an explicit `--confirm` naming the repository; the runbook makes a verified backup the first step.
- **High risk: rollback after success.** No binary or record downgrade restores purged history. Mitigation: output names the backup as the only rollback; the map record lets the rebuilt site keep serving old links so rollback is rarely needed for links.
- **Rewrite time.** `filter-branch` cost grows with history, not with retractions. Early validation: Stage 1 times a dry run on the QDB-derived copy before Stage 3 exists. Mitigation: if the time exceeds what the operator can pause, Stage 3 implements the horizon squash behind the same command and map contract; that switch changes what Activity shows for old commits and is reported for confirmation before Stage 3 starts.
- **Incomplete removal.** Objects linger in reflogs, `refs/original`, packs or other clones. Early validation: Stage 3's verifier is tested against a deliberately leaked object. Mitigation: success requires reflog expiry, `refs/original` removal, prune and an empty reachability scan; other clones are named as out of reach.
- **Hash consumers beyond the map.** Image manifests, import journals and bookmarks hold old identities. Early validation: Stage 5 inventory check against Step 2's list. Mitigation: map resolution for routes, rebuild for indexes, runbook for image backups, refusal for journals.

## Concepts touched

- History purge: created in Stage 6 (`docs/concepts/history_purge.md`).
- Post retraction and operator removal: used unchanged; entry created in Stage 6 since none exists.
- Source history links: changed in Stage 5; entry created in Stage 6.
- [Content snapshot export](../../concepts/content_snapshot_export.md) and [instance content import](../../concepts/instance_content_import.md): unchanged; verified in Stage 7.

## Stage 1 — Sizing gate and purge planner (S1/v1)

**Estimate: 0.15**

- Goal: know exactly what a purge would touch and how long it takes before writing any rewrite code.
- Dependencies: none.
- Expected changes: `HistoryPurgePlanner::plan(root): array` reading `AcceptedRemoval::all()` for `delete` operations, returning target paths (post and signature), commits touching them, legacy posts lacking `records/post-timestamps/`, and blockers (dirty tree, `.git/instance-import/pending.json`, missing `.git`). `HistoryPurgePlanner::timeDryRun(root, tmp): float` clones to a temporary directory, runs the rewrite there, and reports elapsed seconds without touching the source. Record the measured QDB-copy time and the engine decision in this plan's checkpoint and the Step 4 summary.
- Verification approach: planner unit tests on a fixture with one signed retraction and one local deletion; dry run on the QDB-derived local repository with timing captured; planner output for a repository with no receipts is an explicit "nothing to purge".
- Estimate details: driver: measuring on the large copy; uncertainty: 0.10–0.20, whether the dry run itself needs bounding.
- Risks or open questions:
  - Impact: a slow rewrite makes Option A unusable.
  - Early warning / validation: the measured time, before Stage 3.
  - Mitigation: engine switch to horizon squash, reported for confirmation.
- Canonical components/API contracts touched: `AcceptedRemoval`, `AcceptedRetraction`, `LegacyPostTimestamp`, new `HistoryPurgePlanner`.

## Stage 2 — Commit map record contract (S2/v1)

**Estimate: 0.10**

- Goal: a durable, readable record of every rewritten commit identity.
- Dependencies: none.
- Expected changes: spec `docs/specs/history_rewrite_record_v1.md`; files at `records/history-rewrites/<utc-timestamp>.txt` with `Format`, `Rewritten-At`, `Reason: purge`, `Receipt-Count`, then repeated `Old-Commit` / `New-Commit` blocks; bounded size, strict validation, no prose. `CommitMap::resolve(root, sha): ?string` returning the newest mapping across all records; `CommitMap::encode(array): string` / `decode(string): array`.
- Verification approach: round trips, malformed and duplicate entries rejected, multiple records with chained rewrites resolve to the latest identity.
- Estimate details: driver: strict codec; uncertainty: 0.08–0.12.
- Risks or open questions:
  - Impact: a map that grows unbounded or collides across runs.
  - Early warning / validation: size bound and timestamp uniqueness tests.
  - Mitigation: one record per run; refuse a second run in the same second.
- Canonical components/API contracts touched: new `CommitMap`; `GenericTextRecordParser` reuse; `SourcePathValidator` for the new record path.

## Stage 3 — Rewrite engine with restore and verification (S3/v1)

**Estimate: 0.20**

- Goal: remove the planned paths from all history, prove it, or restore.
- Dependencies: Stage 1 decision, Stage 2.
- Expected changes: `HistoryPurge::run(root, plan): array` under `ExecutionLock` on `forum-rewrite.lock`: snapshot all refs; rewrite with built-in `git filter-branch --index-filter` over the target paths, capturing old-to-new pairs from the rewrite map; expire reflogs, delete `refs/original`, prune; `HistoryPurge::verify(root, paths): array` using `git rev-list --all --objects` and `git fsck --unreachable --no-reflogs` to prove no blob for a target path remains; on any failure before prune, restore the snapshotted refs and report. Horizon-squash engine replaces only the rewrite step if Stage 1 required it.
- Verification approach: fixture repository with a retraction: run, verify empty history for the path, surviving files byte-identical, author dates preserved; injected failure after rewrite restores refs; verifier fails on a deliberately leaked unreachable blob; receipt, request and signature bytes unchanged.
- Estimate details: driver: Git plumbing and restore ordering; uncertainty: 0.15–0.25, filter-branch map capture.
- Risks or open questions:
  - Impact: partial rewrite leaves mixed refs.
  - Early warning / validation: injected-failure test.
  - Mitigation: ref snapshot before rewrite; prune only after verify.
- Canonical components/API contracts touched: new `HistoryPurge`; `ExecutionLock`; `HighTrafficSafety::beforeWrite`.

## Stage 4 — Commit the map and rebuild (S4/v1)

**Estimate: 0.10**

- Goal: the repository and derived state agree after a purge.
- Dependencies: Stages 2–3.
- Expected changes: after verify, write the map record, commit it with a fixed subject, run the full `ReadModelBuilder` rebuild, invalidate and rebuild static artifacts through the existing builders, and clear the activity commit-manifest cache. Return paths and counts in the result.
- Verification approach: rebuild on the fixture produces identical posts, labels, reactions and approvals; `commits` table holds only new identities; manifest cache has no stale rows.
- Estimate details: driver: ordering with existing builders; uncertainty: 0.08–0.12.
- Risks or open questions:
  - Impact: map committed but rebuild fails, leaving stale indexes.
  - Early warning / validation: rebuild failure test.
  - Mitigation: report "Site update incomplete; run `./v3 rebuild`", matching the retraction reference's wording.
- Canonical components/API contracts touched: `ReadModelBuilder`, `StaticArtifactBuilder`, `StaticArtifactInvalidator`, `SqliteActivityCommitManifestCache`.

## Stage 5 — Source routes resolve old identities (S5/v1)

**Estimate: 0.10**

- Goal: old links keep working.
- Dependencies: Stage 2.
- Expected changes: `SourceFileController::blob()` and `commit()`, and `Application::sourceCommitDetails()`, consult `CommitMap::resolve()` when Git does not know the identity, then proceed with the mapped one; a purged path keeps returning the existing deletion notice; response includes the resolved identity so the reader sees the redirect happened. Check Step 2's consumer inventory and record each as resolved, rebuilt or documented.
- Verification approach: route tests with old and new identities for a surviving file and a purged path; unknown and malformed identities unchanged.
- Estimate details: driver: shared memoization in `sourceCommitFiles`; uncertainty: 0.08–0.12.
- Risks or open questions:
  - Impact: a mapped identity leaks purged content through the blob route.
  - Early warning / validation: purged-path route test after mapping.
  - Mitigation: retracted-path check runs before resolution, as today.
- Canonical components/API contracts touched: `SourceFileController`, `Application::sourceCommitDetails`, `RetractedPosts`.

## Stage 6 — CLI, runbook and concept entries (S6/v1)

**Estimate: 0.10**

- Goal: an operator can do this from the shell with the documented contract.
- Dependencies: Stages 1–5.
- Expected changes: `scripts/purge_history.php` and `./v3 purge-history preview|run [--confirm=<repository_root>] [--repository-root] [--database-path] [--static-html-root] [--json]` following the CLI error-handling contract; `docs/runbooks/history_purge.md` (backup first, pause, preview, run, verify, fresh image backup, what is out of reach); links from `author_post_retraction.md`, `retraction_sweep.md` and the CLI reference; concept entries for history purge, post retraction and source history links.
- Verification approach: help and bad-option exits; `preview` on a repository with blockers refuses and names them; link check on touched docs.
- Estimate details: driver: documentation breadth; uncertainty: 0.08–0.12.
- Risks or open questions:
  - Impact: an operator runs without a backup.
  - Early warning / validation: `run` without `--confirm` refuses.
  - Mitigation: confirmation names the repository path; runbook ordering.
- Canonical components/API contracts touched: `v3` dispatcher, CLI reference, retraction references, concepts inventory.

## Stage 7 — Acceptance on the QDB-derived copy and closeout (S7/v1)

**Estimate: 0.15**

- Goal: prove the Completion Contract end to end and close the study record.
- Dependencies: Stages 1–6.
- Expected changes: none to application code except corrections found here. Replay Step 2's environment condition: `preview` reports the measured time; `run` on the QDB-derived copy with its real receipts; old-link resolution in a browser; interrupted-run restore; image backup after purge; export and import unaffected. Step 4 summary with stage evidence, retrospective assessment and ledger closeout.
- Verification approach: every Step 2 success criterion checked and recorded with commands and counts; focused suites for retraction, read model, source routes and the new classes; full suite attempted with inherited failures named.
- Estimate details: driver: large-copy runs and evidence capture; uncertainty: 0.12–0.20.
- Risks or open questions:
  - Impact: acceptance only on fixtures.
  - Early warning / validation: the QDB-copy run is a named deliverable.
  - Mitigation: Stage 7 is not complete without it.
- Canonical components/API contracts touched: none new; shared acceptance owner for the cycle.

## Enhancement Requests

None pending.

## Execution and study checkpoint

2026-10-30 UTC; Step 2 explicitly approved ("approved step 2"). Scope v1/A, baseline `2e17c971308a710a540c905e738048c4f51e53ca`, R1/P2, Claude single exposed rater; frozen hashes verified at Step 1 and unchanged. Step 3 whole-cycle **0.80 reaffirmed**. Stage work S1–S7/v1: 0.15 / 0.10 / 0.20 / 0.10 / 0.10 / 0.10 / 0.15, non-additive; Stage 7 owns shared acceptance and closeout; cycle inspection and planning are not charged to stages. Stage 1's engine decision gates Stage 3 and is reported before Stage 3 begins. No implementation, measured effort or accepted outcome. Await Approved Step 3.

Debug: [Step 3 report](history_purge_debug_log.md#2026-10-30-utc--step-3).

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*
