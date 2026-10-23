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
