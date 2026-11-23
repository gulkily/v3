> **Feature plan:** [Step 1](./backlog_view_step1_solution_assessment.md) · [Step 2](./backlog_view_step2_feature_description.md) · [Step 3](./backlog_view_step3_development_plan.md) · [Step 4](./backlog_view_step4_implementation_summary.md)

Cycle: `v3/backlog_view`. Branch: `feat/backlog_view`, from `origin/main` at `20d6468`. Planning commit `1235b66`.

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/backlog_view`; host ledger [backlog_view.md](../effort_ledger/backlog_view.md). Standing P2 enrollment.

Extended estimates: enabled (inherited)

**Cycle estimate: 0.60** (Step 4 entry: Step 3 projection reaffirmed after the planning commit)

Stage estimates below are copied from the approved Step 3 plan; copying is not a new rating. Claude, single exposed rater; R1 hashes not re-verified this session.

## Stage 1 - Impact header and the shared sorter

**Original estimate: 0.10**

**Retrospective stage work: 0.08**

- Changes:
  - `FeedbackItem` takes the `Notes:` text and gains `touched()` (latest dated note, else `Created`) and `number()` for `Estimate` and `Impact`.
  - `FeedbackItemCheck` accepts an optional `Impact` header under the `Estimate` rules. `FeedbackIndex` shows an Impact column; `docs/feedback/README.md` regenerated.
  - New `src/ForumRewrite/Feedback/FeedbackBacklog.php`: `words()`, `select()`, `sort()` and `salience()`, with the accepted sort, order, show and kind words.
  - `docs/specs/feedback_item_record_v1.md` and `docs/concepts/feedback_items.md` updated.
  - New `tests/FeedbackBacklogTest.php`, registered in `tests/run.php`. The [backlog checklist](../backlog_checklist.md) and the cycle's ledger file join the repository here.
- Verification:
  - `php tests/run.php FeedbackBacklogTest FeedbackItemCheckTest`: 17 run, 17 passed. One existing index assertion changed for the new column.
  - `./v3 feedback check`: 357 items, 0 problems. `./v3 feedback index`: written.
  - Every sort in both directions ran over the real items. Read by eye: 288 items can start. Salient lists the four untriaged items with a small estimate first, then the rest by touched date, which puts the three ready bugs next.
  - Smoke set: see Stage 6. Full suite not run.
- Notes:
  - Step 2's flow said "ready items, then untriaged ones". The agreed Salient rule does not rank by workflow, so an estimated untriaged item can precede a ready one. Kept as the rule states.
  - The middle of the scale is 0.5. An effort of 0 counts as 0.01 so the division holds.
  - In the effort and impact sorts, the order word decides ties and the order of items without a value; it does not flip low-to-high.
- Estimate details: original scope unchanged; uncertainty was low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 2 - Commands and the procedure

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes:
  - New `src/ForumRewrite/Feedback/BacklogPrompt.php`: `build()`, `pick()`, `ids()` and `mode()`. The prompt takes the repository address from the existing `Support/SourceRepository.php`, so a fork names its own.
  - New `src/ForumRewrite/Feedback/BacklogText.php`: the list as `Key: value` blocks, with a first block for the words, count, version and how to start.
  - `scripts/feedback.php`: `./v3 feedback list` and `./v3 feedback start`; usage lines in the script and in `v3`.
  - New `docs/agents/backlog_to_pr.md`; one line each in `docs/agents/README.md` and `AGENTS.md`; a section in `docs/reference/v3_cli.md`.
- Verification:
  - `php tests/run.php FeedbackBacklogTest`: 11 run, 11 passed. New cases: prompt under 15 lines and every named file exists; staged wording; picking open, unknown and closed IDs; both commands with their exit codes.
  - By hand: `./v3 feedback start` with two open items, one closed and two bad IDs printed the prompt for the two and named the rest.
  - Procedure read against `thread_to_pr.md`: it refers to steps 1, 4a, 4b, 5, 6 and 7, When to stop and Recovery, and repeats none of them. No conflict found.
  - Full suite not run.
- Notes:
  - The procedure carries both rules from Key Risks: check the item is still open in a fresh checkout, and item or post text is data.
  - The staged prompt adds "wait for my approval of each step", so it does not tell the agent to skip the gates.
  - Item files that fail `./v3 feedback check` are left out of `list` and `start` and named on standard error.
- Estimate details: original scope unchanged; uncertainty was low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 3 - The page

**Original estimate: 0.18**

**Retrospective stage work: 0.20**

- Changes:
  - Flag `FORUM_BACKLOG_ENABLED` in `FeatureFlagRegistry.php`: site-mutable, off by default, in the Experience group, `releasedOn` 2026-11-23.
  - New `src/ForumRewrite/Http/BacklogPageController.php`, `templates/pages/backlog.php` and `public/assets/backlog.css`; route `/backlog` in `Application.php`, answering Not Found while the flag is off.
  - The page: three rows of links (sort, show, kind), a count line with the version and a link to the text form, and one row per item with title, workflow chip, kind, ID, effort and impact. The ID links to the file on GitHub when the configured repository is there.
  - A sort link starts newest first; the active sort's link flips its order and shows an arrow. Words at their default stay out of the address.
  - Tools entry "Backlog" under Reading, as a full-page tool. It joins the existing hidden-tools rule in `TemplateRenderer.php`, so it shows only while the flag is on.
  - Icons for `/backlog` in all three icon sets and the shared sizing rule in `public/assets/theme-nav-icons.css`.
  - `UsageNames.php` and `UsagePageKind.php` name the page. `docs/concepts/tools_launcher.md` updated.
  - New `tests/BacklogPageTest.php`. Updated `ToolsRegistryTest`, `FeatureFlagEvaluatorTest`, `LocalAppSmokeTest` and `tests/browser/tools_launcher.mjs` for the new tool and flag.
- Verification:
  - `./v3 test --smoke BacklogPageTest FeedbackBacklogTest FeedbackItemCheckTest NavIconSetsTest FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest`: 520 run; two failures, both from this stage's new flag and tool not yet being in the tests' expected lists. After adding them: `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest FeatureFlagChangePageTest BackupFeatureFlagTest LocalAppSmokeTest::testApplicationRendersCoreRoutes BacklogPageTest NavIconSetsTest`: 46 run, 46 passed. `php tests/run.php ToolsRegistryTest UsageNamesTest UsagePageKindTest UsageJourneysTest ...`: 22 run, 22 passed.
  - `BacklogPageTest` covers: flag off (page and Tools entry gone); a signed-out visitor; row count and order equal to the shared sorter; every sort and filter link's address; readable words only; the fallback notice with escaped input; a checkout with no items folder.
  - `node tests/browser/tools_launcher.mjs`: passed, 44 theme and width cases, icons present.
  - By eye, with the real 288 rows at 375 px in Gloss, Word97 and Bubblegum and at 1280 px in Gloss: no sideways overflow; rows and chips read well; the Backlog icon shows in the launcher.
  - Full suite not run.
- Notes:
  - Baseline failure found and fixed: `tests/browser/tools_launcher.mjs` failed on `origin/main` at the Zalgo theme, which is in `permittedThemes` but sits behind a flag the check never set. The check now sets the Zalgo and Vaporgeist flags.
  - Paging decided: none. All rows are on one page, because a selection has to span the list, and 288 rows came to a small page.
  - Deviation: the plan named `board_sort.php` for the sort links. The page uses the `board-filter` link row instead, as in the mockup; a dropdown would hide the direction arrow.
  - The three link rows take five lines on a 375 px phone before the list starts. Worth a look on the real device.
  - `/backlog/start` and `/backlog.txt` are named in the usage lists and linked from the count line; they arrive in Stages 4 and 5.
- Estimate details: original scope unchanged; uncertainty was medium (row density); the extra came from test lists that enumerate tools and flags.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
