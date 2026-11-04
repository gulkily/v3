> **Feature plan:** [Step 1](./work_tracking_step1_solution_assessment.md) · [Step 2](./work_tracking_step2_feature_description.md) · [Step 3](./work_tracking_step3_development_plan.md) · [Step 4](./work_tracking_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/work_tracking`; [host ledger](../effort_estimation_trial.md#cycle-v3work_tracking). Standing P2 enrollment.

Extended estimates: enabled

Status: Stages 1–7 implemented on `feat/work-tracking`; Stage 8 (production pilot) waiting on deploy.

**Cycle estimate: 1.00** (Step 4 entry, reaffirmed)

## Study entry

2026-11-04T00:55:14Z; planning commit `be2990e`; scope v2/A, original baseline `561f7dd1113c3021ba0d7d1b28361865c40de2e2`, R1/P2. Step 3 whole-cycle projection **1.00 reaffirmed** before implementation. Stage-work originals copied from the approved plan (Claude, single exposed rater; copying is not a new rating): S1 0.15, S2 0.12, S3 0.15, S4 0.06, S5 0.12, S6 0.15, S7 0.12, S8 0.10. Non-additive.

## Stage 1 - Thread status from `status:` replies

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.12**

- Changes:
  - New `src/ForumRewrite/Work/WorkStatusDirective.php`: `extract(string $body): ?array{status, note}`; same line rules as `TitleDirective` (quoted lines skipped, first valid match wins, case-insensitive), values `open|active|done|dropped`, optional note up to 200 characters.
  - New `src/ForumRewrite/Work/WorkItems.php`: `isWorkThread()`, `item()`, `list(words, status, now, staleDays)`. Status is derived at read time from posts by verified, approved, unretired members; no directive means `open`; staleness is `active` with no thread activity for 7 days.
  - Thread page shows a single `Status:` line on `#work` threads only (`ThreadAndPostPageController::thread`, `templates/pages/thread.php`).
  - New `tests/WorkItemsTest.php`, registered in `tests/run.php`.
- Verification:
  - `php tests/run.php WorkItemsTest`: 4/4 passed (directive parsing; latest-valid-wins ignoring unapproved, guest, unverified and retracted lines; staleness, filters, stable order, retraction; thread page renders status only for work threads).
  - `php tests/run.php LocalWriteServiceTitleDirectiveTest RailspikeFeatureFlagTest RailspikeSyncCommandTest RailspikeWorkflowTest ThreadTitleTest ThreadTitleWithdrawalAndConsumersTest`: 53/53 passed.
  - Rebuild parity holds by construction: nothing is stored beyond existing post rows. Not separately tested.
  - Phone/desktop visual check: not done in this stage; the line reuses the existing `meta` style. Deferred to the Stage 8 production check.
- Notes:
  - Deviation from plan wording: derivation happens at read time rather than in a new read-model column, which avoids a schema change. Same behavior.
  - Static thread artifacts refresh when a reply is posted, so the status line follows the existing invalidation.
- Estimate details: original scope as planned; no revision; retrospective lower because no schema or write-path change was needed.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 2 - `/work.txt` lookup

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.08**

- Changes:
  - New `src/ForumRewrite/Work/WorkListText.php`: `text(items)` renders a header line, then one tab-separated line per item (`status`, thread ID, title, set by, note, `/threads/<id>`); stale items read `active (stale)`; tabs and newlines in cells collapse to spaces.
  - `Application::handle`: `GET /work.txt?q=<words>&status=<open|active|done|dropped|stale>`, `Cache-Control: no-store`; route added to `isApplicationRoute`. Members-only instances gate it like other content (not added to the onboarding allowlist).
- Verification:
  - `php tests/run.php WorkItemsTest`: 5/5 passed, including in-process `GET /work.txt` with word and status filters, the empty result, and one-line cells.
  - Timing: 20,000 threads (200 work items) and 100,000 posts in memory, `WorkItems::list` 9.4 ms.
  - Unicode titles: covered by existing multibyte-safe functions; no separate test.
- Notes:
  - Deviation: the plan said to reuse the `/search` path. Work items are a small tagged set, so a direct filter over them is simpler and avoids search ranking issues; same contract.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 3 - `./v3 work` CLI with offline claims

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes:
  - New `scripts/work_cli.py` (Python 3 standard library only) and `./v3 work` dispatch:
    - `search [words] [--status S]` prints `/work.txt`.
    - `claim THREAD [--note] [--force]` refuses an item already `active` unless forced; default note is host and branch.
    - `status THREAD open|active|done|dropped [--note]`.
    - `flush` posts queued entries in order and keeps the rest on the first failure.
  - Writes post a `status:` reply through `public/frm.py` (one signed request, its own resume/retry), then re-read `/work.txt` to confirm the status took effect.
  - Site unreachable (connection error or 5xx): the entry is queued in `state/work/pending.jsonl` (already git-ignored), exit 3. A failed frm.py write after the site answered is not queued, to avoid double posting; frm.py's recovery instructions apply.
  - `V3_WORK_SITE`, `V3_WORK_STATE_DIR` and `V3_WORK_FRM_STATE_DIR` override the site, queue location and frm.py key directory.
- Verification (local `./v3 start 127.0.0.1:8123` against a temporary copy of `tests/fixtures/parity_minimal_v1` plus one `#work` thread):
  - Claim with the server down: queued, exit 3.
  - Server up, `flush`: reply accepted in 1.1 s; queue emptied; CLI reported that the line does not count because the new identity is unapproved.
  - After `./v3 approval seed <identity>`: `/work.txt` showed `active · frm-agent · test session`.
  - Second-session `claim`: refused with the existing claimant, exit 5.
  - `status ... done --note "fixed in abc123"`: 0.84 s; `/work.txt?status=done` and the thread page both showed `done`.
  - Second `flush`: "Nothing queued."
  - No automated test for the Python CLI; covered by the recorded manual run.
- Notes:
  - **Open question for the maintainer:** `status:` lines only count from approved members (Stage 1), so each new agent signing key needs approval once before its claims count. The CLI says so when it happens.
  - In this sandbox, GPG failed with frm.py's default state path because the agent socket path was too long; `V3_WORK_FRM_STATE_DIR` with a short path fixed it. Environment-specific, not changed in frm.py.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 4 - Agent rule and documentation

**Original estimate: 0.06**
**Retrospective stage-work assessment: 0.06**

- Changes:
  - `AGENTS.md`: new "Work items" section requiring search and claim before non-trivial work, no parallel work on an `active` item, and a `Work: <thread-id>` commit trailer.
  - New `docs/reference/work_tracking.md`: status rules, claim flow, offline queue, identity approval, and how `todo.txt`, the production feedback register and Railspike plans relate.
  - `docs/reference/v3_cli.md`: "Track work items" section.
- Verification:
  - `php tests/run.php LlmsTxtRendererTest MarkdownListStructureTest MarkdownPipeTableTest MarkdownTaskListTest PlatformDocsCatalogTest PlatformDocsPageTest PlatformDocsStaticTest WriteApiSmokeTest`: 271/271 passed.
  - `git diff --check`: clean. Local links resolve.
  - "A fresh session follows the rule unprompted" is a Stage 8 pilot check.
- Notes:
  - Found an existing source of current status: the [production feedback register](../production_feedback_register.md) owns migrated `PF-…` requests. The reference doc says it keeps that role until a follow-up reconciliation cycle; no change to the register here.
  - Post-back and sweep wording is deferred to Stages 6–7 so the doc does not describe unbuilt behavior.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 5 - Markdown export

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes:
  - `WorkListText::markdown(items, origin)`: `# Work items` heading, a visible "generated; do not edit" line, then `- [status] [title](origin/threads/id) · by · note` per item in thread-ID order; Markdown-significant characters escaped.
  - `GET /work.md` (`Cache-Control: no-store`), origin from the request host (validated), added to `isApplicationRoute`.
  - `./v3 work export [--no-push]`: fetches `/work.md`, builds a commit on `work-export` with Git plumbing (no checkout or worktree changes), skips when unchanged, pushes `commit:refs/heads/work-export`. Falls back to a "v3 work export" Git identity when none is configured.
  - Reference and CLI docs updated.
- Verification:
  - `php tests/run.php WorkItemsTest`: 6/6 passed (adds Markdown escaping, one line per item, empty list).
  - Local server: first `export --no-push` created the branch; second reported "already up to date"; after `./v3 work status work-pilot-1 active`, a third export produced a one-line diff in `work.md`. Test branch deleted afterwards.
  - Pushing to GitHub: not exercised yet; Stage 8.
- Notes:
  - The plan's "export from a cloud workspace" check is the Stage 8 production run.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 6 - Inbox sweep

**Original estimate: 0.15**
**Revised estimate: 0.20** (2026-11-04, new understanding: frm.py could not tag threads)
**Retrospective stage-work assessment: 0.20**

- Changes:
  - `./v3 work sweep [--since REV] [--apply]`: lists by default, creates with `--apply`. Inboxes:
    - lines added to `todo.txt` since the commit that added `docs/reference/work_tracking.md` (or `--since`), working tree included; grouping by indentation, skipping `---` headings and `x`-marked done lines with their details;
    - `#request` threads from new `GET /work.txt?kind=requests`.
  - Created items are `#work` threads whose root starts with `Source: todo.txt#<sha256-12>` or `Source: thread:<id>`. `WorkItems` exposes each item's source; `/work.txt` gains a trailing `source` column. The sweep skips known sources, so no local imported-record file exists and `todo.txt` is never edited.
  - `public/frm.py`: new `--tags` option for `thread`/`reply` (default `general`, validated as lowercase words, `identity` refused), sent as `Board-Tags` on the single-request API and `board_tags` on the prepared API; `FRM_VERSION` 2026-11-04. Needed because frm.py hard-coded `general`.
  - Docs: sweep section in `work_tracking.md`, CLI reference, `--tags` in `agent_api.md`.
- Verification:
  - `php tests/run.php WorkItemsTest`: 6/6 passed (source column; `?kind=requests`).
  - Grouping exercised on sample additions: nested details kept, heading/underline skipped, `x\t` done line and its `x\t\t` detail skipped, sibling open line under a section kept.
  - Local server: `frm.py thread --tags work` created a `#work` thread; `--tags "Bad Tag"` rejected client-side. Sweep dry run listed one `todo.txt` item and one `#request` thread; `--apply` created both; rerun reported "No new inbox entries."
  - `php tests/run.php FrmClientTest`: 1/9 passed both before and after the change (identical results); all 8 failures are GPG agent start failures in this sandbox, a baseline environment problem.
- Notes:
  - Editing a `todo.txt` line produces a new item (new hash); the old item remains for manual `dropped`.
  - Pre-existing `todo.txt` lines are deliberately out of scope until the reconciliation cycle.
- Estimate details: revised before dependent work once the frm.py tag limitation surfaced; scope unchanged.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 7 - Post-back

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes:
  - `./v3 work postback [--range A..B] [--dry-run]`: reads `Work: <thread-id>` trailers from `git log`, oldest first, and posts `status: done - landed in <sha12>`. Skips items already `done`/`dropped`, items whose current status was set after the commit (so reopened items stay open), and IDs that are not work items. Site unreachable: exit 3 and leave it to the next sweep.
  - `./v3 work install-hook`: writes a marked `post-merge` hook that runs postback for `ORIG_HEAD..HEAD` in the background with the install-time site, logging to `state/work/postback.log`; refuses to overwrite a hook it did not write and prints the line to add instead.
  - `./v3 work sweep` runs a batch postback over commits since work tracking was added (listing in dry runs, posting with `--apply`).
  - `/work.txt` gains a trailing `set_at` column used for the reopened-item check.
  - Docs: post-back section in `work_tracking.md`, CLI reference, and a note in the [application update](../../concepts/application_update.md) concept.
- Verification:
  - Simulated deploy: local clone with the hook installed, upstream commit with `Work: work-pilot-1`, `git pull --ff-only` exited 0 and the background hook marked the item `done (landed in 1a9db4817451)` on the local server.
  - Rerun over the same range: no post. After reopening the item, postback and its dry run both left it `active` with the reason printed.
  - `php tests/run.php WorkItemsTest`: 6/6; `php tests/run.php AppUpdateTest DeployPageTest`: 25/25. The deploy routine fast-forwards with `git merge --ff-only` and does not disable hooks.
- Notes:
  - Plan said the server would post; implemented as a hook on any checkout instead, which covers both SSH pulls and site deploys without new server code. The server checkout's frm.py identity needs approval once.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 8 - Production pilot (in progress)

**Original estimate: 0.10**
**Retrospective stage-work assessment: pending**

- Changes so far:
  - New concept entry [work item status](../../concepts/work_item_status.md).
- Verification so far:
  - Full suite on the branch: `php tests/run.php` 1919 run, 1904 passed, 15 failed in 880 s. All 15 also fail on baseline `561f7dd` in this sandbox: 8 `FrmClientTest` (GPG agent cannot start), `ApprovalSeedCommandTest::testUnreadableArtifactDeletionCannotReportSuccess`, 3 `LocalAppSmokeTest`, `ReactionRecordFormatParityTest`, `ReadModelBuilderTimingTest`, `TerminalOperatorUiCommandTest`. No new failures.
- Blocked on the maintainer:
  - Merge and deploy to zenmemes.com; approve the agent and server frm.py identities; run `./v3 work install-hook` on the server checkout.
  - Pilot: ten `#work` items including the issue fixed twice on 2026-11-03; two-session claim test; outage replay with `flush`; first `export` push; a fresh session following the `AGENTS.md` rule unprompted.
  - Phone and desktop look of the status line.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
