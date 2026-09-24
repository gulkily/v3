# Chouse Theme — Step 4: Implementation Summary

Note: this summary was written retroactively alongside Stage 5, after
Stages 1-4 were already implemented and committed. Each stage's commit
is accurate and stage-scoped; only the summary-doc update lagged behind
rather than landing in the same commit as its stage, as the process
otherwise calls for.

## Stage 1 - Registry + palette
- Changes:
  - `src/ForumRewrite/View/ThemeRegistry.php`: new entry `['name' => 'chouse', 'label' => 'Chouse', 'mode' => 'dark']`
  - `public/assets/site.css`: new variable block for `:root[data-theme="chouse"]` — a warm walnut/brass dark palette (deep walnut `--page-bg`, cream `--ink`, bronze `--line`, brass `--active-bg`/`--active-line`), serif body font, no reference to Red Bull's actual colors
  - `tests/LocalAppSmokeTest.php`: updated the one existing assertion that hardcodes the theme allow-list string, an unavoidable consequence of the registry addition
- Verification:
  - `php -l` on the touched PHP file — no syntax errors
  - `php tests/run.php` — full suite green
- Notes: none

## Stage 2 - Header/wordmark chrome
- Changes:
  - `public/assets/site.css`: brass `.theme-swatch[data-theme="chouse"]` swatch; scoped `.site-header`/`.eyebrow` rules turning the site-name label into a vertical brass signage tab (`writing-mode: vertical-rl`, CSS-only, no image assets)
- Verification:
  - Headless Chrome screenshots at 1280px and 380px widths (`FORUM_SITE_ID=chouse`, theme forced via the Stage 4 default) — wordmark renders as a legible vertical tab; confirmed no "Continue to Markit event page" link or reference to the concluded external event anywhere in the rendered header
  - Screenshot comparison against the default zenmemes theme at 380px confirmed the "Comfortable ▾" control overflow is a pre-existing app-wide issue, not introduced by this stage
  - `php tests/run.php` — full suite green (verified with the Stage 4 preview change stashed, so the check reflects only this stage's diff)
- Notes: none

## Stage 3 - Shell-wide chrome
- Changes:
  - `public/assets/site.css`: brass top-accent strip on `.card` (via `::before`), inset shadow on `input`/`textarea` for tactile depth, brass hover borders on buttons/nav links — all scoped under `[data-theme="chouse"]`
- Verification:
  - Headless Chrome screenshots of the board (all-threads view) and about page — cards, thread titles, bylines, and the composer all read clearly against the palette
  - Screenshot of the default zenmemes theme confirmed pixel-unchanged rendering (no bleed from the new scoped rules)
  - `php tests/run.php` — full suite green (Stage 4 preview change stashed during verification)
- Notes:
  - Buttons, inputs, and `.nav-link.is-active` already inherit correctly from the theme's CSS custom properties via existing generic rules (`:root:not([data-theme="chicago"]) .nav-link.is-active`, base `button`/`input` variable usage) — no theme-specific override was needed for those, which kept this stage smaller than planned

## Stage 4 - Wire as chouse default
- Changes:
  - `src/ForumRewrite/SiteProfileRegistry.php`: `chouse` entry's `defaultTheme` changed from `'auto'` to `'chouse'`; `zenmemes` entry unchanged
  - `tests/LocalAppSmokeTest.php`: updated `testBoardPageRendersActiveSiteProfilePerFormSiteId` to expect `data-default-theme="chouse"` for the chouse case (was asserting the prior `auto` default)
- Verification:
  - `php tests/run.php` — full suite green
  - Manual render check: `FORUM_SITE_ID=chouse` now yields `data-default-theme="chouse"`; zenmemes still yields `data-default-theme="auto"`
- Notes:
  - This change was implemented early (before Stages 2-3) and used, uncommitted, to drive visual QA screenshots for those stages — each of those stages' own verification was re-run with this change stashed out to confirm their diffs pass in isolation before committing

## Stage 5 - Coverage, verification, docs
- Changes: none (verification-only stage; the allow-list and default-theme test updates it called for were already made in Stages 1 and 4 respectively, as direct consequences of those changes)
- Verification:
  - Ran the theme guide's three stale-state traps: deleted `public/index.html` (and, after an exploratory `php scripts/build_static_artifacts.php` run against the existing zenmemes local repository produced a large batch of gitignored static artifacts unrelated to this feature, deleted all of `public/*.html` and the generated `public/tags/`, `public/about/`, `public/tools/*.html` recursively to fully reset)
  - `php tests/run.php` — full suite green from a clean state
  - Visual sweep via headless Chrome screenshots: board (all-threads view), about page, header at desktop and ~380px widths, all under the chouse theme; one comparison screenshot of the default zenmemes theme to confirm no regression
- Notes:
  - Full interactive verification (theme popover open/close, keyboard focus, text selection, `:target` permalink jump) from the theme guide's workflow was not performed — this environment has headless Chrome but no `playwright-core`/interaction-scripting installed, so only static screenshots were captured. Recommend a manual interactive pass before treating this as production-ready.
  - No custom scrollbar styling was added for this theme, so the guide's headed-Chrome scrollbar-rendering caveat does not apply here.
