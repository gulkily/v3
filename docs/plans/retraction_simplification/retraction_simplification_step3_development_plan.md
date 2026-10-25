> **Feature plan:** [Step 1](./retraction_simplification_step1_solution_assessment.md) · [Step 2](./retraction_simplification_step2_feature_description.md) · Step 3 · Step 4

Debug mode: enabled.  
Effort study: `railspike-effort` / `v3/retraction_simplification`; [P2 ledger](../effort_estimation_trial.md#cycle-v3retraction_simplification). Extended estimates: enabled.  
Approval: Step 2 approved; implementation pending.

**Cycle estimate: 1.00**

## Completion Contract

Browser/API retraction commits deletion and one durable record; replies survive. Existing imports, readers and worker support absent targets, delayed cleanup and retry without retraction ledgers or publication gates. Release requires migration, normal-flow and recovery evidence; production verification remains explicit. History purge is release 2. No dependencies added.

## Key Risks

- **High risk:** migration can lose attribution or invalidate signatures. Stage 1 proves signed-evidence preservation and ingestion authority before deletion; unresolved cases retain evidence and actionable errors, without global blocking.
- **High risk:** stale output or repeatedly timed-out scans can retain prose. Test complete traversal and late publication; use ordinary refresh/manual recovery, never a new cleanup ledger.
- **High risk:** old application rollback may require deleted originals. Roll back code only to compatible readers; never restore prose automatically. Preserve independent replies and ordinary integrity checks.

## Stage 1

**Estimate: 0.20** — stage work

- Estimate details: driver: acceptance/record contract and security fixtures; uncertainty: legacy signed evidence.

- Goal: establish the minimal durable acceptance contract.
- Dependencies: approved Step 2.
- Expected changes: one record containing target/actor/time/structure and preserved signed request; no progress state.
- Verification approach: author/operator, forged import, legacy signature and absent-target fixtures.
- Risks/open questions: prove local acceptance versus untrusted import; unresolved authority blocks dependent changes.
- Canonical components/API contracts touched: retraction parser/repository; acceptance and import validation boundaries.

*— Codex · 2026-10-25 UTC*

## Stage 2

**Estimate: 0.20** — stage work

- Estimate details: driver: atomic deletion and browser/API retry checks; uncertainty: prepared-write races.

- Goal: immediate browser/API deletion with retry.
- Dependencies: 1.
- Expected changes: commit post/signature removal with record under existing write lock; distinguish commit failure from pending refresh.
- Verification approach: signed author/operator actions, failed commit, repeat request, surviving replies.
- Risks/open questions: verify prepared-request races before accepting deletion.
- Canonical components/API contracts touched: `prepareRetraction(array): array`, `finalizePreparedRetraction(array): array`; existing response compatibility.

*— Codex · 2026-10-25 UTC*

## Stage 3

**Estimate: 0.30** — stage work

- Estimate details: driver: legacy conversion and interrupted migration recovery; uncertainty: absent-target authority variants.

- Goal: migrate accepted legacy records safely.
- Dependencies: 1–2.
- Expected changes: idempotent operator migration; resolve historical acceptance once, retain signatures/evidence without re-signing; reuse deletion transaction.
- Verification approach: present/absent targets, removal receipts, interruption/retry, unresolved report.
- Risks/open questions: never infer missing authority or restore prose; document compatible rollback.
- Canonical components/API contracts touched: existing maintenance CLI, retraction authority, legacy removal records.

*— Codex · 2026-10-25 UTC*

## Stage 4

**Estimate: 0.25** — stage work

- Estimate details: driver: reader/import integration and rebuild checks; uncertainty: structural placeholders and potential schema migration.

- Goal: normal readers/imports work after deletion.
- Dependencies: 3.
- Expected changes: derive structural placeholders from retained records; reject ordinary reimport resurrection; remove recurring historical acceptance reconstruction.
- Verification approach: rebuild, replies, feeds/search/profiles, API/source routes, repository/read-model exports and import retries.
- Risks/open questions: no schema change intended; any change requires version/spec/startup migration checks under CONTRIBUTING.
- Canonical components/API contracts touched: canonical import, read model, shared retraction readers.

*— Codex · 2026-10-25 UTC*

## Stage 5

**Estimate: 0.25** — stage work

- Estimate details: driver: publication decoupling across existing consumers; uncertainty: concurrent output and newer high-traffic integration.

- Goal: publication tolerates stale retraction state.
- Dependencies: 4.
- Expected changes: remove generation stamps/retraction gates; preserve ordinary publication integrity and no-rebuild rendering.
- Verification approach: cold/warm/manual high-traffic refresh, static/offline base/update, concurrent stale publisher then refresh.
- Risks/open questions: reconcile `~/v3` changes; missing targets must not block unrelated pages.
- Canonical components/API contracts touched: publication guard, high-traffic render input, snapshot builders/readers.

*— Codex · 2026-10-25 UTC*

## Stage 6

**Estimate: 0.30** — stage work

- Estimate details: driver: stateless artifact cleanup and full-size retry verification; uncertainty: scan completion without persistent cursors.

- Goal: deferred artifact cleanup without a ledger.
- Dependencies: 5.
- Expected changes: repeatable scan/retirement using ordinary artifact metadata and retained records; remove persisted generation/cursor state.
- Verification approach: full-size traversal, interruption/manual retry, late output, offline client refresh; preserve assets and independent documents.
- Risks/open questions: prove scans finish without starvation before release; otherwise revise plan, not add tracking.
- Canonical components/API contracts touched: artifact sweep, existing worker discovery/manual CLI.

*— Codex · 2026-10-25 UTC*

## Stage 7

**Estimate: 0.20** — stage work

- Estimate details: driver: LLM scrubbing and late-write checks; uncertainty: ambiguous older dependency manifests.

- Goal: saved LLM payloads clean up eventually.
- Dependencies: 4, 6.
- Expected changes: reuse existing dependencies for idempotent scrubbing; remove immediate read/publication suppression and parallel sweep ledger.
- Verification approach: affected/unaffected payloads, late writes, ambiguous legacy dependencies, repeated successful sweep.
- Risks/open questions: unresolved provenance needs explicit conservative handling/recovery, never silent permanent retention.
- Canonical components/API contracts touched: exchange store/recorder, dependency manifests, sweep factory/task status.

*— Codex · 2026-10-25 UTC*

## Stage 8

**Estimate: 0.25** — stage work

- Estimate details: driver: shared end-to-end/UI/CLI acceptance, recovery guidance and closeout; uncertainty: QDB-sized reproduction and production evidence availability.

- Goal: release acceptance and operator recovery.
- Dependencies: 1–7.
- Expected changes: retire obsolete contracts/tests; update retraction, sweep, LLM privacy and recovery references; record evidence/study outcome.
- Verification approach: complete UI/API/worker flow, phone/desktop themes, QDB-sized migration/cleanup; replay absent-target cold-page and refresh-timeout failures.
- Risks/open questions: local replay does not establish qdb.us recovery; provide exact host verification/manual retry and retain deployment pending until observed.
- Canonical components/API contracts touched: CLI help/JSON, UI messages, documentation and applicable existing regressions.

*— Codex · 2026-10-25 UTC*

## Study checkpoint

2026-10-25 UTC: v2/release 1, original baseline `22134259876481199aacff6f6b2cd39a811236a9`, R1/P2; exact frozen hashes reverified. Codex single exposed rater; prior source/checkouts exposure retained. **1.00 reaffirmed** including migration, cleanup scale/recovery and verification; v1 full-outcome 1.50 and Step 2 v2 1.00 preserved. Eight planned/zero completed stages; no measured effort or accepted outcome. Current inspection at `df313cf0`, other checkout `63314e6cf`; its dirty work remains untouched. Extended mode stays disabled after beta.3 upgrade. Stop and split/replan if a stage cannot remain roughly one hour or the cycle exceeds a day; no verification omissions to fit the score.

*— Codex · 2026-10-25 UTC*


## Extended-estimate activation — 2026-10-25

2026-10-25T18:04:54Z: enabled at the user's explicit request for all three artifacts. Earlier disabled-mode checkpoints remain historical; activation does not backfill estimates or authorize implementation. Study `railspike-effort`, cycle `v3/retraction_simplification`, R1/P2, original baseline `22134259876481199aacff6f6b2cd39a811236a9`; exact three frozen R1 hashes reverified. Codex single exposed rater with the earlier plans, scores and source review visible; no independent rating or measured labor. Exact runtime model/settings unavailable.

Stable stage IDs `S1/v1`–`S8/v1` correspond to Stage 1–8 above; their scopes are unchanged. These first current stage-work ratings cover each entire stage, including its focused verification, documentation and ordinary correction. Stage 8 owns shared cross-stage acceptance, consolidated references and study closeout; earlier stages own only their focused checks/notes. Cycle-wide inspection/planning is not charged to each stage. **1.00 whole-cycle projection independently reaffirmed**, not calculated from the stage values. Stage work is neither remaining work, time, percentage complete nor independent cycle outcomes. Original pre-activation stage estimates remain missing. Prior attribution is retained; this estimate-only revision covers all eight stages.

*— Revised by Codex · 2026-10-25 UTC*
