> **Feature plan:** [Step 1](./activity_load_performance_step1_solution_assessment.md) · [Step 2](./activity_load_performance_step2_feature_description.md) · [Step 3](./activity_load_performance_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/activity_load_performance; [ledger](../effort_estimation_trial.md#cycle-v3activity_load_performance)
Extended estimates: enabled
Status: Step 2 approved; development planning authorized; implementation unapproved.
**Cycle estimate: 0.95**

## Problem and user stories

Standard and Forte Activity perform repeated work around existing stored events, delaying browsing; production standard Activity returned first bytes after 4–8.5 seconds and once timed out. Forte slowness is user-reported but not reproduced in two anonymous samples.

- As a visitor, I want Activity lists and selected evidence to appear promptly so I can browse history without repeated waits.
- As an operator, I want stored Activity to follow changes and recover after interrupted indexing so speed does not compromise accuracy or access.

## Core requirements

- Both views read precomputed event evidence and maintained category counts from the existing read model, extended only as needed. Normal list/filter/page/sort/selection requests perform no Git traversal, signature verification or category-wide PHP counting, including after a process restart; no expensive reconstruction fallback.
- Update affected data on additions, imports, retractions/deletions and changes to displayed identity, visibility/approval or source/signature/key availability. Historical facts remain distinct from current state; preserve current access checks and freshness rules. Retain retraction records without deleted prose; no new publication guards or sweep diagnostics.
- Preserve ordering, membership, counts, source links and signature meanings, standard Next/Back and expired-position recovery, Forte sort/append/selection and stable first paint. Missing evidence has an honest unavailable state and usable retry/navigation.
- Existing rebuild/update recovery must restore complete, consistent Activity after interruption or incompatible read models. Keep update work proportional to affected data where practical; verify core read-model version/spec, prior-version startup recovery and normal rebuild requirements.
- Measure latency, database growth and indexing/rebuild cost. Accepted sizing estimate: 0.25–1 KB/event plus temporary rebuild/WAL headroom; avoid storing rendered HTML or duplicating whole commit manifests per event. Report deviations before accepting the design.

## Delivery scope and completion boundary

Work type: Application change, with tests, read-model specification, operator recovery guidance, cycle artifacts and study ledger; zero new dependencies. Reuse shared Activity data and presentation components for both views. Full commit/source inspection, RSS behavior, authorization policy, broad host/startup redesign and new page-artifact infrastructure are unchanged contracts.

Normal entry: The user's existing zenmemes.com Activity and Forte links, with their normal cookies/account and category/sort selection. Flow: open either view → filter/page/sort → inspect an event → follow its source. On unavailable data, the user can retry or return to the first page; after operator recovery, the same route must work without clearing browser storage. Operators use existing maintenance/rebuild recovery. Verify phone and desktop navigation, first paint and failure/retry; local evidence cannot establish production resolution. Production verification follows separately authorized rollout; no deployment is authorized here.

## Shared surfaces

Reuse/extend ActivityService, standard Activity controller/template and pagination, Forte controller/list/detail/filter partials and reader, and Forte page/detail APIs. Security-review variants retain live authorization. RSS, full-commit detail API/page and source views share helpers: regression-check their existing contracts, keeping their separate inspection work outside ordinary selected-event rendering. Reuse existing indexing/rebuild and cache-retirement paths; no parallel UI or new service.

## Risks and success criteria

- Missed update dependencies could show stale evidence: before implementation planning, audit all mutation/import/rebuild callers; require incremental-versus-rebuild parity and interruption/retry coverage.
- Indexing/storage amplification could move delay to writes: inspect bulk-commit and shared-key fan-out early; reuse metadata and compare representative signed/unsigned and bulk-history datasets.
- Host/startup contention could persist despite cheaper reads: separate startup, query, rendering and browser timings early; scope broader remediation separately if required.

Acceptance: zero ordinary-read Git/GPG work and category-wide PHP scans; identical expected membership/order/evidence across updates and rebuilds. Target warm document/detail response p95 ≤1 second over 20 sequential controlled representative requests per view; measure startup/cold recovery separately, with no production load test. Record normal production browser timings after rollout and require the reported navigation delay to be resolved before claiming production success. Report phone/desktop behavior, final storage and indexing costs, and any unmet target explicitly.

## Study checkpoint and approval

2026-10-27T23:07:52Z: scope v3/C clarified; original baseline 7ef735dd21b3329f18716bf3d5484baab2b2b71b, R1/P2, Codex single exposed rater; 0.95 whole-cycle projection reaffirmed. Prior scores/intake limitation remain in Step 1; frozen hash verification carried forward. No implementation, measured labor or accepted outcome. User's storage acceptance recorded; no new enhancement requests. Await `Approved Step 2` before development planning.

*— Codex · 2026-10-27 UTC*

Approval record: 2026-10-27T23:15:13Z — user: “Approved Step 2.” Earlier pending status is historical; proceed to Step 3 only.

*— Codex · 2026-10-27 UTC*
