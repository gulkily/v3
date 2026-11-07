> **Feature plan:** [Step 1](./gloss_theme_step1_solution_assessment.md) · [Step 2](./gloss_theme_step2_feature_description.md) · [Step 3](./gloss_theme_step3_development_plan.md) · [Step 4](./gloss_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/gloss_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3gloss_theme). Standing P2 enrollment. Related to `v3/engagify_theme`; not a child cycle.

Extended estimates: enabled

Cycle estimate: 0.95

## Step 4 entry

2026-11-07T13:18:41Z. Step 3 approved by the maintainer ("approved step 3"); the maintainer then asked to start development in this session. Branch `feat/engagify_theme` reused, as approved in Step 2; planning commit `b1551c6a6`. Whole-cycle projection **0.95** reaffirmed from Step 3, same scope v6, R1/P2, single exposed Claude rater. Stage-work originals copied from Step 3, not re-rated: 0.08, 0.10, 0.10, 0.06, 0.08, 0.18, 0.08, 0.10. They do not add up.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 1 - Icon sample

**Original estimate: 0.08**
**Retrospective: 0.07**

- Changes:
  - `public/assets/theme-nav-icons.css`:
    - The ten sizing rules (size, spacing, the phone tab bar layout, hiding the built-in New post icon) now list `gloss` and `engagify` beside the three pixel-art themes. Glyph rules still list only their own set's themes, so the two sets share one copy of the sizing.
    - New "Smooth-line set" block at the end, for `gloss` and `engagify`: 16px, 1.5px violet (`#6d28d9`) strokes, round caps and joins, lavender (`#ede9fe`) fill. Three glyphs: Board (a card with text lines), Backup (an archive box, on both the launcher button and the Tools bar link) and the operator group heading (a gear).
    - Header comment describes both sets.
  - `docs/feedback/items/PF-20261104-F13.txt`: dated note.
- Verification:
  - Passed: headless Chrome against `./v3 start`, Engagify and Word 97, 1280px and 380px, board and `/tools/`. The three glyphs render in Engagify; Word 97 keeps its pixel-art glyphs; no sideways scroll; no page errors. Zoomed screenshots at 3x inspected.
  - Passed: maintainer approved the style ("They look fine to me for now").
  - Passed: `tests/browser/tools_launcher.mjs`, 30 theme and width cases, pixel-art icons in all three themes.
  - Baseline failures: `./v3 test` (smoke), 436 run, 431 passed, 5 failed. The same 5 fail with this stage's CSS change stashed: `AgentPostingApiTest` (2), `SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection` and `SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes`, which log a PCRE Unicode property warning from `UnicodeTextPolicy.php`, and `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, the known pagination failure. Unrelated to themes.
  - `./v3 feedback check`: one problem, `PF-20261030-B08` section order, also present before this stage.
  - Full suite not run.
- Notes:
  - Until Stages 2–4 draw the rest, Engagify shows an empty 16px space before every other navigation label, because the sizing rules now apply to it. This is expected on the branch and closes at Stage 4.
  - The account menu icon keeps the site's own icon in Engagify; Stage 2 covers it with the main navigation.
  - Size settled at 16px, the same as the pixel-art set, so the sizing rules stay shared.
- Estimate details: original scope as Step 3; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Enhancement requests

- **Request:** add colour to the new icons later ("Later we may want to add color to them"). **Disposition and reason:** separate work; the approved style is single-colour violet, and the request is explicitly for later. **Approval status:** not started. **Outcome:** follow-up after this cycle; not in Stages 2–8.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Main navigation icons

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `public/assets/theme-nav-icons.css`, smooth-line block: 24 rules keyed to the same links as the pixel-art set. About, Users, Tools, Account (key), Invites, New post and compose, Lobby, profiles, Messages, Tags, Calendar, Latest, Top, Leetness, Random, Add, Search, the five board filters (All, Liked, Newest, Oldest, Top), Recent and Gallery. The account menu shows a person in a circle; on phones, where it is More, a three-line list.
  - `public/assets/site.css`: the phone More tab's fallback three-bar icon and its renamed-tab arrow excluded only the three pixel-art themes. Both rules now also exclude `gloss` and `engagify`, so those themes show their own More glyph, as the pixel-art themes do. Comment updated.
  - `docs/feedback/items/PF-20261104-F13.txt`: dated note.
- Verification:
  - Passed: contact sheet of all 26 glyphs at 16px and 48px inspected.
  - Passed: headless Chrome against `./v3 start`, Engagify and Word 97, 1280px and 380px, board page and the phone More panel. Every visible main navigation link and New post button has a glyph in Engagify. The two exceptions, the Newest sort control and the page-2 link, have none in Word 97 either, by design. The account menu and More icons render; the renamed More tab on `/about/` matches Word 97's behavior. No page errors.
  - Not rendered, checked only by selector parity with the pixel-art set: links that need a session or a flag (Invites, Messages, profiles, Lobby, Latest, Top, Leetness, Random, Add, Gallery). Stage 8's sweep covers them signed in.
  - Passed: `tests/browser/tools_launcher.mjs`.
  - Smoke: 436 run, 431 passed, 5 failed; the same 5 baseline failures as Stage 1.
  - Full suite not run.
- Notes:
  - Found while checking: `site.css` keeps its own list of icon themes for the phone More tab. Without the change, Engagify's More tab showed a 2px stripe instead of a glyph. Stage 4 checks `tools.css` for the same pattern on Bookmarklets buttons.
  - Recent and Gallery moved here from Stage 3, because they are main navigation links; their rule also covers the launcher button.
- Estimate details: original scope as Step 3, plus Recent and Gallery from Stage 3 and the `site.css` fix. No revision; retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 3 - Tools icons

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - `public/assets/theme-nav-icons.css`, smooth-line block: 16 rules, each serving a Tools launcher button and its Tools bar link. Activity (pulse), Forte (three panes), Bookmarklets (bookmark), SQLite (database), LLM Exchanges (bubble with dots), System status at `/tools/codebase/` (code brackets), Feature Flags (flag), Operations (sliders), Deploy (arrow out of a tray), Offline Reading (cloud, struck through), Platform Docs (open book), Outbox (paper plane), Visitor Statistics (line chart), User Access (shield with a check), Fastmod (gauge) and Account (key, as in the main navigation). Backup was drawn in Stage 1.
  - `docs/feedback/items/PF-20261104-F13.txt`: dated note.
- Verification:
  - Passed: contact sheet of the 16 glyphs at 16px and 48px inspected; the gauge was redrawn once to sit centered.
  - Passed: headless Chrome, Engagify and Word 97, 1280px and 380px, on `/tools/`, `/tools/backup/` and `/tools/bookmarklets/`. Every launcher button and Tools bar link has a glyph in Engagify. The only gaps are Stage 4's: three group headings and the Bookmarklets buttons. Word 97 has no gaps. No sideways scroll, no page errors. Full-page screenshot of `/tools/backup/` inspected.
  - Passed: `tests/browser/tools_launcher.mjs`.
  - Smoke: 436 run, 431 passed, 5 failed; the same 5 baseline failures.
  - Full suite not run.
- Notes:
  - Recent and Gallery were drawn in Stage 2, so this stage has 16 glyphs instead of about 19.
- Estimate details: original scope as Step 3 minus Recent and Gallery. No revision; retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 4 - Headings, Bookmarklets and coverage

**Original estimate: 0.06**
**Retrospective: 0.07**

- Changes:
  - `public/assets/theme-nav-icons.css`, smooth-line block: group headings Your device (phone), Reading (glasses) and Community (the Users glyph); Bookmarklets buttons +URL (link), Clip (paperclip), Rip (highlighted line) and Tweet (repost arrows, no brand mark). The Messages rule also names the exact `/messages` link, as the pixel-art set does.
  - `public/assets/tools.css`: the Bookmarklets drag-handle glyph skipped only Word 97 and Chicago; it now also skips `gloss` and `engagify`, so their own glyphs show.
  - New `tests/NavIconSetsTest.php`, registered in `tests/run.php`: the smooth-line set must name exactly the destinations the pixel-art set names.
  - `tests/browser/tools_launcher.mjs`: Engagify joins the icon themes, so every tool button, Tools bar link and group heading must have a glyph in it.
  - `docs/feedback/items/PF-20261104-F13.txt`: dated note.
- Verification:
  - Passed: contact sheet of the 7 glyphs inspected.
  - Passed: `./v3 test --smoke NavIconSetsTest`, 437 run, 432 passed, 5 failed (the same baseline failures). The new test failed on its first run and found the missing exact `/messages` selector, fixed above. A deliberate rename of one smooth-line selector made it fail with both messages, then was reverted.
  - Passed: `tests/browser/tools_launcher.mjs`, 30 theme and width cases, icons checked in Word 97, Chicago, Midnight and Engagify.
  - Passed: headless Chrome, Engagify, 1280px and 380px, `/tools/`, `/tools/backup/` and `/tools/bookmarklets/`: no element without a glyph, no sideways scroll, no page errors. Full-page Bookmarklets screenshot inspected.
  - Full suite not run.
- Notes:
  - The empty icon slots noted in Stage 1 are closed: every destination has a smooth-line glyph.
  - Observed, not changed: the `tools.css` drag-handle rule also covers Midnight, so Midnight's Bookmarklets buttons likely show the drag handle instead of their pixel-art glyphs. This predates this cycle and is outside its scope; left for the maintainer.
  - `NavIconSetsTest` is not in `tests/smoke.txt`; it runs with `./v3 test --smoke NavIconSetsTest` and in the full suite.
- Estimate details: original scope as Step 3, plus the `tools.css` fix and a PHP test in place of a one-off script. No revision; retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 5 - Shared stylesheet field

**Original estimate: 0.08**
**Retrospective: 0.06**

- Changes:
  - `src/ForumRewrite/View/ThemeRegistry.php`: an entry may set `'stylesheet' => '<other>'`; `stylesheetPaths()` then returns `/assets/theme-<other>.css` for it. No theme sets it yet. The server, both layouts and the pre-paint script already read paths from this map, so they need no change.
  - `public/assets/theme_toggle.js`: stylesheet load states are keyed by path instead of theme name, so two names that share a file share one request and one ready state.
  - `tests/ThemeRegistryTest.php`: each theme's path is its own stylesheet or a declared owner's; the owner must be an explicit theme that does not itself borrow; the file must exist.
  - New `tests/browser/theme_shared_stylesheet.cjs`, run by `ThemeToggleScriptTest::testThemesSharingAStylesheetLoadItOnce`: choosing two sharing themes before the file arrives makes one request, and later switches between them apply at once.
- Verification:
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest ProfileThemePresentationTest`, 443 run, 438 passed, 5 failed (the same baseline failures).
  - Passed: the new script test fails against the previous `theme_toggle.js` (two requests for one file) and passes with the change.
  - Passed: headless Chrome against `./v3 start`: menu choices Word 97, Engagify, Light, Engagify each apply; no theme stylesheet requested twice; no page errors.
  - Full suite not run.
- Notes:
  - A shared link keeps the `data-theme-name` of the first theme that loaded it. Nothing reads that attribute for behavior.
- Estimate details: original scope as Step 3; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 6 - Gloss and the move

**Original estimate: 0.18**
**Retrospective: 0.16**

- Changes:
  - New `public/assets/theme-gloss.css`, made from `theme-engagify.css`, which is removed:
    - Shared section for `gloss` and `engagify`: the variable block (Engagify's palette and Inter, with `--gloss-accent`), rounded cards, rounded inputs, pill buttons and violet links. Gloss's page is plain `#f6f5fb`.
    - Gloss swatch: violet with a white highlight.
    - Parody section for `engagify` only: the gradient page wash, `--engagify-accent` (an alias of `--gloss-accent`), `--engagify-accent-ink`, `--engagify-hot`, Engagify's swatch, its bold "NEW" menu row and every nag rule, unchanged.
    - Header names both themes and says which rules go where.
  - `src/ForumRewrite/View/ThemeRegistry.php`: `gloss` ("Gloss", light) after Whitehot; Engagify sets `'stylesheet' => 'gloss'`.
  - `src/ForumRewrite/SiteProfileRegistry.php`: ZenMemes permits `gloss`.
  - `tests/LocalAppSmokeTest.php`: the theme allow-list includes `gloss`; the cookie thread's initial stylesheet and its baked asset are `theme-gloss.css`.
  - `tests/theme_contrast_audit.py`: reads each theme's stylesheet through the registry's `stylesheet` field.
  - `tests/browser/tools_launcher.mjs`: Gloss joins the icon themes.
  - Feedback items F13 and F46: dated notes.
- Verification:
  - Passed: Engagify before/after, time frozen, nags open (at 1s and 7s), 1280px and 380px, on board, thread, Tools, Users and the cookie thread: 20 full-page screenshots. 17 are pixel-identical. The other 3 differ only in the areas that also differed between two identical runs before the move (the phone bar region, 2794–3091px, and a 10px caret).
  - Passed: the cookie thread and the board serve `theme-gloss.<hash>.css` for both `gloss` and `engagify`, and `theme-engagify.<hash>.js` for Engagify only.
  - Passed: Gloss on board, thread, Recent, Calendar, Users, Search, About, Tools, Bookmarklets, compose, Account, Tags and Docs, at 1280px and 380px: Gloss applied, no Engagify element, no sideways scroll, no page errors. Screenshots inspected. Forte's standalone layout uses only the theme's light or dark mode, so Gloss needs nothing there.
  - Passed: switching Gloss, Engagify, Gloss, Engagify, Word 97, Gloss in the browser applies each theme and never requests a theme stylesheet twice. Nags appear in Engagify (6 elements) and none remain 8s after switching to Gloss.
  - Passed: `tests/theme_contrast_audit.py`: Gloss OK, Engagify OK. Other themes' results are unchanged by this stage.
  - Passed: `tests/browser/tools_launcher.mjs`, 32 theme and width cases, icons in Word 97, Chicago, Midnight, Gloss and Engagify.
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest LocalAppSmokeTest NavIconSetsTest ProfileThemePresentationTest SiteProfileRegistryTest Word97ThemeCoverageTest PostActionThemeTest`, 456 run, 450 passed, 6 failed. The sixth was `ThemeRegistryTest::testEveryExplicitThemeHasAnIsolatedStylesheet`, which requires a `Theme: <name>` header for each theme. Fixed by adding Engagify's header line to `theme-gloss.css`; the smoke rerun is back to the 5 baseline failures.
  - Full suite not run.
- Notes:
  - Navigation links and thread lists keep the site's square edges in both themes, as Engagify had. PF-20261030-F46 asks for rounder corners; rounding those is a possible follow-up, not in this cycle.
  - On a fresh load with Gloss saved, the server sends the default stylesheet and the pre-paint script switches to Gloss's, as for every saved theme.
- Estimate details: original scope as Step 3, plus the contrast audit change and the header fix. No revision; retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 7 - Exit to Gloss and the cycle skip

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `public/assets/theme_toggle.js`:
    - `forumExitTheme(target)` takes the theme to land on and applies it like a menu choice: saved as the reader's theme, and on a page theme's page the page exit is recorded. A target the site does not offer, or the current theme, falls back to the site default.
    - The stored previous theme (`<key>-previous`) is gone; only the old Exit read it. Existing values in browsers are left unused.
    - `nextTheme` passes over options marked `data-theme-cycle="skip"`.
  - `public/assets/theme-engagify.js`: the Exit pill calls `forumExitTheme("gloss")`, so the Exit target lives in the theme's own script.
  - `src/ForumRewrite/View/ThemeRegistry.php`: new optional `'cycle' => false`; Engagify sets it.
  - `templates/partials/theme_menu.php`: such a theme's menu option carries `data-theme-cycle="skip"`. The attribute is the same for every viewer, so server HTML stays static.
  - New `tests/browser/theme_exit.cjs`, run by `ThemeToggleScriptTest::testExitSavesItsTargetAndCycleSkipsMarkedThemes`: Exit after picking Engagify; Exit on a page theme's page over a saved Word 97, then a revisit; a target the site lacks; twelve cycle presses that never reach Engagify; a cycle press from Engagify itself.
  - `tests/LocalAppSmokeTest.php`: the menu lists Gloss and marks only Engagify to skip the cycle.
- Verification:
  - Passed: `theme_exit.cjs` fails against the previous `theme_toggle.js` and passes with the change.
  - Passed: `./v3 test --smoke ThemeToggleScriptTest LocalAppSmokeTest ThemeRegistryTest ProfileThemePresentationTest`, 444 run, 439 passed, 5 failed (the same baseline failures); `LocalAppSmokeTest::testApplicationRendersCoreRoutes`, `testPageThemeIsMarkedOnItsOwnPageOnly` and `testBakedThreadArtifactCarriesItsPageTheme` run by name and passed.
  - Passed: headless Chrome against `./v3 start`, the cookie thread, a browser with Word 97 saved, 1280px and 380px. The page opens in Engagify with its nags; clicking Exit shows Gloss with no reload and no nags, and saves Gloss; a reload and the board stay on Gloss. No page errors. Phone screenshot inspected.
  - Passed: Engagify picked from the menu, then Exit by keyboard (focus and Enter): Gloss, saved, no nags.
  - Passed: with Midnight current, the cycle button's label names Auto/System as next, skipping Engagify, which follows Midnight in the order.
  - Full suite not run.
- Notes:
  - As accepted in Step 2, a reader who had saved another theme and exits Engagify now has Gloss everywhere; the theme menu restores any theme in one choice.
- Estimate details: original scope as Step 3, plus the registry field and menu attribute for the skip. No revision; retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 8 - Docs, feedback records and sweep

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `docs/runbooks/theme_development_guide.md`: registry fields `stylesheet` and `cycle`; a new section on shared stylesheets and the cycle button, with Gloss and Engagify as the example; `forumExitTheme(target)`; Exit on a page theme saves its target; the smooth-line icon set.
  - `docs/concepts/theme_navigation_icons.md`: two icon sets, five icon themes, the parity test, and the icon-theme lists in `site.css` and `tools.css`.
  - `docs/concepts/page_theme.md` and `docs/concepts/theme_script.md`: Exit lands on Gloss and saves it.
  - `AGENTS.md`, navigation icons rule: both sets and their themes; `NavIconSetsTest` checks they match.
  - `docs/feedback/items/PF-20261104-F13.txt` and `PF-20261030-F46.txt`: `Workflow: blocked` (merge, deploy and production check pending), `Evidence: locally-verified`, dated note.
  - Effort ledger closeout.
- Verification:
  - Passed: `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest LocalAppSmokeTest NavIconSetsTest ProfileThemePresentationTest SiteProfileRegistryTest Word97ThemeCoverageTest PostActionThemeTest InvitationIssuanceTest`, 467 run, 462 passed, 5 failed. The 5 are the baseline failures recorded in Stage 1, which also fail with this cycle's changes stashed.
  - Passed: `tests/browser/tools_launcher.mjs`, 32 theme and width cases, icons in all five icon themes.
  - Passed: `tests/theme_contrast_audit.py`: Gloss OK, Engagify OK.
  - Passed: links in a real page, Gloss, Engagify and Word 97: 21 main navigation hrefs (including signed-in and flagged ones: Invites, Messages, a profile, Lobby, Latest, Top, Leetness, Random, Add, Gallery), the 5 board filters and the New post button were injected into the board's navigation; every one shows a glyph.
  - Passed: keyboard, Gloss board at 1280px and 380px: 25 Tab presses, every focused element shows an outline or focus shadow.
  - Passed (earlier stages, same code): Gloss on 13 page types at both widths (Stage 6); Engagify pixel comparison (Stage 6); Exit on the cookie thread with a saved Word 97 at both widths, by mouse and keyboard, plus reload and board (Stage 7); cycle skip (Stage 7).
  - Passed: `./v3 feedback check`: 1 problem, `PF-20261030-B08` section order, present before this cycle.
  - Passed: `git diff --check`; relative links and anchors in the five touched docs resolve.
  - Not run: the full suite (the maintainer runs it); Firefox, WebKit and a physical phone; a signed-in session rendered by the server (covered by the injected links above, not by real signed-in pages).
- Notes:
  - Final diff review: no second copy of the look (one stylesheet, one sizing rule set for both icon sets); no unrelated files. Out-of-scope observation kept for the maintainer: Midnight's Bookmarklets buttons likely show the `tools.css` drag handle instead of their pixel glyphs (Stage 4).
- Estimate details: original scope as Step 3; no revision. Retrospective is a scope assessment, not measured time.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Closeout

2026-11-07T14:32:27Z. All eight stages are complete on `feat/engagify_theme`: planning `b1551c6a6`, then one commit per stage. Technical completion is local; nothing is merged or deployed.

- Delivered scope v6: a hand-drawn smooth-line icon set for 52 destinations; Gloss, selectable on ZenMemes, sharing `theme-gloss.css` with Engagify, which renders as before; Exit from Engagify lands on Gloss and saves it; the cycle button skips Engagify; registry fields `stylesheet` and `cycle`; guide, concepts and `AGENTS.md` updated.
- Unverified: production after deploy (the cookie thread opens in Engagify, Exit lands on Gloss); Firefox, WebKit and a physical phone; server-rendered signed-in pages. The full suite was not run.
- Corrections during work: the phone More tab's fallback icon in `site.css` and the Bookmarklets drag handle in `tools.css` each kept their own icon-theme lists (Stages 2 and 4); the parity test found a missing exact `/messages` selector (Stage 4); the stylesheet header check needed an Engagify line (Stage 6). All were found by stage checks and fixed in their stages.
- New requests during Step 4: colour for the icons later (recorded above as separate work, not started).

Study closeout, `railspike-effort` / `v3/gloss_theme`, R1/P2, single exposed Claude rater:

- Projection at Step 4 entry 0.95, no revision. Stage originals 0.08/0.10/0.10/0.06/0.08/0.18/0.08/0.10; retrospectives 0.07/0.10/0.08/0.07/0.06/0.16/0.08/0.10. They do not add up.
- Retrospective scope assessment for the whole cycle: **0.90**. This is not measured time.
- Maintainer comfortable-fit judgment and study acceptance: pending.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Follow-up - consistent edges

After closeout the maintainer found Gloss mixing square and rounded corners, and dark borders with pale borders plus shadows. Claude proposed one rule per kind of element; the maintainer approved it ("i approve this"), including the softer button edges. Direct follow-up on this branch, one commit.

- Changes, `public/assets/theme-gloss.css` shared section (so Engagify's page chrome changes too):
  - Tokens `--gloss-edge` (`#e2dff0`), `--gloss-control-edge` (`#c4b5fd`) and `--gloss-shadow`.
  - Containers (thread list, account and sort menus, compose menu): 16px corners, pale edge, shadow; the thread list clips its rows. Inner groups (tag groups, Bookmarklets groups, account cards and groups, Advanced and compose disclosures): 16px, pale edge.
  - Clickable things (navigation links, New post, You/More, Tools buttons, sort control, compose menu toggle, the theme switcher, all buttons, plain chips): pills with the soft violet edge. Active items keep `--active-line`; status chips keep their status colour.
  - Text fields keep 12px and the darker `--line`, for 3:1 against white.
  - Dividers (thread rows, About sections, account rows, calendar cells) and small boxes (code blocks, thumbnails, Bookmarklets feedback): pale edge, 8px where rounded.
  - Phone tab bar: tabs, New post, More and the renamed More tab use 12px corners and the violet active edge.
- Verification:
  - Passed: edge inventory of every bordered or shadowed element on 12 Gloss pages: the only dark edges left are text fields; the only other strong edges are active items, the theme swatch and status chips.
  - Passed: before/after screenshots of the board and Tools at 1280px, the board, Tools and its renamed More tab at 380px, Account and Calendar inspected.
  - Passed: Engagify nags: computed corners, borders, shadows, colours and widths of all 48 nag elements on the cookie thread, nags open, at 1280px and 380px, are identical before and after.
  - Passed: Gloss on 14 page types at both widths without sideways scroll; keyboard focus visible on 25 Tab stops at both widths.
  - Passed: contrast audit Gloss OK, Engagify OK; `tests/browser/tools_launcher.mjs`; `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest NavIconSetsTest Word97ThemeCoverageTest PostActionThemeTest`, 444 run, 439 passed, the 5 baseline failures.
  - Full suite not run.
- Notes:
  - Buttons and links no longer have a 3:1 edge; their fill and text identify them, and focus rings are unchanged. Accepted by the maintainer.
  - The calendar keeps a square grid of pale lines inside its rounded card.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
