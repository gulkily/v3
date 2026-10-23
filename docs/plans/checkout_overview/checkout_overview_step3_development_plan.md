> **Feature plan:** [Step 1](./checkout_overview_step1_solution_assessment.md) · [Step 2](./checkout_overview_step2_feature_description.md) · [Step 3](./checkout_overview_step3_development_plan.md) · [Step 4](./checkout_overview_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/checkout_overview`; [standing P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Step 2 v1 approved; follow-up to `v3/concurrent_checkouts` without branch reuse.

## Completion Contract

- Default `./v3 checkouts` reads a private local observation cache without waiting for the manager's registry/network/apply lock, prints aligned one-row-per-checkout columns with age/refresh state, and coalesces a background refresh when stale or unchecked. Target foreground under 1s on this WSL host for eight registered clones. Cold/invalid/missing cache gives unchecked rows plus recovery, not a synchronous fetch.
- Preserve explicit `list`, fresh `preview` and validated `apply`. Add synchronous `refresh` as a cache-only retry/diagnosis route (no apply ID); an internal worker runs the same bounded refresh. Cached output never offers an apply command. Support existing registry/state overrides, roles/path selection and full JSON; cache keys include registry generation and selection identity.
- Release when cold/warm/slow/busy/error terminal tests and representative-width output pass, fresh preview/apply safeguards regressions pass, and on-host timing evidence is recorded. Actual IntelliJ interaction remains pending if inaccessible. No live clone updates, IDE settings, service install, database or history migration; local test fixtures only. Include CLI docs, cycle plans/debug and study ledger.

## Key Risks

- **High risk: stale observations treated as current authority.** Validate unchecked/age/failure presentation and selector invalidation first; retain last successful observation timestamps individually, never advertise an apply ID from cache, and require fresh preview revalidation.
- **High risk: history races or orphaned writers.** Existing manager lock serializes authoritative inspection/evidence/apply. Keep one coordinator as the sole history/cache publisher; parallel children fetch only distinct Git stores. Bound/await children, recheck generation before publication, and validate worker failure/ownership before enabling background concurrency.
- **High risk: foreground waits or worker proliferation.** No Git/network or blocking lock in the overview path. Validate detached launch/closed descriptors and single-worker ownership early; bounded launch/worker status and actual lock ownership distinguish queued/running/stalled. Never use an expired timestamp alone as permission to launch duplicate writers.
- **High risk: truncated identity/recovery.** Validate collision-free labels and narrow-terminal columns before reuse; retain concise actionable hints and a full-details route, sanitize control characters, preserve JSON full values.

## Stage 1 — Local cached overview

- Goal: make the default return local observations immediately.
- Dependencies: Approved Step 3, new branch and planning commit.
- Expected changes: add `overview`/default dispatch ahead of the mutating manager lock; versioned atomic cache with registry/selection fingerprints and per-row observation ages; unchecked fallbacks and explicit cache/corruption state. Excluded roles remain visibly excluded. Foreground reads no Git metadata and never creates a signed preview.
- Verification approach: real registry fixtures for empty/cold/warm/corrupt cache, removed/reconfigured/new entries, path selection and held manager lock; assert no Git subprocess, network or apply ID.
- Risks or open questions: stale/wrong rows; reject fingerprints and preserve last completed generation before any worker integration.
- Canonical components/API contracts touched: checkout manager/cache view, private state paths, CLI default and JSON observation contract.

## Stage 2 — Shared aligned renderer

- Goal: scan overview/preview/apply in aligned columns.
- Dependencies: Stage 1 committed.
- Expected changes: extract checkout renderer; CHECKOUT / BRANCH / ACTION / HINT columns with unique compact labels, sanitized values and width-aware abbreviation; compact footer and explicit full-details JSON guidance on abbreviation. Width uses actual terminal/environment where available with a stable non-TTY fallback; source listings stay omitted.
- Verification approach: rendered output at 80/120 columns and a narrow case; long/duplicate names, Unicode/control characters, missing branches, conflict/migration/spawn failure hints, piped output and full JSON. Confirm one logical row per checkout and identity remains distinguishable.
- Risks or open questions: narrow terminal cannot fit full recovery; prioritize action/unique identity and expose full command/reason via JSON without silently losing required recovery.
- Canonical components/API contracts touched: `scripts/checkouts.php`, new checkout renderer, existing hint semantics and command-output fixtures.

## Stage 3 — Detached refresh lifecycle

- Goal: refresh observations without holding up the overview.
- Dependencies: Stages 1–2 committed.
- Expected changes: private queued/running/failed worker state, nonblocking single-flight launch and detached descriptors; synchronous `refresh` and internal worker entry sharing existing inspection. Reuse detached-launch patterns without forum task-queue coupling. Begin with a 30s freshness interval and bounded worker deadline; show deferred/busy status when manager lock is held. Retain good data/failure context and cap retained diagnostics.
- Verification approach: launch acceptance in WSL with slow fetch, repeated invocations, immediate worker exit/spawn failure, paused/apply lock and stale worker marker; foreground returns before completion, no duplicate active worker, retry route usable.
- Risks or open questions: parent accidentally waits on worker pipes or stale markers suppress refresh forever; prove detached return and actual lock ownership before moving to concurrent fetching.
- Canonical components/API contracts touched: worker launcher/status store and CLI refresh entry; existing detached task launcher only as reference, no changes to forum queue.

## Stage 4 — Bounded parallel fetch with serialized evidence

- Goal: reduce refresh duration without racing safety state.
- Dependencies: Stage 3 committed and lifecycle tests passing.
- Expected changes: separate pre-fetch snapshots, bounded fetch scheduling (up to 3 independent stores) and post-fetch classification. Coordinator holds existing manager ownership while children fetch only; it alone records observed-source/baseline/rewrites and atomically publishes results after generation validation. Bound Git/worker elapsed time, terminate/reap launched children on handled cancellation, report uncertain interrupted work without auto-replay. Refresh creates no preview files/IDs.
- Verification approach: real forward/diverged/rewritten/already-fetched cases; prove concurrent independent fetches overlap, shared-store fetches do not; interruption, child failure, source/registry changes and delayed publishers cannot overwrite newer history/cache state. Ensure evidence remains safe after interrupted fetches even without cache publication.
- Risks or open questions: process termination cannot guarantee cleanup after an uncatchable kill; authoritative history writes stay in coordinator, OS locks release on death, and next run revalidates Git/source state before publishing. No stale worker may publish on a lost ownership token.
- Canonical components/API contracts touched: Git runner/scheduler, existing inspect/classification/history store, cache publication and lock ownership.

## Stage 5 — Fresh-preview/apply integration and recovery

- Goal: complete cached overview → fresh preview → safe apply/recovery.
- Dependencies: Stage 4 committed with source/history safeguards verified.
- Expected changes: fresh preview shares bounded inspection where safe and remains actionable authority; expose busy/failed refresh retry instructions; successful preview/refresh may update matching cache observations, apply invalidates affected observations rather than presenting old data as current. Every cache writer checks generation/ownership. Empty registry has setup guidance; backend failures keep full JSON details and truthful footer.
- Verification approach: full existing checkout acceptance, plus cached status during held apply lock, apply invalidation, first-use/offline recovery, interrupted worker then preview, registry/selector changes, conflict abort/retry and rewrite handoff. Assert cache never bypasses signed-preview checks.
- Risks or open questions: older background results after apply; coordinate through existing lock and invalidation generation, reject obsolete publication before unlocking.
- Canonical components/API contracts touched: `CheckoutManager` preview/apply, cache invalidation, shared renderer/JSON and exit behavior (cache metadata distinguish unavailable from stale observations).

## Stage 6 — CLI documentation and operator checks

- Goal: explain the changed default and recovery without repeated boilerplate.
- Dependencies: Stage 5 committed.
- Expected changes: update help/reference for cached semantics, 30s refresh policy, unchecked/stale/queued/busy/error states, synchronous refresh, full details and explicit fresh preview/apply. Explain that network-complete preview need not finish under 1s and cached labels reflect last observation. Document shared state/worker deadline and bounded logs.
- Verification approach: execute documented commands against disposable registries from two project directories; check human/JSON/help and exit consistency, source safety, renderer and existing status/Railspike CLI isolation.
- Risks or open questions: misleading performance or failure claims; tie wording to measured environment and retain explicit uncertainty/diagnosis guidance.
- Canonical components/API contracts touched: `v3` help, `docs/reference/v3_cli.md`, checkout acceptance harness and cycle summary.

## Stage 7 — WSL performance and failure acceptance

- Goal: verify the user's latency and review flow on this host.
- Dependencies: Stage 6 committed.
- Expected changes: record sanitized terminal transcript and machine-readable timing samples for eight disposable clones matching registry roles/shape, plus study closeout. Benchmark at least 10 cold and 20 warm foreground runs, including a slow/offline worker and held apply/registry lock; time through process exit/output completion (detect inherited-pipe waits).
- Verification approach: require each foreground sample <1s on the recorded host; report min/median/p95/max and failures. Replay worker spawn/error/retry, coalescing, cancellation, changed registry, long/narrow columns, current-source preview and recovery. Run focused checkout and affected existing CLI checks; syntax/diff/plan links. No repeated broad suites without unresolved failures.
- Risks or open questions: loaded-machine timing varies; retain failed samples, investigate stalls before claiming target met, distinguish host terminal evidence from inaccessible actual IntelliJ UI. Clean up only self-created fixtures/workers; leave live clone pool untouched.
- Canonical components/API contracts touched: acceptance tests/evidence, Step 4 summary/debug and host ledger; documentation corrections supported by acceptance.

## Execution and Study Checkpoint

- Seven bounded stages (roughly <=1 hour each); split/replan if a stage cannot fit. After Approved Step 3, create `feat/checkout-overview` from the current predecessor implementation, preserving its commits; do not reuse its branch. Move this cycle's plans/debug into `docs/plans/checkout_overview/`, repair links/index and commit only approved plans/organization allowance first. Create Step 4 summary afterward; every stage has checks, summary and its own commit before the next stage. Ledger bookkeeping is committed at its authorized implementation/closeout stage, not planning commit.
- Conceptual contracts: readOverview, launchIfNeeded, refreshObservations, renderRows and inspect/classify/publish with generation ownership. Internal helper signatures settled during implementation; no external API/database contract changes.
- Request record: cache recommendation and columns are approved Step 2 v1; refresh retry command, width behavior, bounded worker/concurrency and benchmark sampling are in-scope refinements / implementation pending. No new service, automatic discovery, migration or live enrollment added.
- 2026-10-23T14:33:16+00:00; Step 3 total projection **unavailable pending exact frozen R1 feedback recovery**, reaffirming scope v1, baseline `1edbe534d7faf0c84c3b4d0ddc3e60fef3a38beb`, R1/P2, Codex single exposed rater. Earlier missing estimates preserved, no reconstructed intake or measured active work. Prior implementation/timing exposure remains disclosed; maintainer acceptance pending, accepted count unchanged.

**Approved Step 3** received. Implement on `feat/checkout-overview` under the approved staged plan.
