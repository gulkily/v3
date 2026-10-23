> **Feature plan:** [Step 1](./checkout_overview_step1_solution_assessment.md) · [Step 2](./checkout_overview_step2_feature_description.md) · [Step 3](./checkout_overview_step3_development_plan.md) · [Step 4](./checkout_overview_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/checkout_overview`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Follow-up to `v3/concurrent_checkouts`; Step 1 v1 approved.

## Problem and User Stories

Default checkout preview takes a reported 5–10 seconds because it waits for Git/network inspection. Operators need a fast, legible overview without confusing stale observations with permission to update.

- As the operator, I want instant aligned checkout rows so that I can review my IntelliJ project folders without waiting for network work.
- As the operator, I want visible observation age and refresh failures so that I know when to request a fresh actionable preview.

## Core Requirements

- Default `./v3 checkouts` reads only registry/cache locally, targeting under one second on this WSL host for the eight-checkout registry shape. It never waits for network, per-checkout Git inspection or a running worker/apply lock. Cold/new entries show “unchecked”; removed/reconfigured entries cannot inherit obsolete status. Cached branch/action values are explicitly observations, not live state.
- Start/coalesce one bounded background refresh when needed; inspect/fetch independent Git stores with bounded concurrency, preserve existing history evidence, publish complete results atomically and reject a changed registry generation. Refresh never changes checked-out branches. Retain previous successful observations on failure alongside truthful failure/age state; no orphan-worker proliferation, unbounded logs or older evidence overwriting newer results.
- Show aligned CHECKOUT / BRANCH / ACTION / HINT columns with one row per checkout and a compact age/refresh footer. Disambiguate compact path labels, handle long/control-character values and narrow terminals, and keep actionable recovery accessible when abbreviated. JSON keeps full paths, reasons, timestamps and refresh state. Use the same renderer for fresh preview/apply, without per-row expected-source listings.
- `preview` still performs fresh source inspection and creates the only actionable preview ID; `apply` retains signed-preview/state/history validation and session coordination. Cached output never offers an apply ID or implies safe-to-update. Explicit preview can take longer than one second; registry list remains non-fetching.
- First use, spawn failure, offline/slow fetch, busy refresh/apply and expired worker state all leave the overview responsive and provide an actionable retry/diagnosis route. Recovery guidance must distinguish a stale observation from a live failure; no service installation, IDE setup or automatic clone enrollment.

## Delivery Scope and Completion Boundary

Work type: **application change — internal operator tooling**. Extend checkout CLI/cache/worker lifecycle, aligned rendering, tests and CLI documentation; include this cycle's plans/debug/study bookkeeping. No forum UI, database schema, migrations or trust changes. Complete when ordinary cached overview → background observation → explicit fresh preview → existing apply/recovery works end to end, with on-host latency and worker/concurrency evidence. Automatic history migration and live clone updates remain outside scope.

## Known Environment and User Flow

User uses IntelliJ IDEA 2023.3.6 terminals over `/home/wsl` clones. Run `./v3 checkouts`, read rows plus age/refresh state, rerun after refresh if desired; when preparing an update, run `preview`, coordinate sessions, then `apply`. If unchecked/stale/offline/spawn-failed, follow the displayed retry action; if another update holds locks, read cached status immediately and retry fresh preview once available. Verify recovery by a newer completed observation or a successful fresh preview. Replay on this WSL host using disposable clones/private registries; do not update live clones as tests. Actual IntelliJ interaction must be recorded as pending if inaccessible.

## Key Risks

- Stale data mistaken for actionable status: could mislead updates. Validate age/unchecked/error presentation before wiring the default; omit cached apply IDs and preserve fresh preview rechecks.
- Background races/source rewrites: could corrupt evidence or overwrite newer state. Validate ownership/coalescing, registry generation and existing history/apply-lock coordination before enabling concurrent refresh.
- Latency/spawn failures: could recreate waits or leak workers. Validate cold/warm/busy/offline foreground timings and worker termination early; bound concurrency, lifetime and diagnostics with explicit retry.
- Narrow/long output: could hide identity or recovery. Validate unique labels and terminal widths before accepting renderer changes; preserve full JSON/details access.

## Shared Components

Extend `scripts/checkouts.php` and `CheckoutManager` registry, source classification and state contracts; cached observations are a distinct view from signed previews. Extract/reuse the current compact action/hint renderer for all human checkout views. `DetachedTaskQueueLauncher` and `ScriptureTaskLauncher` provide detached-process precedents; evaluate their launch/error-handling patterns without coupling checkout refresh to the forum queue. Reuse Git/identity/history safeguards and existing `CheckoutsCommandTest` real-Git harness. Update `v3` help and `docs/reference/v3_cli.md`; no existing browser/API surface renders this data.

## Success Criteria and Study Checkpoint

Repeated cold/warm default invocations over eight disposable registered clones each finish below one second on the recorded WSL environment, including a deliberately slow/busy/offline refresh; report samples/distribution and scheduling limits. Verify refresh coalescing, failure/retry, registry invalidation, history-evidence integrity, atomic publication and lock interaction. Human rows align and remain one per checkout at representative widths; JSON retains details. Existing fresh-preview/apply and conflict/rewrite tests pass.

2026-10-23T14:27:34+00:00; Step 2 total projection remains **unavailable pending exact frozen R1 feedback recovery**, reaffirming scope v1, baseline `1edbe534d7faf0c84c3b4d0ddc3e60fef3a38beb`, R1/P2, Codex single exposed rater. Prior implementation/timing exposure disclosed in Step 1; no backfilled estimate, measured active effort or accepted outcome. Ledger bookkeeping included in delivery scope.

Request: Approved Step 1 / Disposition: record selection approval and define outcome/recovery / Approval: Step 2 and implementation pending / Outcome: no application changes yet.

**Approved Step 2** received; recorded 2026-10-23T14:33:16+00:00. No new requirements since approval; Step 3 drafting authorized, implementation pending.
