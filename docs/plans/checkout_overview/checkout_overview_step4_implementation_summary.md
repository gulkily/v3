> **Feature plan:** [Step 1](./checkout_overview_step1_solution_assessment.md) · [Step 2](./checkout_overview_step2_feature_description.md) · [Step 3](./checkout_overview_step3_development_plan.md) · [Step 4](./checkout_overview_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/checkout_overview`; [ledger](../effort_estimation_trial.md), v1/R1/P2, Codex single exposed rater. Step 3 approved; branch `feat/checkout-overview`, baseline `1edbe534d`, planning `db8ea0009`. Entry total projection reaffirmed unavailable pending exact frozen R1 feedback recovery; no score backfilled or measured effort inferred. Seven planned stages; implementation begins after this entry.

## Stage 1 - Local cached overview

- Changes: default overview reads local registry/cache before manager lock; versioned selection/generation cache keys, unchecked/role-excluded fallbacks, safe corrupted-cache handling, no signed preview or Git work in the overview read path.
- Verification: PHP syntax passed; 3 focused real-Git tests passed (offline cold view while manager lock held under 1s, corrupt cache/registry invalidation and role selection, explicit preview still fetches without changing HEAD).
- Notes: worker launch and cache population follow in later stages. No live registry/checkout mutations.

## Stage 2 - Shared aligned renderer

- Changes: shared CHECKOUT/BRANCH/ACTION/HINT renderer with shortest unique suffix labels, Unicode-aware cell widths, control-character sanitization, narrow layout and abbreviation/details guidance. Preview/apply and cached view share it; apply preserves a full recovery-result file. No per-row source listings.
- Verification: 4 focused real-Git/render checks passed at 30/80/120 columns, duplicate labels, Unicode/control values, explicit preview and saved-preview output. PHP syntax passed. Initial line-count failure exposed an overlong dirty hint; shortened the hint and reran successfully.
- Notes: Stage 1 `ba6eda559`; background status/refresh follows Stage 3. JSON keeps full values.

## Stage 3 - Detached refresh lifecycle

- Changes: detached single-flight worker and synchronous refresh, 30s freshness, queued/running/deferred/failed status with launch timeout and bounded status/cache retention. Worker owns a session, handles interruption/deadline by terminating its own process group, retains previous good observations/errors, and never creates apply previews. `--no-refresh` supports deliberate cache-only diagnosis; normal default schedules automatically.
- Verification: 4 real-Git worker tests passed: detached response under 1s/coalescing, retained good rows/age after offline fetch, deferred manager lock, disabled process launcher and cold failure/retry. Corrected retained-error exit reporting after a failing focused test and reran successfully.
- Notes: Stage 2 `2929b56f4`; background launch needs available POSIX/pcntl PHP support, otherwise visible failure plus synchronous refresh recovery. No live worker or registry test mutations.

## Stage 4 - Bounded concurrent fetches

- Changes: shared collector captures pre-fetch snapshots, runs at most three independent fetch-only Git children, and classifies/publishes evidence solely in the manager-lock-owning coordinator. Pool enforces per-child/overall limits and reaps handled cancellations; refresh never creates preview files. Registry generation is rechecked before cache publication.
- Verification: 6 focused real-Git tests passed: three slow independent fetches overlap, cached foreground remains under 1s during work, manual registry change discards cache, actual SIGTERM cancels the isolated worker group and prevents publication/token replay, observed/externally fetched and already-fetched rewrites remain blocked.
- Notes: Stage 3 `dc633db4b`. Uncatchable kill cannot guarantee descendant cleanup; future inspection must still revalidate source/Git state. No child writes authoritative history/cache.

## Stage 5 - Fresh authority, invalidation and recovery

- Changes: fresh preview populates observations but signs only its fresh raw results; failed inspection cannot promote retained cached rows to apply authority. Apply invalidates affected observations before branch mutation. Empty setup guidance, relative local override normalization and ownership-token checks cover all worker state/history/cache writes; a lost worker cannot overwrite newer status.
- Verification: complete 50-case checkout suite passed before the additional ownership fixture; 6 targeted integration checks passed afterward, then 2 cancellation/lost-token checks passed with stronger no-history-write assertion. Covered preview/cache authority separation, update invalidation, cached reads under apply lock, offline recovery and relative worker paths.
- Notes: Stage 4 `5bba5d22d`. Source observations remain explicitly cached; no subsecond guarantee is made for fresh preview.

## Stage 6 - CLI documentation and operator checks

- Changes: help/reference distinguish cached default, 30s background policy, fresh preview authority, synchronous refresh and cache-only JSON diagnosis. Document columns/width, observation ages/errors, launch/busy recovery, worker/child limits and optional POSIX/pcntl/nohup requirements. Top-level help includes overview/refresh.
- Verification: 3 documented-flow/help tests passed from two project directories; 19/19 existing StatusCommand/RailspikeSync PHP checks passed. Relevant syntax/diff checks passed.
- Notes: Stage 5 `4d023bfe6`; performance samples and full final acceptance follow Stage 7. Network-complete preview still has no subsecond guarantee.

## Stage 7 - WSL timing and failure acceptance

- Changes: repeatable eight-clone benchmark/terminal replay using isolated private fixture state; cache-shape validation converts malformed nested rows to unchecked instead of crashing. Added expired queued-worker recovery coverage. [Final timings](./timing_acceptance.json) retain 60 samples and implementation-file hashes; [initial timings](./timing_acceptance_initial.json) preserve the earlier successful run before the final cache-shape check. [Terminal replay](./terminal_acceptance.txt) shows columns plus cached overview → fresh preview → apply → refresh, with disposable working-tree updates only.
- Verification: PASS — `php tests/run.php CheckoutsCommandTest StatusCommandTest RailspikeSyncCommandTest`: 20/20 PHP checks, including all 53 real-Git checkout cases. PASS — final benchmark: 10 cold, 10 slow-running, 20 warm, 10 offline and 10 manager-lock-held foreground runs, all below 1 second. Final warm median 0.0506s, p95 0.0617s, maximum across all phases 0.0807s; initial all-phase maximum 0.1657s retained separately. Source hashes match final implementation. Malformed cache/stale marker test passed separately before final harness. PHP/shell syntax, Python parsing, diff and cycle local file links passed.
- Notes: Stage 6 `21640d606`. Host `6.6.87.2-microsoft-standard-WSL2`; timing includes CLI process/output completion. No failed/outlier samples discarded; numbers are local observations, not universal OS scheduling or network-complete preview guarantees. Actual IntelliJ UI interaction/branch-title plugin remains unverified. Full project suite not run; scoped checkout/status/sync checks passed. Live registry and clone branches were not modified by acceptance; no push, main merge, service install or deployment.

## Requests, Scope and Study Outcome

- Approved request: instant cached overview/background refresh plus aligned columns / delivered within Step 2 v1 and seven Stage 3 stages / implementation authorized by Approved Step 3 / exact final revision recorded in the post-commit debug handoff.
- In-scope refinements: `--no-refresh` for local-only diagnosis, synchronous refresh retry, complete apply-recovery file for abbreviated output, relative override normalization, lost-token protection on every authoritative worker write and malformed cache recovery / tested and recorded in the relevant stage evidence. No new service, automatic enrollment, migration or IDE integration.
- Deferred/unverified: live clone updates, actual IntelliJ UI clicks, main integration/push and broad full-suite acceptance. Cached rows intentionally describe previous observations; fresh preview remains required before apply. Detached background launch requires available POSIX/pcntl/nohup; failure is visible and synchronous refresh remains available. SIGKILL can leave descendants requiring inspection; handled termination kills the worker's isolated group.

2026-10-23T14:59:26+00:00; stable cycle `v3/checkout_overview`, parent `v3/concurrent_checkouts`, scope v1/R1/P2; Codex single exposed rater. Original baseline `1edbe534d`; planning `db8ea0009`; stage commits: 1 `ba6eda559`, 2 `2929b56f4`, 3 `dc633db4b`, 4 `5bba5d22d`, 5 `4d023bfe6`, 6 `21640d606`, 7 recorded after commit in debug handoff. Seven stages delivered with per-stage checks/summary commits; eight commits through final stage including planning (minimum met).

Projected total remains unavailable pending exact frozen R1 feedback recovery; preserve prior missing checkpoints. Separately labeled retrospective scope assessment is also numerically unavailable against incomplete frozen references: qualitatively delivered cache/worker lifecycle, concurrency/history safeguards, shared renderer, recovery/documentation and host acceptance. Test/timing durations are not measured active effort. Maintainer comfortable-fit and acceptance pending; no accepted-count increment, independent split outcome or automatic study upload.

## Main integration

2026-10-23T15:03:21+00:00: user explicitly authorized merge and push. Fast-forwarded local main to remote `b9d1cc874`, then merged `feat/checkout-overview` including the predecessor checkout tooling. Resolved the sole append-only study-ledger conflict by retaining both independent sets of records; application/test-runner changes merged automatically. Combined `php tests/run.php CheckoutsCommandTest StatusCommandTest RailspikeSyncCommandTest MarkdownPipeTableTest` passed 28/28 PHP checks, including all 53 real-Git checkout acceptance cases. No live clone synchronization or automatic migration added. Ordinary push follows the verified merge; exact merge revision/publication is recorded in Git history and the user handoff.

## Small post-merge refinement - Preview eligibility marker

- Request: mark apply-eligible preview rows with one character; user explicitly requested no legend.
- Changes: prefix fresh-preview fast-forward/merge actions with `*`; cached overview and other actions remain unmarked, with no legend or JSON/eligibility changes.
- Verification: 3 focused real-Git/render tests passed (fast-forward/merge marked, dirty/current and cached rows unmarked, no legend, existing one-line preview/column widths preserved); PHP syntax and diff checks passed.
