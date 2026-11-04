Cycle: `v3/smoke_tests`; Mode: one-shot; Record/index: this file, [plans index](../README.md); builds on [`fast_default_tests_outliers`](../fast_default_tests_outliers/fast_default_tests_outliers_one_shot.md) (PR #15, unmerged); supersedes the parked [`fast_default_tests` Step 1](../fast_default_tests_step1_solution_assessment.md) as the route to fast per-task runs
Debug mode: enabled
Effort study: `railspike-effort` / `v3/smoke_tests`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages exist, so only the cycle estimate applies)
Debug report: [log](smoke_tests_debug_log.md)

**Cycle estimate: 0.35**

Status: committed and pushed on `feat/smoke-tests` (based on `feat/fast-default-tests-outliers`); next permitted action: maintainer review; merge only on request.

## Original request

2026-11-04, maintainer, in sequence: "From now on, let me handle running the full test suite, period. I can do it periodically instead of us waiting for it every task we do." Then: "When we do a task, we should only run a subset of the test suite, period. Maybe we should compile this set of smoke tests?" Claude proposed a curated smoke set and asked whether plain `./v3 test` should become the smoke run and who picks the list. The maintainer replied: "I think you're up to just building it. Period. Yes, do follow agents.md. And update the process in the repo."

## Authorization

Explicit one-shot ("just building it"), with the two open questions left to Claude. Bounded outcome: a curated smoke set, a runner that runs it by default and combines it with named tests, and the per-task test process written into the repository. Exclusions: parallel runner, changes to the tests themselves, merge or deploy.

Decisions taken under that authority: plain `./v3 test` becomes the smoke run, and `--all` is the maintainer's periodic full run. Claude picked the list from the timings of the 2026-11-04 full run. The interim tier from PR #15, which left three slow classes out of the default run, is replaced, because the smoke set leaves out far more.

## Outcome and work type

Application work: test runner, the smoke list, a runner test, and contributor/agent process docs. Work item `thread-20261104120908-c9ac769efeedadcff72749a35d9a23cb` (claimed; takes effect once this workspace's signing identity is approved).

## Approach and risks

`tests/smoke.txt` lists class or `Class::method` names in plain text, one per line with `#` comments, following the existing named subsets such as `./v3 agent-reply test-local`. The runner adds those names to the filters when there are no arguments or `--smoke` is given. Risks: the list goes stale (mitigated: the runner exits 1 on an entry that no longer exists); the set grows slow (mitigated: CONTRIBUTING says to keep it near one minute and swap slow tests out); a default run prunes history (only a full `--all` run prunes).

## Concepts touched

None in the concept inventory.

## Verification and recovery plan

`php tests/run.php TestRunnerBehaviorTest` with new cases for the default, `--smoke` plus names, names alone, and a missing entry; one timed smoke run. Recovery: revert the runner hunk; `--all` and named runs keep working.

## Starting revisions and branch

`feat/fast-default-tests-outliers` at `9459c7479c54f28a6d38c060a4a5293d9d6fb48c` (PR #15, itself based on `main` at `8dac3f9`); branch `feat/smoke-tests`. PR targets #15's branch, so its diff shows only this cycle.

## Dated execution history

- 2026-11-04 — Smoke set: 38 classes chosen across canonical parsing, read-model rebuild, routing and pages (including the broad `LocalAppSmokeTest`), rendering, signed writes, approval and access, activity, messages, offline, CLI, and the runner's own tests. The slow classes (`WriteApiSmokeTest`, `PlatformDocsStaticTest`, `CheckoutsCommandTest` and other multi-second classes) stay out; area tests run with `--smoke Name`.
- 2026-11-04 — Process written into the repository: an `AGENTS.md` section "Tests" (per task, run the smoke set plus touched tests; do not run or wait for `--all`; say the full suite was not run), a `CONTRIBUTING.md` "Tests" section with the command table and the rules for maintaining the list, and updated `README.md`, `docs/reference/v3_cli.md` and `./v3` help. The vendored Railspike rule that a focused pass is not a full-suite pass is left as is; it fits the new process.

## Study history

- Intake: missing (the request followed directly from the discussion, and the timing data was already inspected). Execution entry: **0.35**, scope v1, single exposed Claude rater. Closeout retrospective: 0.35. Not hours or measured labor; maintainer acceptance pending.

## Outcome

`./v3 test` now runs the smoke set: 428 tests in 46 s on the 2-core workspace. `./v3 test --smoke FooTest` adds the tests for a change, and `./v3 test --all` runs everything.

## Verification

- `php tests/run.php TestRunnerBehaviorTest`: 7/7 passed, including the new smoke cases.
- `php tests/run.php` (smoke set): 428 run, 424 passed, 4 failed, 46 s. The 4 failures are `LocalAppSmokeTest` (3) and `ReadModelBuilderTimingTest` (1), which fail on `main` and are fixed in PR #16; this branch does not contain that fix. With #16 merged, the smoke set should be green; that combination has not been run.
- Full suite: not run (the maintainer runs it).

## Commit, merge and publication status

Committed and pushed on `feat/smoke-tests`; PR opened against `feat/fast-default-tests-outliers`. Not merged or deployed.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
