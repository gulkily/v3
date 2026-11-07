> **Feature plan:** [Step 1](./gloss_theme_step1_solution_assessment.md) · [Step 2](./gloss_theme_step2_feature_description.md) · [Step 3](./gloss_theme_step3_development_plan.md) · [Step 4](./gloss_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/gloss_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3gloss_theme). Standing P2 enrollment. Related to `v3/engagify_theme`; not a child cycle.

Extended estimates: enabled

Cycle estimate: 0.95 (revised; Step 1 0.90)

## Handoff note

Read this file first; it is the whole approved direction. Step 1 Revisions 5 and 6 are the source, and its earlier sections are history. This cycle shares branch `feat/engagify_theme` with the unmerged `v3/engagify_theme` cycle. Its [Step 4 summary](../engagify_theme/engagify_theme_step4_implementation_summary.md) explains how Engagify, theme scripts and page themes work. Since commit `54ad7a658`, the Exit pill is Engagify's only Exit control.

## Problem

Engagify's modern look exists only inside a parody theme, so no reader can use it without the nags. Exit from Engagify returns the reader to a theme that looks nothing like it, and the cycle button can start the nags by accident.

Concepts touched:

- [theme navigation icons](../../concepts/theme_navigation_icons.md): changed. A second icon set joins the pixel-art set; the entry names both sets and the themes that use each.
- [theme script](../../concepts/theme_script.md): used unchanged. Only `engagify` starts the script.
- [page theme](../../concepts/page_theme.md): changed. Exit on a page theme thread now lands on Gloss.
- Proposed: none. A shared theme stylesheet is a registry field documented in the theme development guide, not a new concept.

Feedback items: [PF-20261104-F13](../../feedback/items/PF-20261104-F13.txt) "Modern theme with icons" and [PF-20261030-F46](../../feedback/items/PF-20261030-F46.txt) "Rounder corners in modern themes". This cycle addresses both; commits carry `Feedback:` trailers for both.

## User stories

- As a reader who likes a modern look, I want to pick Gloss from the theme menu, so that I get rounded cards and a clean sans-serif face without nags.
- As a reader in Gloss, I want icons beside the navigation labels, so that I find destinations at a glance.
- As a reader in Engagify, I want Exit to leave the same site without the nags, so that leaving feels like the noise stopping.
- As a reader cycling through themes, I want the cycle button to skip Engagify, so that one click never buries the page in popups.
- As a theme author, I want one theme to reuse another theme's stylesheet, so that two themes with one look never drift apart.

## Core requirements

- **Gloss** is a selectable theme on ZenMemes: label "Gloss", name `gloss`, with Engagify's look minus the parody layer. It has no script, no nags, no Exit pill and no "NEW" badge.
- **One stylesheet.** Gloss and Engagify load the same stylesheet, `theme-gloss.css`. Its shared section applies to both theme names; its parody section applies to `engagify` only. `theme-engagify.css` goes away, and Engagify looks the same as before the move.
- **New icons.** A new icon set with smooth lines covers every destination the pixel-art set covers, 52 glyphs today. Both Gloss and Engagify show it; the pixel-art themes keep their set.
- **Exit** from Engagify works like choosing Gloss in the theme menu. Gloss becomes the reader's saved theme on every page. On a page theme thread, the page exit is also recorded, so a revisit shows Gloss.
- **Cycle button** skips Engagify from every theme. Engagify stays in the theme menu and still serves as a page theme.

## Delivery scope

- Work type: application change, with documentation.
- In scope: theme registry, theme stylesheets, the navigation icon stylesheet, `theme_toggle.js` for Exit and the cycle button, tests, the theme development guide, touched concept entries, the navigation icons rule in `AGENTS.md`, this cycle's plans and the effort ledger.
- Out of scope: new nags, other themes' looks, Gloss on sites other than ZenMemes, and changes to which pages have a page theme.

## Completion boundary

- Normal entry: the theme menu on any ZenMemes page, the cycle button, and the cookie notice thread, whose page theme is Engagify.
- End-to-end outcome: a reader picks Gloss and sees the Gloss look and icons on every page, before first paint, after reload. A reader in Engagify selects Exit and lands on Gloss without a reload; reloads and other pages stay on Gloss. Cycling from any theme never lands on Engagify.
- Recovery: if `theme-engagify.js` fails to load, Engagify still shows the Gloss look; Exit is not available, but the theme menu still is. A browser that blocks storage shows Gloss after Exit until the next page load, as other theme choices do today. With JavaScript off, pages show the site default theme.
- Release condition: smoke and changed tests pass. The contrast audit passes for Gloss and Engagify. A browser check of both themes passes at phone and desktop widths on every page type, icons included. Production check after deploy follows the existing cookie thread check.

## Risks

- **Engagify changes when its look moves.** Impact: the shipped parody breaks or loses its garnish. Earliest validation: screenshots of Engagify with its nags open, before and after the move, at 380px and 1280px. Mitigation: move the look first with no visual change, and compare before adding anything.
- **One stylesheet for two names in `theme_toggle.js`.** Impact: the file loads twice, or switching from Gloss to Engagify waits on a file that is already loaded. Earliest validation: a browser check that switches Gloss, Engagify, Gloss, Engagify and counts stylesheet requests. Mitigation: track a loaded stylesheet by its path, not only by theme name.
- **52 glyphs at a new style.** Impact: the icons take most of the cycle, or look uneven. Earliest validation: draw a small sample first, covering a main navigation link, a Tools launcher button and a group heading, and get maintainer approval on it. Mitigation: if the set threatens the cycle, Step 3 splits it out and records the split in the ledger.
- **Exit overwrites a saved theme.** Impact: a reader who saved Word 97 and wanders onto the cookie thread loses Word 97 on Exit. The maintainer chose this; it is a known effect, not a defect. Mitigation: none needed; the theme menu restores any theme in one choice.

## Shared component inventory

- `src/ForumRewrite/View/ThemeRegistry.php`: theme list, `stylesheetPaths()` and `scriptPaths()`. Extend: an entry may name another theme's stylesheet; `stylesheetPaths()` honors it. No new registry.
- `src/ForumRewrite/SiteProfileRegistry.php`: ZenMemes `permittedThemes` and page themes. Reuse: add `gloss` to the list; the page theme stays `engagify`.
- `src/ForumRewrite/View/TemplateRenderer.php`, `templates/layout.php`, `templates/standalone_layout.php`: initial theme stylesheet and pre-paint script. Reuse unchanged; they read paths from the registry.
- `public/assets/theme_toggle.js`: `applySelection` for menu choices, `forumExitTheme` for Exit, `nextTheme` for the cycle button, stylesheet loading by theme. Extend: Exit calls the same path as a menu choice of Gloss; `nextTheme` skips Engagify; loading tolerates a shared path. The stored previous theme becomes unused by Exit; Step 3 decides whether to remove it.
- `public/assets/theme-engagify.css`: source of the look. Its content moves into a new `public/assets/theme-gloss.css`, split into a shared section and a parody section. New file because Gloss needs its own name; no second copy of the look remains.
- `public/assets/theme-engagify.js`: nags and the Exit pill. Reuse unchanged.
- `public/assets/theme-nav-icons.css`: pixel-art icons keyed by link, for Word 97, Chicago and Midnight. Extend: a second block of rules for `gloss` and `engagify`, keyed by the same links. Same selectors, so adding a destination still means one rule per set.
- `templates/partials/theme_menu.php`: menu rows and swatches. Reuse unchanged; Gloss needs a swatch rule and no badge.
- Tests: `ThemeRegistryTest`, `ThemeToggleScriptTest`, the theme allow-lists in `LocalAppSmokeTest`, `tests/browser/theme_cycle.cjs`, `tests/browser/theme_scripts.cjs`, `tests/browser/tools_launcher.mjs` and `tests/theme_contrast_audit.py`. Extend each for Gloss, the cycle skip, Exit to Gloss and the second icon set.
- Docs: `docs/runbooks/theme_development_guide.md` (shared stylesheet field), the concept entries above, and the [navigation icons rule](../../../AGENTS.md#navigation-icons), which lists the icon themes.

## User flow

1. A reader opens the theme menu and picks Gloss.
2. The page shows the Gloss look and icons; every later page and reload stays on Gloss.
3. Another reader opens the cookie notice thread, which shows Engagify with its nags.
4. That reader selects the Exit pill. The nags go, the page shows Gloss, and Gloss is now their saved theme.
5. A reader presses the cycle button repeatedly; it passes from the theme before Engagify straight to the theme after it.

## Success criteria

- Gloss appears in the ZenMemes theme menu and the cycle order, and loads before first paint.
- Gloss and Engagify both pass the contrast audit.
- Each of the 52 destinations shows a new-set glyph in Gloss and in Engagify, at 380px and 1280px; the pixel-art themes are unchanged.
- Engagify screenshots before and after the move match, apart from the new icons.
- Exit from Engagify, picked or page theme, shows Gloss without a reload and saves it; a reload of the cookie thread shows Gloss.
- No sequence of cycle button presses reaches Engagify.
- Switching between Gloss and Engagify fetches `theme-gloss.css` once.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Enhancement requests

Carried from Step 1:

- Exit lands on the closest theme: folded into this cycle as Exit to Gloss. Approved with Step 1.
- Cycle button skips Engagify: in scope. Approved with Step 1.
- New icon set: in scope, drawn first. Approved with Step 1; Step 3 may split it out if the cycle runs over 1.0.

## Study checkpoint

2026-11-07T13:07:30Z, `railspike-effort` / `v3/gloss_theme`, Step 2 handoff. Step 1 explicitly approved with Revisions 1–6 ("approved step 1"). Scope v6 unchanged. Starting revision `54ad7a6580cc04d3553d6da92689eb2829401944`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.90 → **0.95**, from new understanding, not changed scope: the icon set is 52 glyphs, needs its own block of rules, and the `AGENTS.md` icon rule and concept entry change too. No implementation, measured labor or accepted outcome.

Step 2 approved by the maintainer on 2026-11-07 ("approved step 2").
