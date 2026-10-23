> **Feature plan:** [Step 1](./concurrent_checkouts_step1_solution_assessment.md) · [Step 2](./concurrent_checkouts_step2_feature_description.md) · [Step 3](./concurrent_checkouts_step3_development_plan.md) · [Step 4](./concurrent_checkouts_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/concurrent_checkouts`; [ledger](../effort_estimation_trial.md), R1/P2, scope v4. Step 3 approved; planning commit `b1b33e0b8`; original baseline `19f1066a`, rewritten entry `1a358bc9`. Entry projection reaffirmed unavailable pending exact frozen feedback recovery; earlier snapshots preserved. Codex single exposed rater. No numeric score or measured effort invented.

## Stage 1 - Registry and command entry

- Changes: private atomic shared registry, add/list/remove, source/path validation and shared-storage duplicate rejection; `v3` dispatcher and human/JSON entry.
- Verification: `python3 tests/test_checkouts.py`: initial run had 4 passes and a JSON error-mode failure; corrected option pre-detection, then 5 real-Git cases passed (lifecycle/permissions, aliases/shared storage, malformed/missing paths, roles/help, credential rejection); PHP syntax checks passed.
- Notes: no live checkout registrations; stages 2–7 pending. Other local planning-ledger changes remain pending and outside the initial planning commit.

## Stage 2 - Fetch and history evidence

- Changes: selected-source fetch without tags/submodules, identity/state inspection, persistent validated ancestry baselines and sticky rewrite blocks, conservative unknown-divergence handoff, role/path selection.
- Verification: `python3 tests/test_checkouts.py`: 10 real-Git tests passed, including observed and already-fetched rewrites, unknown versus validated feature divergence, dirty/busy and source mismatch. Working HEAD remains unchanged by preview.
- Notes: Stage 1 commits `5c296bac3`, correction `3bd230353`; initial stage check failure is recorded. A baseline proves continuity from observed enrollment, not universal history authenticity; uncertain branches block. Saved preview/UI pending Stage 3.

## Stage 3 - Saved previews and output

- Changes: private signed preview snapshots with registry/source/HEAD identity, opaque IDs, human actions/reasons and JSON parity; attention exit 2 and fetch side-effect notice. Source URLs/errors remain sanitized; selected/excluded roles visible.
- Verification: `python3 tests/test_checkouts.py`: 12 cases passed, including private saved-preview permissions, human guidance, missing paths and invalid selectors; PHP syntax passed.
- Notes: Stage 2 commit `8721d5963`. Apply and preview-consumption validation follow in Stage 4; no live pool touched.

## Stage 4 - Revalidated fast-forward application

- Changes: session-coordination assertion, signed-preview/registry validation, tool/common-storage locks, stale snapshot rejection, fast-forward-only Git update, per-folder durable attempt/results and no preview replay.
- Verification: `python3 tests/test_checkouts.py`: 17 real-Git tests passed, including invalid/tampered preview, registry/HEAD/branch/source/dirty changes, session flag, lock contention and interrupted-result replay rejection.
- Notes: Stage 3 commit `f3527f330`. External writers must still be paused; tool locks coordinate only this tool using the same state directory. Preview applies its fetched commit, not an unobserved later remote tip.

## Stage 5 - Feature merge and recovery

- Changes: ordinary feature merges preserve parents/local commits; conflicts retain recovery state with path-specific continue/abort guidance, independent folders proceed; post-update checks, ahead/main-divergence handling, missing-identity/hook failures, explicit remove/re-enrollment archives history evidence after reviewed reconciliation.
- Verification: `python3 tests/test_checkouts.py`: 22 tests passed. Added merge-parent checks, conflict plus independent success, abort/retry, missing identity, failed hook, non-fast-forward main exclusion and sticky-history re-enrollment. Initial identity fixture exposed overly broad Git environment stripping; narrowed it to repository-routing variables and reran successfully.
- Notes: Stage 4 commit `830d2bd40`. Tool never resets/reconciles branches; manual rewrite fixture reset is test setup only. Git timeout is an uncertain operation requiring inspection, never automatic replay.

## Stage 6 - Operator documentation and integration

- Changes: CLI reference covers registry, setup/selectors, role exclusions, history-baseline limitations, source failures, preview side effects, session coordination, conflicts and interruption recovery; help includes exits and custom-state apply command. Registry mutation revision invalidates old previews even after remove/re-add. Added normal PHP test-harness wrapper for the Python standard-library real-Git fixtures.
- Verification: 25 real-Git cases passed directly and via `php tests/run.php CheckoutsCommandTest` (1 wrapper passed). Existing `StatusCommandTest RailspikeSyncCommandTest`: 19/19 passed. PHP/shell syntax and diff checks passed. Tests exercise shared registry from two directory contexts, help/options and custom apply guidance.
- Notes: Stage 5 commit `f55adab58`. Optional automatic discovery remains deferred. No production/IDE settings or live registry writes.

## Stage 7 - WSL terminal acceptance and handoff

- Changes: disposable on-host terminal replay saved in [terminal transcript](./terminal_acceptance.txt); acceptance refinements protect ignored local files, avoid recursive submodule worktree updates, enforce private state storage and detect directory/storage replacement. History evidence now retains the last observed source independently of the branch baseline, covering an externally fetched rewrite beyond that older baseline. Empty preview gives registration guidance; human output summarizes results.
- Verification: PASS — final normal harness `php tests/run.php CheckoutsCommandTest StatusCommandTest RailspikeSyncCommandTest`: 20/20 PHP checks, including a wrapper containing 32 real-Git acceptance cases; extra real process-group interruption case passed separately (33 acceptance cases total). Tested first-run/known divergence, externally fetched rewrites, ignored-file protection, post-merge-hook changes, replaced directories with identical HEADs, XDG sharing from two project directories, unavailable source and busy skips. Actual interrupted merge leaves an `attempting` journal and blocks replay. PHP/shell syntax and diff checks passed.
- Notes: Stage 6 commit `1150f255d`. Host kernel `6.6.87.2-microsoft-standard-WSL2`; all mutations were confined to disposable fixtures/private test registries. Terminal transcript sanitizes temporary paths. Full project suite not run; scoped CLI checks cover affected behavior. Actual IntelliJ UI interaction and branch-title plugin installation/behavior remain unverified and user-side; no automated IDE change was in scope. No live enrollment, merge to main, deployment or push performed.

## Request Disposition and Refinements

- Approved task: implement Step 3's seven-stage routine synchronization slice / completed on `feat/concurrent-checkouts` with stage commits / no new scope approval needed.
- In-scope corrections: invalid-option JSON handling, preserving intended Git environment settings, archive/reset of explicit enrollment evidence, registry revision invalidation, last-observed-source rewrite detection, storage identity rechecks, ignored-file and post-hook outcome verification / verified above; stage commits recorded in adjacent notes and final debug handoff.
- Deferred: automatic discovery/enrollment, automated migration, other local branch/worktree reconciliation, IDE setup and live-pool updates. Manual earlier migration remains separate operational work.

## Study Outcome and Release Limits

2026-10-23T10:09:28+00:00; stable cycle `v3/concurrent_checkouts`, scope v4, R1/P2. Step 4 entry projection remains unavailable pending exact frozen feedback recovery; prior missing projections remain missing. Separately labeled retrospective scope assessment: numeric assessment also unavailable against the incomplete frozen reference set; delivered scope qualitatively includes seven stages through real-Git and WSL terminal recovery acceptance. No measured active-work figure inferred from test duration or commits. Codex single exposed rater; maintainer comfortable-fit and acceptance pending, accepted-count unchanged.

Original baseline `19f1066a` retained; rewritten entry `1a358bc9`, planning `b1b33e0b8`. Stage 1 `5c296bac3` plus corrective `3bd230353`; Stage 2 `8721d5963`; Stage 3 `f3527f330`; Stage 4 `830d2bd40`; Stage 5 `f55adab58`; Stage 6 `1150f255d`; Stage 7 revision is recorded in the final debug handoff after commit. All seven stages have focused evidence and summary updates; application behavior is available locally through `./v3 checkouts`. Only selected checked-out branches are managed. Timeout may leave child hooks/processes requiring inspection; tool locks do not make active external sessions safe. No universal history detection/erasure or runtime-application acceptance claim.
