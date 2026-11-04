Cycle: `v3/preexisting_test_failures`; Mode: one-shot; Record/index: this file, [plans index](../README.md); related: [`fast_default_tests_outliers`](../fast_default_tests_outliers/fast_default_tests_outliers_one_shot.md), parked [`fast_default_tests` Step 1](../fast_default_tests_step1_solution_assessment.md)
Debug mode: enabled
Effort study: `railspike-effort` / `v3/preexisting_test_failures`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages exist, so only the cycle estimate applies)
Debug report: [log](preexisting_test_failures_debug_log.md)

**Cycle estimate: 0.30**

Status: committed and pushed on `fix/preexisting-test-failures`; full suite not yet run; next permitted action: full suite, then maintainer review.

## Original request

After `fast_default_tests_outliers`, Claude proposed next steps; option 2 was to fix the 15 tests that fail on `main` in the 2-core cloud workspace (8 `FrmClientTest` GPG-agent failures plus 7 others), because a red default run needs manual comparison. The maintainer replied "Do 2".

## Authorization

2026-11-04 (chat, maintainer): "Do 2". Bounded outcome: make those 15 tests pass on `main`'s code without weakening what they check; where a test is stale against a deliberate product change, align it with that change and say so. Exclusions: product behavior changes, test tiers or parallelism, merge or deploy. Work on a fresh branch from `main` so PR #15 stays separate.

## Outcome and work type

Application work, tests only. Work item `thread-20261104115254-63245d761e30fdf4607b0fc59cdc794c` created and claimed (the claim takes effect after an operator approves this workspace's signing identity).

## Approach and risks

Reproduce each failure, find whether the cause is the environment or a test that fell behind a deliberate change, and fix the test. Risk: "fixing" a test by asserting whatever the code does now. Mitigation: for each stale test, find the commit that changed the behavior and confirm the change was intentional before updating the test.

## Concepts touched

None changed. Used unchanged: Platform member-only gate, read-model builder progress timings, private-message history revision tokens.

## Verification and recovery plan

Each class before and after with `php tests/run.php <Class>`, then the full suite. Recovery: each test file change is independent and revertable.

## Starting revisions and branch

`main` at `8dac3f9cfda4a1e8833a3072ce47414b1ef21b73`; branch `fix/preexisting-test-failures`.

## Dated execution history

2026-11-04 — causes and fixes:

| Test(s) | Cause | Kind | Fix |
| --- | --- | --- | --- |
| `FrmClientTest` (8) | frm.py keeps its keyring at `<state-dir>/<64-hex site hash>/keyring`. Under `/tmp/signed-http-…/client` the longest gpg-agent socket (`S.gpg-agent.browser`) exceeds the Unix socket path limit, and this workspace has no `/run/user/<uid>` for GPG's fallback socket directory. | Environment | State dirs moved to a short `sys_get_temp_dir()/f<6 hex>` per test root, with keyring agents stopped and dirs removed at exit. The keyring path must stay at 86 characters or fewer, so this leaves 13 characters for the state dir. |
| `ApprovalSeedCommandTest::testUnreadableArtifactDeletionCannotReportSuccess` | `chmod 0555` cannot block deletion when the tests run as root. | Environment | As root, make the file immutable with `chattr +i` (removed in `finally`); otherwise unchanged. The test fails with a clear message if `chattr` is unavailable as root. |
| `LocalAppSmokeTest::testPrivateLobbyOnly…` and `testPrivateForteRoutesRecover…` | Members-only API routes now answer with JSON `access_denied` 403 instead of the HTML approval page, since `e230c168` (2026-10-30, single-request posting API), which tests the new response in `SingleRequestHttpTest`. | Stale test | Assert the JSON `access_denied` code; the no-data-leak assertions are kept. |
| `LocalAppSmokeTest::testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp` | `042e684a` (2026-10-24) removed the method this test reflects into: downloads now go through the repository snapshot path and are named by revision, not timestamp. Its record says those routes are covered. | Stale test | Test removed (and its now-unused import). |
| `ReadModelBuilderTimingTest::testProgressSeparates…` | `c7ae2108` (2026-10-15) renamed the invitation phase `replaying_invitation_posts` → `checking_invitation_candidates`. | Stale test | Expect the new timing key. |
| `ReactionRecordFormatParityTest::testMixedFormatsBuildTheSameReadModelAsLegacy` | The test compares every read-model table across three builds; `history_keys_revision` (private-message history) was added later and holds a random token per build. | Stale test | Skip `*_revision` tables, which hold only random mutation tokens per `PrivateMessageHistoryRevision`. |
| `TerminalOperatorUiCommandTest::testNonInteractiveInput…` | The script checks for `whiptail` before checking for a terminal; `whiptail` is not installed here. | Environment | Set `V3_TUI_WHIPTAIL=true` so the test reaches the terminal check everywhere; the missing-`whiptail` case already has its own test. |

- A first `FrmClientTest` attempt with an 8-hex state dir still failed: the limit is set by `S.gpg-agent.browser`, not `S.gpg-agent.extra`. A second miss: the `glob($root.'/client/*…')` lookups had not been switched to the new helper. Both corrected before verification.

## Study history

- Intake: missing (diagnosis preceded rating). Execution entry: **0.30**, scope v1, single exposed Claude rater. Closeout retrospective: 0.30. Not hours or measured labor; maintainer acceptance pending.

## Outcome

All 15 previously failing tests pass when their classes are run; no product code changed.

## Verification

- Passed, per class: `FrmClientTest` 9/9 (no gpg-agent or state dir left behind), `ApprovalSeedCommandTest` 14/14, `LocalAppSmokeTest` 164/164, `ReadModelBuilderTimingTest` 4/4, `ReactionRecordFormatParityTest` 3/3, `TerminalOperatorUiCommandTest` 3/3.
- Not run: the full suite. A run was started and stopped at the maintainer's request (2026-11-04) before it finished, so a full-suite pass is not claimed. Only test files changed, and each changed class passes, but tests outside these classes were not rerun.
- Not verified: the maintainer's machine, where `/run/user/<uid>` exists, `whiptail` may be installed and tests may not run as root. The environment fixes are written to work either way.

## Commit, merge and publication status

Committed and pushed on `fix/preexisting-test-failures`; no PR, merge or deploy.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
