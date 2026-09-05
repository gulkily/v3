# Chouse Theme — Step 3: Development Plan

Note: chouse.club's live header includes a "Continue to Markit event page"
link to an external, now-concluded event. This theme reproduces the site's
branding feel (clubhouse identity, vertical wordmark) but explicitly does
not carry over that link or any other reference to the concluded event —
called out again under Stage 2's risks below so it isn't reintroduced by
pattern-matching the live site too literally.

## Stage 1
- Goal: Register the `chouse` theme and establish its base palette so it exists as a valid, selectable theme.
- Dependencies: `SiteProfileRegistry` and `ThemeRegistry` from the multi-site-config feature; branches from `feature/multi-site-config` (not yet merged to `main`)
- Expected changes:
  - `src/ForumRewrite/View/ThemeRegistry.php`: new entry `['name' => 'chouse', 'label' => 'Chouse', 'mode' => 'light'|'dark']` (mode decided during palette design)
  - `public/assets/site.css`: new variable block `:root[data-theme="chouse"], .theme-menu__option[data-theme-option="chouse"]` with an original palette (not Red Bull's actual colors) — `color-scheme`, `--page-bg`, `--ink`, `--panel`, `--button-bg`, fonts
- Verification approach: `php tests/run.php` still green (registry order/uniqueness assertions); manual check that the theme is listed in the popover and applies its palette when selected
- Risks or open questions:
  - none
- Canonical components/API contracts touched: `ThemeRegistry::all()`, `public/assets/site.css` variable block

## Stage 2
- Goal: Add the theme's signature header chrome — the vertical "CHOUSE" wordmark treatment and swatch — without any template changes.
- Dependencies: Stage 1
- Expected changes:
  - `public/assets/site.css` swatch rule (`.theme-swatch[data-theme="chouse"]`) and scoped section styling `.site-header`/`.eyebrow` with a `writing-mode: vertical-rl`-style wordmark treatment, CSS-only (no image assets)
- Verification approach: visual check of the header under the chouse theme at desktop and ~380px width; confirm no image/network assets added
- Risks or open questions:
  - chouse.club's live header also carries a "Continue to Markit event page" link to a concluded external event — this is explicitly out of scope and must not be reproduced; verify the rendered header contains no such link or reference before calling this stage done
- Canonical components/API contracts touched: `public/assets/site.css` swatch + scoped section (header chrome only)

## Stage 3
- Goal: Extend the scoped section to the rest of the shell — cards, composer, buttons — so the page reads as a distinct artifact, not a palette swap.
- Dependencies: Stage 2
- Expected changes:
  - `public/assets/site.css` scoped-section additions for `.card`, composer chrome, and buttons, using CSS-only synthesis techniques (title-bar illusions, pseudo-element decoration, box-shadow frames) per `docs/runbooks/theme_development_guide.md`
- Verification approach: visual sweep (board, a thread, tools, tags, users, about) under headed Chrome per the theme guide's verification workflow; confirm interactive elements stay visible/reachable and no `data-*`/class/role selectors used by JS are renamed
- Risks or open questions:
  - must not alter any other theme's rendering — scoped selectors only, verified by a visual pass over the other 12 existing themes
- Canonical components/API contracts touched: `public/assets/site.css` scoped section (shell-wide)

## Stage 4
- Goal: Make chouse the automatic default for the chouse site profile.
- Dependencies: Stage 1
- Expected changes:
  - `src/ForumRewrite/SiteProfileRegistry.php`: `chouse` entry's `defaultTheme` changes from `'auto'` to `'chouse'`; `zenmemes` entry unchanged
- Verification approach: manual render check (as used in the multi-site-config feature) confirming `data-default-theme="chouse"` for `FORUM_SITE_ID=chouse` and `data-default-theme="auto"` still for zenmemes
- Risks or open questions:
  - none
- Canonical components/API contracts touched: `SiteProfileRegistry::all()`

## Stage 5
- Goal: Close out coverage, verification, and documentation.
- Dependencies: Stages 1-4
- Expected changes:
  - `tests/LocalAppSmokeTest.php`: add `chouse` to the theme allow-list assertion and any `data-theme-option` presence check
  - Run the theme guide's three stale-state traps: delete `public/index.html`, rebuild static artifacts, run the full suite
- Verification approach: `php tests/run.php` green; full visual sweep per `docs/runbooks/theme_development_guide.md` (standard route sweep, `:target` jump, keyboard focus, text selection, theme popover leak check)
- Risks or open questions:
  - production static-artifact routes (thread/profile/tag) silently keep the old anti-FOUC allow-list until artifacts are rebuilt — confirm the rebuild step is included in this deployment's runbook, not just local dev
- Canonical components/API contracts touched: `tests/LocalAppSmokeTest.php`, static artifact rebuild pipeline
