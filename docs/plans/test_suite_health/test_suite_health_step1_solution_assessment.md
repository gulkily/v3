> **Feature plan:** [Step 1](./test_suite_health_step1_solution_assessment.md) · Step 2 · Step 3 · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/test_suite_health`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.45

## Original Query

> We're wasting a lot of time rerunning tests when they actually fail on main too. We really need to fix the test suite and no longer accept failing tests. How can we make the test suite 100% most efficiently?

Follow-ups (2026-11-09, chat):

> Also, I don't know how we could pull this off, maybe a separate repo, but it would be amazing if all the different clones were reporting the testing results that we're logging in the database.

> Maybe if we have like a dev.zmemes.com to separate this from content?

> Let's write all this down.

## Understood Intent

- `main` should pass the full suite, and a failing test should stop being normal. Today a red run means comparing failures against `main` by hand, and sessions rerun tests to find out whether a failure is theirs.
- Reaching green quickly matters more than fixing every failure before anything else changes.
- Every clone (the maintainer's machines, cloud workspaces, contributors' agents) should report its runs to one shared place, so "does this fail on `main` elsewhere?" is a lookup, not a rerun.
- Machine-generated reports should not crowd human content on zenmemes.com. A separate dev instance (`dev.` subdomain) is the maintainer's suggestion.

## Current State

- `tests/run.php` records results in a local SQLite history (`TestRunHistoryStore`, table `test_run_results`): one row per test with last status, pass/fail streaks, first-failure and recovery times and last duration. It keeps no per-run rows, no commit, no clone identity and no environment details, and it is never shared.
- The runner has no skip, expected-failure or quarantine mechanism. A known failure looks exactly like a new one.
- There is no CI. "Green" is enforced only by agents' and the maintainer's own runs. Agents run the smoke set plus changed tests (`AGENTS.md`); the maintainer runs `./v3 fulltest` periodically.
- [`preexisting_test_failures`](../preexisting_test_failures/preexisting_test_failures_one_shot.md) (2026-11-04) fixed 15 failures on a 2-core cloud workspace, now on `main`. Several were environment-specific (socket path length, running as root, missing `whiptail`), which a single-machine view cannot tell apart from real breakage.
- Baseline (2026-11-09, `./v3 fulltest` on `main` at `88c312a3`, 2-core cloud workspace, one run): 17 failures out of about 2,020 tests, 270 s wall time. Grouped by apparent root cause (not yet diagnosed):

  | Cluster | Tests | Symptom |
  | --- | --- | --- |
  | Offline snapshot | 11 (`PublicOfflineSnapshotBuilderTest` ×4, `OfflineSnapshotBootstrapTest` ×2, `OfflineSnapshotPublishCommandTest` ×2, `PublicOfflineSnapshotManifestTest`, `TaskQueueCommandTest`, `PrivateMessageReleaseIsolationTest`) | `no such column: posts.post_score_total`; the snapshot schema seems to lag a read-model change |
  | Private-message nav flag | 2 (`PrivateMessageFeatureFlagTest`) | navigation and background scripts disagree with the effective flag |
  | Singles | `BoardListTest` (unexpected `rel="prev"`, possibly the readable page URL work), `InvitationIssuanceTest` (`.focus is not a function` in the JS harness), `FrmClientTest::testCharacterCorrection…`, `PlatformDocsPageTest::testAssetsAreInert…` | each needs its own look |

  About six root causes for 17 failures. The runner had no prior history in this workspace, so it could not say which were new. One run cannot show flakiness; Step 2 reruns the failing classes to check.

## Problem Statement

Failures that already exist on `main` are indistinguishable from new ones, so every red run costs a rerun or a manual comparison. Results stay on the machine that produced them, so environment-specific and flaky failures cannot be told apart from real breakage.

## Concepts touched

- Option A: proposed *known failure* (a named, reasoned, expiring exception to "the suite must pass") and *test run report* (one run's commit, clone, environment and results). [Agent responses](../../concepts/agent_responses.md) used unchanged if reports are posted through the signed client.
- Options B and C: the same two concepts; B adds a dedicated v3 instance, C a separate results repository.
- Option D: none.

## Solution Options

The first three options share a green-and-gate core and differ only in where run reports go.

**Shared core (all of A–C):**

1. **Quarantine to green.** A checked-in known-failures file lists each failing test with a reason and a date. The runner treats a listed failure as expected and anything else as a real failure. A listed test that starts passing is reported, so the list only shrinks. `main` is green from the day the file lands.
2. **Gate.** Any failure not on the list fails the run, and `AGENTS.md` makes "no new entries without the maintainer's approval" the rule. The full run stays the maintainer's; agents keep running smoke plus changed tests.
3. **Burn down.** Group the listed failures by root cause and fix them cluster by cluster, sorting each as a real bug, a stale test, an obsolete test or a flaky one, the way `preexisting_test_failures` did.
4. **Run report.** The runner writes one report per run: commit, clone ID, environment fingerprint (OS, PHP, cores, root or not, gpg agent socket dir, `whiptail`), selection (smoke, named, full), totals, failing and known-failing tests, and durations. Per-run rows join the existing history database, so local streaks keep working.

### Option A: Shared core plus reports posted to a dev instance (`dev.` subdomain)

**Estimate: 0.95** (both slices; see Recommendation for the split)

A second v3 instance on the same VPS (its own checkout and data directory, a vhost for the subdomain) receives reports as signed posts from the repo's `frm.py` client. Posting is opt-in per clone and best-effort: a failed post never fails the run. A small view or script answers "which tests fail on commit X, in which clones and environments".

- Pros: dogfoods v3 as a multi-instance framework; the same signing identity works on both instances; keeps machine logs off the main board; the dev instance can also host the posting-trouble thread and serve as a staging site for risky changes before prod.
- Cons: a second deploy and data directory for the maintainer to keep updated; each clone's key needs approval on the dev instance; aggregation needs a reader (script first, page later).
- Tension: moving all development activity to dev would hide the agentic cooperative development that [`site_purpose`](../site_purpose/) wants first-time visitors to see. Split by audience: machine reports go to dev; work items, feature requests and human/agent discussion stay on zenmemes.com.
- Driver: the dev deploy (maintainer operations) and the report format. Uncertainty: how much of key approval carries across instances; whether `frm.py` needs changes for a non-default site.

### Option B: Shared core plus reports posted to zenmemes.com

**Estimate: 0.85**

As Option A, but reports go to one standing thread or a `#testrun` tag on the main site.

- Pros: no second instance to deploy or keep in sync; keys are already approved there.
- Cons: dozens of machine posts a week on the main board; the noise falls on the readers the site is trying to win.

### Option C: Shared core plus a separate results repository

**Estimate: 0.75**

Clones push `runs/<clone>/<timestamp>.json` to a `v3-test-results` repository.

- Pros: plain, git-native files; no server work.
- Cons: every reporting clone needs push access, which contributors' agents working through PRs do not have; push races between parallel sessions; nothing to read but raw files unless a reader is built.

### Option D: Fix every failure first, no quarantine or reporting

**Estimate: 0.40** (depends on the baseline)

- Pros: no new mechanism.
- Cons: nothing is enforced until the last fix lands, and the next pre-existing failure starts the cycle again; environment-specific failures stay invisible. This is what `preexisting_test_failures` did, and new failures have accumulated since.

## Recommendation

**Option A, delivered as two cycles.**

1. **This cycle (`test_suite_health`): the shared core.** Known-failures file, gate, local per-run report (written to the history database and to a file), the `AGENTS.md` rule, and burning down the baseline's failures. **Cycle estimate 0.45.** Fixes that need product changes are split out rather than folded in.
2. **Follow-up cycle (`test_run_reporting`): sharing.** Post reports to the dev instance, plus the aggregation reader. Its Step 1 settles the dev instance setup, key approval there and the report post format.

The report format is defined in this cycle so the follow-up only adds transport. Option B remains a fallback if the dev instance stalls: switching the destination is a configuration change, not a redesign.

**Vertical-slice viability:** after this cycle, a full run on `main` is green with every remaining known failure listed and reasoned; a session that hits an unlisted failure knows it is new without rerunning on `main`; and each run leaves a report a later cycle can post.

## Questions for Step 2

- Who may add a known-failures entry: maintainer only, or agents with a reason and a linked work item?
- Should a known failure expire (for example after 30 days) and fail the run again until it is fixed or re-approved?
- Flaky tests: quarantine like other failures, or rerun once and record the flip? The same-commit flip is the flakiness signal the reports need either way.
- Clone ID: random per checkout, or derived from the signing identity plus host?

## Enhancement Requests

- Dev instance as a staging site before prod merges (the maintainer currently reviews PRs by merging into prod). Disposition: noted for the follow-up cycle's Step 1; not in this scope.
- Known failures and run reports could be a general Railspike pattern for host projects. Disposition: not in this scope.

## Study checkpoint

2026-11-09T10:32:15Z, `railspike-effort` / `v3/test_suite_health`, intake and Step 1 handoff (standing P2 enrollment, no parent; related to `preexisting_test_failures`). Scope v1: the shared core of Option A (quarantine, gate, local run report, burn-down). Starting revision `88c312a3fadbf72f5d3803b66fbce341a99fc2f0` on `main`. Reference R1, protocol P2; R1 hashes not re-verified. Single exposed Claude rater. Pristine intake is missing: the rater read `tests/run.php`, `TestRunHistoryStore`, `CONTRIBUTING.md` and the `preexisting_test_failures` record, and started a full baseline run, before rating. The burn-down share depends on that baseline, which had not finished. Intake and Step 1 projected total **0.45**. Option estimates A 0.95, B 0.85, C 0.75 and D 0.40 are alternatives over different scopes and do not add up. No implementation, measured labor or accepted outcome.

Status: Step 1 drafted; awaiting Approved Step 1. Planning records uncommitted.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
