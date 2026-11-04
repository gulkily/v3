> **Feature plan:** [Step 1](./fast_default_tests_step1_solution_assessment.md) · [Step 2](./fast_default_tests_step2_feature_description.md) · [Step 3](./fast_default_tests_step3_development_plan.md) · [Step 4](./fast_default_tests_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/fast_default_tests`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.75

## Original Query

Please write a new Step 1 plan for getting our default test run down to 2 minutes. You can commit and push it right away because we'll pick it up later.

## Understood Intent

- Earlier the same evening the maintainer asked whether too much time goes to waiting for tests after each task. Measured on 2026-11-04 (2-core cloud workspace, `main` at `ed5cbc0`): `php tests/run.php` ran 1,919 tests in 880 s with 15 failures that also fail on untouched `main` here (8 GPG-agent start failures in `FrmClientTest`, plus 7 others).
- Where the time goes: 136 tests of 1 s or more take 648 s; the other 1,783 take 232 s. `PlatformDocsStaticTest` (2 tests) takes 253 s, `WriteApiSmokeTest` (209 tests) 192 s, `CheckoutsCommandTest` (1 test) 62 s. Tests run one at a time.
- "Default run" means what `./v3 test` does with no arguments. The full suite stays available and still runs before merge or deploy.
- The maintainer explicitly authorized committing and pushing this Step 1 before approval so it can be picked up later; that does not approve it.

## Problem Statement

The default test run takes about 15 minutes and ends with failures that also happen on `main`, so every task waits a long time for a result that still needs manual comparison.

## Concepts touched

- All options: proposed *test tiers* (which tests run by default) and *test run history* (`tests/Support/TestRunHistoryStore.php`, already records durations; used, possibly changed).
- Option B: proposed *parallel test runner*.
- Option C: fixture setup in `WriteApiSmokeTest`, `LocalAppSmokeTest` and 11 other test files that build Git repositories per test (changed).

## Solution Options

### Option A: Fast default tier

**Estimate: 0.35**

Mark slow tests (for example, over 1 s in recorded history) as `slow`. `./v3 test` runs the rest; `./v3 test --all` runs everything and is required before merge. Tests that need an unavailable tool (such as a working GPG agent) report as skipped with the reason, not failed.

- Pros: small, low-risk runner change; immediate gain; skipped-not-failed makes a red result meaningful.
- Cons: on its own the default run is still about 232 s here, missing the target; slow tests run less often.
- Driver: choosing and maintaining the tier boundary. Uncertainty: low.

### Option B: Parallel runner

**Estimate: 0.60**

Run test classes in N worker processes, balanced by recorded durations, with per-worker temp directories and ports, and merge results into one report.

- Pros: speeds up both the default and the full run; scales with cores.
- Cons: tests that share fixed paths, ports or environment variables will clash and need fixes; gain depends on the machine (2 cores here); the 253 s class must be split to benefit.
- Driver: finding and fixing shared-state assumptions. Uncertainty: how many tests clash.

### Option C: Cheaper fixtures

**Estimate: 0.85**

Build fixture repositories and read models once per run and copy them per test, instead of `git init`, commit and rebuild each time; start with `WriteApiSmokeTest` (about 0.9 s per test).

- Pros: faster on every machine and in every tier; no change to what is tested.
- Cons: edits many test files; risk of tests leaking state through shared fixtures; largest effort.
- Driver: breadth of test-file changes. Uncertainty: how much of the per-test cost is Git versus PHP startup.

### Option D: Change-aware selection

**Estimate: 0.50**

Run only tests mapped to the files a change touches, falling back to the full suite for unmapped files.

- Pros: smallest runs for small changes.
- Cons: the mapping goes stale and misses regressions; does not help broad changes; least predictable.
- Driver: building and maintaining the mapping.

## Recommendation

**Option A plus Option B.** The default run becomes the fast tier run in parallel, with unavailable-tool tests skipped and explained; the full suite also runs in parallel and stays mandatory before merge. That reaches 2 minutes on most machines with 4 or more cores; on this 2-core workspace it is borderline, so Option C's fixture work is the follow-up if the target is not met. Option D is not recommended.

**Vertical-slice viability:** a contributor runs `./v3 test` after a change and gets a pass/fail result in about 2 minutes, with skips listed and no known-environment failures. `./v3 test --all` gives the full result faster than today. Verify on this workspace and on the maintainer's usual machine, recording core counts and times.

**Open question for Step 2:** which machine sets the 2-minute target (the maintainer's laptop, the server, or 2-core cloud workspaces)?

## Enhancement Requests

- The 7 non-GPG tests failing on `main` here may be stale. Disposition: Step 2 decides whether fixing or marking them is in scope.

## Study Snapshots

2026-11-04T02:07:00Z; scope v1; starting revision `ed5cbc07ddcf8810bfe35d23c4bd3d82f062e9ae`; reference R1 (all three frozen source hashes verified). Claude, single exposed rater; full-suite timings were collected before rating, so no pristine pre-inspection estimate is claimed.

- Intake after inspection: **0.75 projected total** for Options A plus B (tiering, skip reporting, parallel runner and the shared-state fixes it exposes).
- Step 1 handoff: **0.75**. Alternatives A 0.35, B 0.60, C 0.85, D 0.50, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Status: Step 1 drafted for review; awaiting Approved Step 1. Committed and pushed at the maintainer's request.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
