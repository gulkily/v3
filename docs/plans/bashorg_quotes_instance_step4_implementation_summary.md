# Bash.org-Style Quotes Instance — Step 4: Implementation Summary

## Stage 1 - Site instance identity plumbing
- Changes:
  - Added a `bashorg` entry to `SiteProfileRegistry::all()` (`src/ForumRewrite/SiteProfileRegistry.php`): `defaultTheme: bashorg`, `composerPrompt: "Submit a quote..."`.
  - Added a `bashorg` entry to `ThemeRegistry::all()` (`src/ForumRewrite/View/ThemeRegistry.php`), `mode: light`.
  - Documented `bashorg` as a valid `FORUM_SITE_ID` value in `docs/runbooks/production_deploy.md`.
  - Extended `tests/SiteProfileRegistryTest.php` with `testActiveHonorsBashorgOverride`, mirroring the existing `chouse` coverage.
- Verification:
  - `./v3 test SiteProfileRegistryTest` — 5 run, 5 passed (including the new bashorg case).
  - Inline PHP check with `FORUM_SITE_ID=bashorg` set: `SiteProfileRegistry::active()` resolves `{"name":"bashorg","defaultTheme":"bashorg","composerPrompt":"Submit a quote..."}`; `ThemeRegistry::explicitNames()` includes `bashorg`; `ThemeRegistry::stylesheetPaths()['bashorg']` resolves to `/assets/theme-bashorg.css`.
- Notes:
  - The `/assets/theme-bashorg.css` file referenced above does not exist yet — that's Stage 2. No error occurs from its absence yet since nothing serves the stylesheet until a page actually loads under this theme.

## Stage 2 - Bash.org-accurate theme stylesheet
- Changes:
  - Added `public/assets/theme-bashorg.css`: variable block (`:root[data-theme="bashorg"]`, `.theme-menu__option[data-theme-option="bashorg"]`) using the archived palette (`#ffffff` page, `#000000` ink, `#c08000` line/active/button chrome), `--code-font` set to Courier New/Lucida Console for the quote body font Stage 4 will use, theme swatch, and scoped chrome overrides (`.site-header`, `.eyebrow`, `.card`, `.nav-link.is-active`) synthesizing the orange title-bar look from existing markup per the theme guide's "CSS-only illusion" approach.
  - Updated `tests/LocalAppSmokeTest.php`'s theme allow-list assertion (`var allowed = [...]`) and added a `data-theme-option="bashorg"` presence assertion, per the theme guide's required two updates.
- Verification:
  - `./v3 test LocalAppSmokeTest` — 113 run, 110 passed; the 3 failures (`testAnonymousPublicBoardDoesNotStartViewerSession`, `testPostAndActivityLinkAdjacentSignatureFiles`, `testSqliteViewerRouteUsesToolsShellAndPublishedSource`) are pre-existing long-standing failures per the suite's own test-run history tracking, unrelated to `SiteProfileRegistry`/`ThemeRegistry`/theme CSS.
  - Confirmed `testApplicationRendersCoreRoutes` (the test containing the updated allow-list/theme-option assertions) passed.
- Notes:
  - No template changes were needed — `ThemeRegistry` already feeds the popover markup, cycle button, anti-FOUC allow-list, and `layout.php`'s stylesheet-swap map automatically, per the theme guide.

## Stage 3 - Upvote/downvote tag weights
- Changes:
  - Added `upvote => 1` and `downvote => -1` to `TagScore::scoredTags()` (`src/ForumRewrite/TagScore.php`), alongside the existing `like`/`flag` entries.
  - Added `tests/TagScoreTest.php` (new, registered in `tests/run.php`) covering the two new tags, the two existing tags' weights staying unchanged, and an unknown-tag case.
- Verification:
  - `./v3 test TagScoreTest` — 4 run, 4 passed.
  - Read-path confirmation (no code change needed): `LocalWriteService::normalizeThreadTag()`/`normalizePostTag()` validate against `TagScore::isScoredTag()` directly — adding the two tags there is what makes the write API accept them at all, not just what scores them. `ReadModelBuilder`/`IncrementalReadModelUpdater` iterate `TagScore::scoredTags()` generically when computing `score_total`/`post_score_total`, so no reducer change was needed either.
- Notes:
  - **Resolved the Stage 3 risk flagged in Step 3 (approval gating):** confirmed in code (`LocalWriteService::isApprovedIdentity()` plus the score-reduction loops in `ReadModelBuilder`/`IncrementalReadModelUpdater`) that a reaction record is always written regardless of the voter's approval status, but the displayed `score_total`/`post_score_total` only moves for reactions from identities already marked `is_approved` in the `profiles` table. This is pre-existing behavior that `like`/`flag` already have today — upvote/downvote inherit it unchanged rather than introducing a new limitation. Net effect: a drive-by visitor's upvote/downvote is recorded but will not visibly move the score unless/until their identity is approved. This is a real gap from "anonymous bash.org-style voting" as originally imagined, but closing it would mean changing shared approval-gating behavior used by every other reaction on the site — out of scope for this feature per the plan's "reuse, don't fork" rule. Flagging for a separate decision if genuinely-anonymous vote-moves-the-number behavior turns out to matter.
