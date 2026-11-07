> **Feature plan:** [Step 1](./theme_directive_step1_solution_assessment.md) · [Step 2](./theme_directive_step2_feature_description.md) · [Step 3](./theme_directive_step3_development_plan.md) · [Step 4](./theme_directive_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/theme_directive`; [host ledger](../effort_estimation_trial.md#cycle-v3theme_directive). Standing P2 enrollment. Builds on `v3/engagify_theme`.

Extended estimates: enabled (inherited)

Cycle estimate: 0.75

Branch `feat/theme_directive`, from `feat/engagify_theme` (unmerged; this cycle depends on its page themes). Planning commit `bcf05898`. Earlier Step 1–3 drafts stay on `plan/theme_directive`.

## Step 4 entry

2026-11-07T15:20:40Z, Step 3 explicitly approved ("approved step 3"). Step 3 projection **0.75** reaffirmed at entry; scope v2 unchanged. Stage identities and original stage-work estimates copied from the Step 3 plan, not re-rated: Stage 1 0.15, Stage 2 0.07, Stage 3 0.15, Stage 4 0.10, Stage 5 0.12, Stage 6 0.06. R1/P2, single exposed Claude rater.

## Stage 1 - Visible `theme:` line sets the thread's theme

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes:
  - New `Support/ThemeDirective`: reads a whole unquoted `theme: <name>` line; `none` clears.
  - New `View/ThreadTheme::forThread()`: latest valid line from an unretracted, verified post by the thread's verified author or a current operator; names the site does not permit are skipped, so the earlier setting stays.
  - `ThreadAndPostPageController` passes `threadTheme`; `TemplateRenderer` uses it ahead of the site profile's page theme list, with the existing permitted-theme check.
  - New `tests/ThreadThemeTest.php`, registered in `tests/run.php`.
- Verification:
  - `php tests/run.php ThreadThemeTest`: 3 passed (directive parsing; author, operator, other user, `none`, unknown name, retraction, unverified; precedence over the site list and a foreign site's theme).
  - `php tests/run.php --smoke ThreadThemeTest EventStatusTest SiteProfileRegistryTest --jobs=4`: 451 run, 450 passed. The one failure, `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, also fails on the base branch with this stage stashed; recorded by the runner as long-standing.
  - Full suite not run, per project practice.
- Notes:
  - Authority is judged on read, like `event:`, so a theme set by someone who stops being an operator stops applying.
  - The visible line stays in the post as written.
- Estimate details: original scope as planned; uncertainty low; no revisions. Retrospective: smaller than planned because the renderer already had the page-theme path; measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Baked thread pages follow theme changes

**Original estimate: 0.07**
**Retrospective: 0.04**

- Changes:
  - No application change was needed. Every reply path already drops its thread's baked page (`invalidateReply()` in `LocalWriteService` and the signed-post path), and retraction drops all generated pages (`invalidateRetractedPostArtifacts()`).
  - `ThreadThemeTest::testBakedThreadPageFollowsAThemeReply`: bakes a thread, posts a theme reply, checks the baked file is gone, rebuilds and reads `data-page-theme`.
- Verification:
  - `php tests/run.php ThreadThemeTest`: 4 passed.
  - Retraction invalidation confirmed by reading the code, not by a new test; signing a retraction in a test needs the prepare/finalize flow, which existing retraction tests already cover.
- Notes:
  - As with `event:`, a baked page does not change when someone stops being an operator; it updates on the thread's next write or rebuild.
- Estimate details: original scope as planned; uncertainty low; no revisions. Retrospective: tracing only, no code change; measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 3 - Hidden `Theme:` header in records

**Original estimate: 0.15**
**Retrospective: 0.13**

- Changes:
  - `docs/specs/canonical_post_record_v1.md`: optional `Theme` on roots and replies; in the client-signed subset it follows `Body-Format`.
  - `PostRecord` gains `?string $theme`; `PostRecordParser` reads and validates it (`isThemeValue()`: lowercase name or `none`).
  - `SignedPostBundleValidator` accepts it only after `Body-Format`, once, lowercase.
  - `LocalWriteService`: `normalizeTheme()` reads the `theme` input on create and prepare paths for threads and replies; the record builders append `Theme:` after `Body-Format`. Site permission and authority stay on read.
  - Tests: `CanonicalRecordParsersTest` (parse and builders), `SignedPostBundleValidatorTest` (order, repeats, case), `SingleRequestPostingTest::testSignedReplyCarriesHiddenThemeHeader` (signed round trip), `ThreadThemeTest::testThemeInputWritesTheHiddenHeader`.
- Verification:
  - `php tests/run.php CanonicalRecordParsersTest SignedPostBundleValidatorTest`: 61 passed.
  - `php tests/run.php SingleRequestPostingTest`: 8 passed. `ThreadThemeTest`: 5 passed.
  - Smoke plus changed tests (`--jobs=4`): 451 run, 449 passed. `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged` fails on the base branch too. `LocalAppSmokeTest::testApplicationRendersTextApisAndRss` failed once under parallel jobs and passed twice alone; not touched by this stage.
- Notes:
  - The header does not set a theme yet; Stage 4 reads it.
  - Clients that never send `Theme` produce the same bytes as before.
- Estimate details: original scope as planned; uncertainty medium, resolved: `Body-Format` gave an exact pattern. No revisions. Measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 4 - Read model keeps the hidden header; both forms share one rule

**Original estimate: 0.10**
**Retrospective: 0.11**

- Changes:
  - `posts.theme` (TEXT NULL) in `ReadModelSchema`; `SCHEMA_VERSION` 50 → 51.
  - `ReadModelBuilder` and both `IncrementalReadModelUpdater` insert paths fill it; retraction placeholders clear it.
  - `ThreadTheme` reads `posts.theme` and the body line from the same rows; latest setting wins across both forms, and a header beats a line in the same post.
  - `docs/specs/read_model_schema_v1.md`: version 51 note; `body_format` and `theme` rows in the `posts` table.
  - Tests: `ThreadThemeTest::testHiddenAndVisibleSettingsShareOneLatestWins` (mixed forms, `none`, header over line, other user, full rebuild) and `testStartupRebuildsASchema50ReadModel`.
- Verification:
  - `php tests/run.php ThreadThemeTest`: 7 passed.
  - Smoke plus `ThreadThemeTest EventStatusTest SingleRequestPostingTest PostFormatRecordTest WriteApiSmokeTest` (`--jobs=4`): 669 run, 668 passed; the one failure is the base branch's `BoardListTest` failure.
- Notes:
  - Posts written in the same second can swap order after a full rebuild, as they can for `event:` and `title:`; the rebuild test checks stored values rather than which one is latest.
  - `PublicOfflineSnapshotBuilder` keeps its own posts table and is unchanged; offline snapshots do not show page themes.
- Estimate details: original scope as planned plus the prior-version startup test the schema contract requires; uncertainty low. No revisions. Measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 5 - Theme choice in the browser composer

**Original estimate: 0.12**
**Retrospective: 0.12**

- Changes:
  - `templates/partials/composer.php`: a theme select in the ··· menu, after Formatting help. New threads: "Theme" with "Site default" plus the site's permitted themes. Replies: "Thread theme" with "Unchanged", the themes and "Clear thread theme" (`none`), and a tooltip saying it counts only from the thread's author or an operator.
  - `ThreadTheme::choices()` supplies names and labels; `compose.css` lays the select out on one row.
  - `browser_signing.js`: the signed submit sends `theme` with `body_format`; Outbox sends carry it; Discard draft resets the choice.
  - `outbox_compose.js`: Outbox drafts keep the chosen theme.
  - `ThreadThemeTest::testComposersOfferTheThemeChoiceAndTheFormPathWritesIt` covers the markup and the unsigned form path.
- Verification:
  - Smoke plus `ThreadThemeTest`, the Markdown composer test and `BrowserSigningNormalizationTest` (`--jobs=4`): 530 run, 529 passed; the one failure is the base branch's `BoardListTest` failure.
  - Browser check with headless Chrome against a local server on a fixture copy, signed in as a fresh browser identity:
    - New thread with Word 97 picked in the menu: a fresh 380px reader sees `data-page-theme="word97"`; the post text has no theme line.
    - Reply with Midnight picked: the reader sees Midnight after reload.
    - Reply with "Clear thread theme": a fresh reader sees no page theme.
    - Screenshots of the menu at 1280px (new thread) and 380px (reply, Midnight) looked right; the select fits on one row.
- Notes:
  - Thread pages are baked, so the reply menu shows the choice to everyone; the tooltip says who it counts for. This was the planned fallback for the baked composer risk.
  - A thread posted anonymously has no verified author, so only an operator can theme it.
  - After posting a theme reply, the poster sees the new theme on the next page load, not instantly.
- Estimate details: original scope as planned; uncertainty medium, resolved: the browser sends fields to `prepare_*` and signs the server's record, so no client-side record building changed. Measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 6 - Docs and close-out

**Original estimate: 0.06**
**Retrospective: 0.06**

- Changes:
  - `docs/concepts/page_theme.md`: thread-set themes in the scenario; ThreadTheme and the directive in sources.
  - `docs/runbooks/theme_development_guide.md`: page themes section covers thread-set themes and why they need no manual rebuild.
  - `docs/reference/agent_api.md` and `LlmsTxtRenderer` (served `llms.txt`): the `theme:` line and the `theme` input beside `event:`.
- Verification:
  - Local links in changed docs and plan files resolve (scripted check).
  - Smoke plus `ThreadThemeTest LlmsTxtRendererTest PlatformDocsCatalogTest` (`--jobs=4`): 457 run, 456 passed; the one failure is the base branch's `BoardListTest` failure.
  - Final diff review of the cycle (22 files in src, templates, public and tests); `git diff --check` clean.
- Notes: none.
- Estimate details: original scope as planned; uncertainty low. Measured labor unknown.

## Closeout

- Completion Contract:
  - Normal entry (both forms, new thread and reply, browser and signed API): passed. Browser check in Stage 5; signed API round trip in Stage 3.
  - End-to-end outcome (theme before first paint, hidden form not in the text, other pages unchanged): passed.
  - Recovery (retraction, `theme: none`, Exit, old records unchanged): passed; Exit is the existing page-theme mechanism, unchanged and not re-checked in a browser this cycle.
  - Deployment (read-model rebuild for schema 51, baked pages): startup rebuild from schema 50 tested; production deploy pending, not run from here.
  - Release condition: smoke and changed tests pass apart from the base branch's `BoardListTest` failure. Full suite not run, per project practice.
- Base-branch failure reproduced: `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged` fails on `feat/engagify_theme` without this cycle's changes.
- Merge order: this branch is based on `feat/engagify_theme`; merge that first, or merge this branch, which contains it.

**Cycle retrospective: 0.58** (stage retrospectives 0.12, 0.04, 0.13, 0.11, 0.12, 0.06; projection was 0.75)

Retrospective scope assessment, 2026-11-07T15:32:51Z: delivered scope v2 as approved, with no scope changes during Step 4. Smaller than projected mainly because Stage 2 needed no code and Stage 5 needed no client-side record building. Rater: Claude, single exposed rater, also the planner. Maintainer comfortable-fit judgment: pending. Measured labor: unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
