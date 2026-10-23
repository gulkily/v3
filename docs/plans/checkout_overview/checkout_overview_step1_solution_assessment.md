> **Feature plan:** [Step 1](./checkout_overview_step1_solution_assessment.md) · [Step 2](./checkout_overview_step2_feature_description.md) · [Step 3](./checkout_overview_step3_development_plan.md) · [Step 4](./checkout_overview_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/checkout_overview`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Follow-up to [concurrent checkout synchronization](../concurrent_checkouts/concurrent_checkouts_step4_implementation_summary.md); distinct cycle, no branch reuse requested or implementation approved.

## Original Query

“Checkouts / checkouts preview currently takes 5–10 seconds. How do we keep it down to <1 second?”

“Let's go with your recommendation. Let's try to make some vertical columns for easier review too, please.”

## Understood Intent

Adopt an immediate cached default overview with background refresh; retain explicit fresh preview/apply for updates. Align checkout, branch, action and recovery hint columns while keeping one line per checkout. Subsecond responsiveness applies to the overview, not network-complete preview.

## Problem

The default currently fetches/inspects each clone synchronously and serially, and variable-length paths/branches make the rows harder to scan.

## Option A: Cached overview with background refresh (selected recommendation)

Show the last completed local snapshot immediately and arrange a bounded background refresh when needed; keep fresh preview as the deliberate preparation step for applying updates. Use aligned terminal columns and a compact cache-age/refresh indicator.

- Pros: Foreground avoids network and per-checkout Git work, including when a refresh is already running; stable scan-friendly output.
- Cons: Observations can be stale; cold start, refresh coordination/failure and terminal width require explicit behavior.

## Option B: Parallel synchronous preview

Fetch independent clones concurrently and reduce Git subprocess overhead, while keeping network-complete preview as the default.

- Pros: Current meaning of default preserved; likely faster under normal connectivity.
- Cons: Network/authentication/slow Git still prevent a reliable subsecond foreground response; does not satisfy the selected immediate-display goal.

## Recommendation and Vertical Slice

Choose A: registered paths → immediate cached/unchecked rows → single bounded background refresh → atomically published latest observation → explicit fresh preview and existing validated apply. Reduce refresh duration with bounded concurrency across independent Git stores; preserve history evidence and coordinate with existing registry/apply locks. The overview must read the last complete cache without waiting for the refresh/apply lock; workers must not mutate checked-out branches, overwrite newer history evidence or publish results for a changed registry.

Show cache age and refresh/error status truthfully. New entries and first use show “unchecked” immediately while refresh starts; retain previous successful data and failure context on refresh errors. Registry changes invalidate/filter old rows. Cached branch/action values are observations, not live guarantees; never offer a cached apply ID. Keep JSON complete and source/history validation/recovery intact. Spawn failures must leave usable cached output and a concrete retry action; coalesce repeat invocations and bound worker lifetime/log retention.

Human output uses aligned CHECKOUT / BRANCH / ACTION / HINT columns, unique compact path labels, and one row per checkout. Account for long/duplicate names, terminal width and control characters; do not hide actionable failure recovery through truncation. Keep full paths/reasons in JSON; narrow terminals can use concise hints with an explicit way to retrieve details. No extra per-row source listings. Apply/fresh-preview output should use the same column renderer.

Verify cached and empty-cache default in this WSL environment against the local eight-checkout registry shape, with repeated startup timings below 1 second (report distribution/environment, not a universal scheduling guarantee), slow/offline refresh, overlapping invocations/apply, registry changes, worker failure and terminal widths. Fresh preview may still exceed 1 second and must say it waits for current source inspection. No automatic branch changes, migration, IDE changes, new service/plugin or live-pool updates as tests.

## Evidence, Requests and Study Checkpoint

Existing `CheckoutManager::run` holds one state lock across serial fetch/inspection; `scripts/checkouts.php` defaults to preview. Registry-only `list --json` measured 0.023s median / 0.082s maximum across five local runs; this is feasibility evidence, not a cache implementation benchmark or a measured 5–10s preview profile. User supplied the preview latency. Worker/renderer reuse needs investigation during later planning.

Request: adopt cached-default recommendation and vertical columns / Disposition: follow-up cycle changes earlier default semantics and adds worker lifecycle; column alignment included / Approval: direction selected, Step 1 advancement and implementation pending / Outcome: assessment saved; predecessor preserved on current branch. Optional discovery, IDE integrations and automated migration remain deferred.

2026-10-23T14:15:51+00:00; intake exposure disclosure and Step 1 handoff, scope v1; baseline `1edbe534d7faf0c84c3b4d0ddc3e60fef3a38beb`; R1/P2, Codex single exposed rater. Prior implementation, performance discussion and registry timing were inspected before recording; no pristine prospective intake claim. Total scope covers planning, cache/worker lifecycle, columns, verification/recovery and docs. Numeric projection unavailable pending exact frozen R1 feedback recovery as recorded in the predecessor; no reconstructed earlier score, measured active effort or accepted outcome. Keep same cycle across copies; no independent split success.

**Approved Step 1** received; recorded 2026-10-23T14:27:34+00:00. Step 2 drafting authorized; implementation unapproved.
