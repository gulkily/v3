> **Feature plan:** [Step 1](./retraction_simplification_step1_solution_assessment.md) · [Step 2](./retraction_simplification_step2_feature_description.md) · [Step 3](./retraction_simplification_step3_development_plan.md) · Step 4

Debug mode: enabled.  
Effort study: `railspike-effort` / `v3/retraction_simplification`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Extended estimates: enabled.

**Cycle estimate: 1.00**

## Problem and user stories

Retraction currently retains canonical prose and couples publication to suppression evidence. Release 1 replaces this with deletion plus a durable record and deferred local cleanup.

- As an author/operator, I want retraction to delete content and save its record immediately so that acceptance has a concrete repository outcome.
- As an operator, I want retryable maintenance so that cleanup delays do not block unrelated pages.

## Core requirements

- **Delete and record:** preserve action-time authorization/signing; commit removal of the post and detached signature together with one minimal record identifying target, actor, time and necessary conversation/purge metadata, without prose. Preserve independent replies. Report acceptance only after commit; retries must work without the original post and create no duplicate action.
- **Remove tracking:** eliminate retraction generation stamps, parallel cleanup ledgers, recurring Git acceptance reconstruction and publication gates. Retain normal write serialization, authorization and integrity checks. Missing retracted targets must not block unrelated publication.
- **Allow delay:** ordinary refresh removes prose from current views. Periodic maintenance cleans covered generated HTML, static/offline artifacts and affected saved LLM payloads; use the existing worker’s five-minute discovery cadence and manual run/retry. Backlog/failure may delay completion; no immediate all-copy suppression promise or new provenance subsystem. Git history remains until release 2.
- **Migrate:** convert existing accepted retractions, including absent targets, without restoring prose or requesting new signatures. Preserve established attribution/structure; report unresolved legacy cases without globally blocking publication. Retained accepted records prevent ordinary archive reimport from restoring targets; validate imported claims at ingestion, not repeatedly through historical proofs.
- **Recover simply:** distinguish rejected acceptance from accepted deletion with pending refresh; retain actionable retry/manual-maintenance errors. Missing targets and repeat cleanup are normal. Document covered stores/exclusions; delayed cleanup must not silently become permanent retention.

## Delivery, surfaces and flow

**Application change, release 1:** browser/API action, migration, publication simplification, local cleanup, tests, references/recovery guidance and planning/study records. No new dependencies. **Release 2**, separately planned, delivers history purge over explicit refs with interruption recovery and changed-commit verification; the full objective remains incomplete. Backups, remote/provider copies, attachments and independently published replies/quotes are excluded.

Reuse shared retraction controls/notices and prepare/finalize/retry APIs on roots, replies and post pages; preserve QDB/Forte display-only support. Update existing boards/search/activity/profiles/feeds, post/thread APIs, current/historical source routes, repository/read-model downloads, static/high-traffic pages, offline base/update snapshots and LLM list/detail views. Reuse delete-record, imports, read-model refresh and worker/CLI maintenance; no new dashboard.

1. User retracts; content deletion and record are committed.
2. Views refresh; stale local copies disappear through successful maintenance.
3. Retry failed refresh/maintenance; history purge remains explicitly outstanding.

## Risks and release acceptance

- **Migration/reimport:** validate legacy present/absent targets and interrupted commits first; preserve unresolved evidence for recovery. Records must survive loss of original content/history.
- **Concurrent publication:** validate stale/in-flight output followed by cleanup; retain ordinary authorization/integrity while relaxing immediate retraction freshness.
- **Parallel work:** reconcile newer `~/v3` high-traffic changes before implementation; preserve no-rebuild rendering and registration improvements. Verify its cold-page/manual-refresh recovery, not only helpers.

Release requires verified author/operator commit, idempotent retry, legacy migration, surviving replies, import non-resurrection, current-source removal, cold publication with absent targets and eventual cleanup of covered stores. Operator guidance must show commit/record verification and maintenance retry. Follow applicable schema/UI verification rules; distinguish local evidence from production recovery and source deletion from undelivered history purge.

## Approval and study checkpoint

User approved Step 1 Option A and its two-release split. At **2026-10-24T17:14:30Z**, scope **v2 / release 1** projected total **1.00**, independently assessed from the original baseline `22134259876481199aacff6f6b2cd39a811236a9`; original v1 full-outcome **1.50** retained, not subtracted or summed. R1/P2, exact frozen hashes reverified, Codex single exposed rater; prior source/other-checkout exposure retained. Migration and publication/cleanup integration place this slice at the comfortable-cycle boundary; reassess if planning reveals more. No independent child outcome, measured work or acceptance count. Debug/extension state inherited; diagnostics cancellation stands. Await **Approved Step 2** under [Railspike Step 2](../../railspike/docs/workflow/step2_feature_description.md#next); no implementation or live purge authorized.


2026-10-25 UTC — Approval restored: user explicitly supplied **Approved Step 2** before the Railspike upgrade interruption. Resume Step 3 under unchanged v2 scope; no implementation approval.

*— Codex · 2026-10-25 UTC*


## Extended-estimate activation — 2026-10-25

2026-10-25T18:04:54Z: enabled at the user's explicit request for all three artifacts. Earlier disabled-mode checkpoints remain historical; activation does not backfill estimates or authorize implementation. Study `railspike-effort`, cycle `v3/retraction_simplification`, R1/P2, original baseline `22134259876481199aacff6f6b2cd39a811236a9`; exact three frozen R1 hashes reverified. Codex single exposed rater with the earlier plans, scores and source review visible; no independent rating or measured labor. Exact runtime model/settings unavailable.

Release-1 whole-cycle projection **1.00 reaffirmed** for unchanged scope v2, including planning, implementation, verification, recovery, documentation and ordinary correction. Step 2 has no alternatives or implementation stages to score separately; the extension is expressed by its enabled state and inherited links to Step 1 option ratings and Step 3 stage work. No new requirements or approval reset.

*— Revised by Codex · 2026-10-25 UTC*
