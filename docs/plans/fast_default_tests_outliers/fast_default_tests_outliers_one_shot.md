Cycle: `v3/fast_default_tests_outliers`; Mode: one-shot; Record/index: this file, [plans index](../README.md); parent: [`fast_default_tests` Step 1](../fast_default_tests_step1_solution_assessment.md)
Debug mode: enabled
Effort study: `railspike-effort` / `v3/fast_default_tests_outliers`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages exist, so only the cycle estimate applies)

**Cycle estimate: 0.30**

Debug report: [log](fast_default_tests_outliers_debug_log.md)
Status: scope v2 complete and pushed on `feat/fast-default-tests-outliers`; next permitted action: maintainer review; merge only on request.

## Original request

After reviewing the `fast_default_tests` Step 1, the maintainer agreed with a review point that the three slowest test classes (`PlatformDocsStaticTest` 253 s, `WriteApiSmokeTest` 192 s, `CheckoutsCommandTest` 62 s) should be investigated before tiering or parallelism, and asked: "Yes, let us fix those heaviest tests first, do you agree to one shot?"

## Authorization

2026-11-04 (chat, maintainer): explicit one-shot execution to fix the heaviest tests. Bounded outcome: make the three outlier classes faster by removing waste, without reducing what they verify. Exclusions: no test tiers, parallel runner, skip-not-fail handling or fixture rework (those stay in the parked staged cycle); no merge, push or deploy (one-shot authorization does not grant them).

## Outcome and work type

Application work, test-and-tooling scope: test files, `tests/run.php`, and a behavior-preserving fix in code that turns out to cause the slowness. The parked staged cycle `fast_default_tests` keeps its own Step 1, unapproved and unchanged; this cycle is a separate, linked piece of work.

## Approach and risks

Profile each class, find the dominant cost, and fix it at the source. Risks: a speed change that silently weakens a test; a product change that alters behavior. Mitigation: keep every assertion, run the affected classes before and after, and run the full suite.

## Concepts touched

None in the concept inventory. Used unchanged: Platform Docs catalog snapshot (`PlatformDocsCatalog::withSnapshot`), checkout manager Git helper. No inventory updates proposed.

## Verification and recovery plan

Ordinary entry: `./v3 test` (`php tests/run.php`) and per-class filters. Before/after timings for each class on this 2-core cloud workspace; full-suite comparison against the 15 known failures recorded in the parent Step 1. Recovery: each change is independent and revertable on its own.

## Starting revisions and branch

`main` at `8dac3f9cfda4a1e8833a3072ce47414b1ef21b73`; branch `feat/fast-default-tests-outliers`.

## Dated execution history

- 2026-11-04 — Work item `thread-20261104020942-96f27522` ("Get the default test run under 2 minutes") found and claimed with `./v3 work claim`; the claim posted but takes effect only after an operator approves this workspace's signing identity. (The default key directory failed to start `gpg-agent`; a short `V3_WORK_FRM_STATE_DIR` worked, consistent with GPG's socket-path limit noted in the work-tracking docs.)
- 2026-11-04 — Process correction: this record was written after profiling and the first two edits had started, not before implementation as the one-shot workflow requires. The edits were already within the authorized boundary above; nothing was committed before the record existed.
- 2026-11-04 — `PlatformDocsStaticTest` (208 s here): the test publishes the docs twice (~23 s each) and then compares 609 prebuilt browse pages with live renders. Outside a build, every live `/docs/` render rereads all 2,253 docs (~0.23 s), so the loop cost ~140 s. Fix: run the comparison loop inside `PlatformDocsCatalog::withSnapshot`, the same helper the publisher uses for a build. Docs do not change during the loop, so every page is still compared. Product behavior unchanged.
- 2026-11-04 — `CheckoutsCommandTest` (63 s here): ~50 real-Git acceptance tests, ~230 `./v3 checkouts` invocations. A syscall trace of one `preview` showed ~70 fixed 10 ms sleeps: `CheckoutManager::git()` polls a finished child with `usleep(10000)` while most Git calls finish in 1–3 ms. Fix: wait with `stream_select` on the output pipes (wakes on output or EOF), keeping the 30 s and deadline kill paths and the exit-status check; if both pipes reach EOF while the process still runs (a hook holding them), it falls back to a 1 ms poll instead of busy-waiting. `preview` dropped from ~430 ms to ~125 ms, so operators get the same speedup.
- 2026-11-04 — `WriteApiSmokeTest`: each write commits to a throwaway Git repository, and the test runner inherited the developer's global Git config. In cloud workspaces that config signs every commit through an external program (~0.1 s per commit) and runs auto-maintenance. Fix: `tests/run.php` points `GIT_CONFIG_GLOBAL` at a temporary file with a test identity, signing off and auto-maintenance off. Repository-level and `-c` settings still win; `AppUpdateTest` already saves and restores this variable. The gain depends on the machine: none where commits are unsigned.
- 2026-11-04 — Verification (below). No enhancement requests arose; nothing was added beyond the authorized boundary.

## Study history

- Intake: missing. Profiling of all three classes preceded the first rating, so no pristine projection exists.
- Execution entry (2026-11-04T04:50Z, after profiling, before the record and the third fix): **0.30**, scope v1, single exposed Claude rater.
- Closeout: delivered scope v1 in full; no revision. Retrospective scope assessment, labeled separately: 0.30. Not hours or measured labor; maintainer fit and acceptance pending.

## Outcome

All three outliers are faster, with every assertion kept:

| Class | Before (s) | After (s) |
| --- | ---: | ---: |
| `PlatformDocsStaticTest` | 208 | 62 |
| `CheckoutsCommandTest` | 63 | 28 |
| `WriteApiSmokeTest` | 183 | 122 |
| Full default run (`php tests/run.php`) | 880 | 486 |

"Before" for the three classes was measured on this branch's base before the edits; the full-run "before" is the parent Step 1 measurement on `ed5cbc0`, same workspace type. The full run also gained from other tests that commit to Git, since the isolated config applies everywhere.

Remaining distance to the 2-minute goal: 96 tests of 1 s or more now take 299 s, and the other 1,823 take 186 s. `PlatformDocsStaticTest` is now dominated by two full docs publishes (~23 s each); `WriteApiSmokeTest` averages ~0.6 s per test across many signed writes. Tiering and parallelism (parked Step 1) remain the route to 2 minutes; the parked Step 1's own numbers are now stale.

## Verification

All on a 2-core cloud workspace, PHP 8.3.6, Git with global commit signing configured (that last point is why the Git-config change helps here).

- Passed: `php tests/run.php PlatformDocsStaticTest` 2/2; `CheckoutsCommandTest` 1/1 (wraps 50+ real-Git acceptance tests, including the 30 s hook interruption case); `WriteApiSmokeTest` 209/209.
- Full run: 1,919 run, 1,904 passed, 15 failed. Same count and split as the parent baseline: 8 `FrmClientTest` GPG-agent failures plus 7 others. The 7 others (`ApprovalSeedCommandTest` 1, `LocalAppSmokeTest` 3, `ReadModelBuilderTimingTest` 1, `ReactionRecordFormatParityTest` 1, `TerminalOperatorUiCommandTest` 1) were rerun on an untouched `main` worktree and fail there too; they are pre-existing, not caused here. Not a full-suite pass.
- Manual: `./v3 checkouts preview` on a scratch clone, ~430 ms → ~125 ms, same JSON output.
- Not verified: the maintainer's usual machine; a machine without commit signing (expected gain there is from the first two fixes only).

Recovery: each of the three changes is a separate hunk and can be reverted on its own. If a test needs the developer's real global Git config, it can set `GIT_CONFIG_GLOBAL` itself, as `AppUpdateTest` does.

## Commit, merge and publication status

v1 committed as `c08f2b7` on `feat/fast-default-tests-outliers` and pushed at the session's request; v2 in the following commit on the same branch. Not merged or deployed. Work item claim posted; its status line awaits operator approval of this workspace's signing identity.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Scope revision v2 — 2026-11-04

Authorization: after v1 was committed (`c08f2b7`) and pushed, the maintainer wrote "Remove these tests from default run", read as the three outlier classes above. This renews one-shot authority for a narrow tier; it does not approve the parked Step 1's broader Option A (history-based boundary, skip-not-fail for missing tools) or Option B. v1 scope and its record above are retained unchanged.

Changes:
- `tests/run.php`: `NOT_IN_DEFAULT_RUN` lists `PlatformDocsStaticTest`, `WriteApiSmokeTest`, `CheckoutsCommandTest`. A run with no arguments skips them and names them after the summary; `--all` runs everything; naming a class or method still runs it. History pruning of vanished tests now happens only on a complete run, so a default run cannot forget the left-out tests' history.
- `./v3` help, `README.md` and `docs/reference/v3_cli.md`: document `--all` and that it runs before merge or deploy.
- Correction found during verification: `test_independent_fetches_overlap_and_foreground_stays_fast` failed about 1 run in 5 because the shell's `>` creates its marker file empty before `date` writes the stamp, and the test parsed the file as soon as it existed. v1's faster Git wait likely made the race show up more often. The test now waits for a written stamp; assertions unchanged.

Study: execution-entry projection for v2 revised 0.30 → 0.40 (original retained), same rater and baseline. Closeout retrospective for v1+v2: 0.40.

Verification (v2):
- Default `php tests/run.php`: 1,707 run, 1,692 passed, 15 failed (the same pre-existing failures as v1), **241 s wall**, ending with the left-out list.
- `php tests/run.php CheckoutsCommandTest` runs the named class; the race test passed 10/10 isolated and the whole checkouts suite passed twice after the fix.
- Not rerun after v2: `--all` end to end. The logic only adds classes back, and the three classes passed individually above; the v1 full run (486 s) is the closest evidence.

The default run is still about 2× the 2-minute goal on this workspace; the remaining time is spread across ~1,700 tests, so parallelism (parked Option B) is the next lever.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
