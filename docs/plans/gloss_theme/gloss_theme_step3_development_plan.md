> **Feature plan:** [Step 1](./gloss_theme_step1_solution_assessment.md) · [Step 2](./gloss_theme_step2_feature_description.md) · [Step 3](./gloss_theme_step3_development_plan.md) · [Step 4](./gloss_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/gloss_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3gloss_theme). Standing P2 enrollment. Related to `v3/engagify_theme`; not a child cycle.

Extended estimates: enabled

Cycle estimate: 0.95

Concepts touched: [theme navigation icons](../../concepts/theme_navigation_icons.md) (changed in Stages 1–5, entry rewritten in Stage 8); [page theme](../../concepts/page_theme.md) (Exit changes in Stage 7, entry updated in Stage 8); [theme script](../../concepts/theme_script.md) (used unchanged). No new concept entries.

Feedback: [PF-20261104-F13](../../feedback/items/PF-20261104-F13.txt) and [PF-20261030-F46](../../feedback/items/PF-20261030-F46.txt). Each stage commit that delivers part of an item carries its `Feedback:` trailer and adds a dated `Notes:` line to the item file. Stage 8 sets `Workflow: blocked` until deploy and production check, as `AGENTS.md` requires.

## Handoff note

Read [Step 2](./gloss_theme_step2_feature_description.md) for the approved requirements and inventory; this plan does not repeat them. Work happens on `feat/engagify_theme`, shared with the unmerged `v3/engagify_theme` cycle. Stage 1 stops for the maintainer's approval of the icon style before Stages 2–5. No third-party icons or tools: draw every glyph by hand, per the dependency policy in `CONTRIBUTING.md`.

## Completion Contract

- Normal entry: the theme menu and cycle button on any ZenMemes page; the cookie notice thread, `/threads/thread-20261102152258-25e82c97`, whose page theme is Engagify.
- End-to-end outcome: Gloss is selectable, shows its look and the new icons on every page before first paint, and stays after reload. Engagify looks as before, with the new icons. Exit from Engagify, picked or page theme, shows Gloss without a reload and saves it. The cycle button never lands on Engagify.
- Recovery: a failed `theme-engagify.js` leaves the Gloss look, and the theme menu still works. Blocked storage keeps Gloss until the next page load. JavaScript off shows the site default theme.
- Deployment: an ordinary deploy. The cookie thread's baked page keeps its page theme mark, so no rebuild beyond the usual is needed; the production check confirms it.
- Release condition: smoke plus changed tests pass; contrast audit passes for Gloss and Engagify; the Stage 8 browser sweep passes. The full suite is not run; the maintainer runs it.

## Key Risks

- **High risk:** **Engagify changes when its look moves.** Impact: the shipped parody breaks. Early validation: Stage 6 takes Engagify screenshots before the move and compares after. Mitigation: move first with no visual change; add nothing in that stage.
- **Shared stylesheet loads twice or stalls.** Impact: switching Gloss to Engagify refetches or waits on a loaded file. Early validation: Stage 6 counts stylesheet requests while switching. Mitigation: `theme_toggle.js` tracks loaded stylesheets by path.
- **Icon style rejected late.** Impact: 52 glyphs redrawn. Early validation: Stage 1 sample, approved before Stage 2. Mitigation: no further glyphs until the maintainer approves.
- **Icon work overruns.** Impact: the cycle runs past 1.0. Early validation: Stage 2's actual size. Mitigation: if Stage 2 runs long, split Stages 3–5 into a follow-up cycle and record the split in the ledger.

## Stage 1

**Estimate: 0.08**

- Goal: a sample of the new icon set, visible in Engagify, approved by the maintainer.
- Dependencies: none.
- Expected changes: a second block in `theme-nav-icons.css` keyed to `gloss` and `engagify`, with the same per-link selectors as the pixel-art block. Three glyphs: Board (`/`), the Tools launcher's Backup button and Tools subnavigation link, and the operator group heading. Settle the size, stroke, corner radius and colors here, in Engagify's palette.
- Verification approach: browser screenshots of Engagify at 380px and 1280px, on the board and Tools pages; the pixel-art themes unchanged. Show the maintainer and wait for approval.
- Estimate details: driver: choosing a style that reads at 16px; uncertainty: medium.
- Risks or open questions:
  - Impact: the nags or the app bar cover icons. Early warning: the board screenshot with nags open. Mitigation: icons sit in the same place as labels, which nags already avoid.
  - Open: icon size. The pixel-art set is 16px; smooth lines may need 18px. Decide with the maintainer.
- Canonical components/API contracts touched: `theme-nav-icons.css` selectors (extended with a second theme list), the [navigation icons rule](../../../AGENTS.md#navigation-icons).

## Stage 2

**Estimate: 0.10**

- Goal: every main navigation glyph in the new set.
- Dependencies: Stage 1 approved.
- Expected changes: glyphs for the main navigation links, the board filters keyed by `data-nav-key`, the New post button, profile and messages links, and the phone More panel, which shares the same rules.
- Verification approach: browser check in Engagify at 380px and 1280px, desktop bar and phone More panel; every link shows a glyph.
- Estimate details: driver: about 25 glyphs; uncertainty: medium.
- Risks or open questions: overrun, as in Key Risks.
- Canonical components/API contracts touched: `theme-nav-icons.css` (extended).

## Stage 3

**Estimate: 0.10**

- Goal: every Tools glyph in the new set.
- Dependencies: Stage 2.
- Expected changes: one glyph per Tools destination, used by both the launcher button and the Tools subnavigation link, plus Gallery and Recent.
- Verification approach: browser check of `/tools/` and one tool page in Engagify at both widths.
- Estimate details: driver: about 19 glyphs; uncertainty: low, as the style is settled.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `theme-nav-icons.css` (extended).

## Stage 4

**Estimate: 0.06**

- Goal: launcher group headings and Bookmarklets buttons in the new set; full coverage tested.
- Dependencies: Stage 3.
- Expected changes: the remaining glyphs, four group headings and four Bookmarklets buttons. `tests/browser/tools_launcher.mjs` asserts a glyph for every button, bar link and heading in Engagify too.
- Verification approach: `tools_launcher.mjs` passes in all icon themes; a script compares the new block's selectors with the pixel-art block's, so no destination is missing.
- Estimate details: driver: eight glyphs and one test extension; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `theme-nav-icons.css`, `tests/browser/tools_launcher.mjs` (extended).

## Stage 5

**Estimate: 0.08**

- Goal: a theme can use another theme's stylesheet.
- Dependencies: none; may run before Stage 1.
- Expected changes: a registry entry may name another theme's stylesheet; `ThemeRegistry::stylesheetPaths()` returns that path for it. `theme_toggle.js` tracks loaded stylesheets by path, so two names that share one file load it once. No theme uses the field yet.
- Verification approach: `ThemeRegistryTest` and `ThemeToggleScriptTest` cases for a shared path; `./v3 test --smoke ThemeRegistryTest ThemeToggleScriptTest`.
- Estimate details: driver: load-state tracking in `theme_toggle.js`; uncertainty: low to medium.
- Risks or open questions: shared stylesheet loading, as in Key Risks.
- Canonical components/API contracts touched: `ThemeRegistry::stylesheetPaths` (extended), `theme_toggle.js` stylesheet loading (extended), `TemplateRenderer.php` and layouts (unchanged; they read registry paths).

## Stage 6

**Estimate: 0.18**

- Goal: Gloss is selectable, and Engagify looks the same with its look moved into Gloss.
- Dependencies: Stages 1–5.
- Expected changes: new `theme-gloss.css` with Engagify's variables and scoped look, written for both names, then a parody section for `engagify` only: nags, gradient garnish, Exit pill and "NEW" badge. Gloss gets its own swatch. `theme-engagify.css` is removed. Registry: `gloss`, label "Gloss", mode light, placed away from Engagify in the order; Engagify names Gloss's stylesheet. ZenMemes `permittedThemes` gains `gloss`. Update the `LocalAppSmokeTest` allow-lists and `theme_cycle.cjs`.
- Verification approach: Engagify screenshots before and after, nags open, at 380px and 1280px, compared. Gloss on every page type at both widths, with icons. `tests/theme_contrast_audit.py` for both. Stylesheet requests counted while switching Gloss and Engagify. Smoke plus `ThemeRegistryTest`, `LocalAppSmokeTest` theme cases.
- Estimate details: driver: splitting one stylesheet without visual change, plus a new theme on every page; uncertainty: medium.
- Risks or open questions: Engagify changing and shared loading, as in Key Risks.
- Canonical components/API contracts touched: `ThemeRegistry.php`, `SiteProfileRegistry.php` `permittedThemes`, theme stylesheet structure per `docs/runbooks/theme_development_guide.md`, `templates/partials/theme_menu.php` (unchanged).

## Stage 7

**Estimate: 0.08**

- Goal: Exit from Engagify lands on Gloss and saves it; the cycle button skips Engagify.
- Dependencies: Stage 6.
- Expected changes: `forumExitTheme` chooses Gloss through the same path as a menu choice, which already records the page exit on a page theme thread. `nextTheme` skips `engagify`. Remove the stored previous theme if nothing else reads it.
- Verification approach: `ThemeToggleScriptTest` and `theme_scripts.cjs` cases: Exit after picking Engagify, Exit on a page theme thread with a saved Word 97, reload after each; a full cycle never reaches Engagify. Browser check on the cookie thread at both widths.
- Estimate details: driver: Exit's two paths and their tests; uncertainty: low.
- Risks or open questions:
  - Impact: a reader on the cookie thread loses a saved theme on Exit. Accepted in Step 2; not a defect.
- Canonical components/API contracts touched: `theme_toggle.js` `applySelection`, `forumExitTheme`, `nextTheme` (extended); page exit storage (reused).

## Stage 8

**Estimate: 0.10**

- Goal: documentation, feedback records and the final sweep.
- Dependencies: Stage 7.
- Expected changes: theme development guide gains the shared stylesheet field. Concept entries for navigation icons and page theme updated. The `AGENTS.md` navigation icons rule names both icon sets and their themes. Feedback items F13 and F46: `Evidence`, dated `Notes:`, `Workflow: blocked` pending deploy. Step 4 summary closeout and ledger.
- Verification approach: browser sweep, Gloss and Engagify, fresh browser and saved Word 97, 380px and 1280px, mouse and keyboard, every page type including standalone layout pages; pixel-art themes spot-checked. `git diff --check`; links in touched docs resolve. Final diff review for duplication and scope.
- Estimate details: driver: the sweep across page types; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/runbooks/theme_development_guide.md`, `docs/concepts/`, `AGENTS.md`, `docs/feedback/items/`.

## Study checkpoint

2026-11-07T13:11:40Z, `railspike-effort` / `v3/gloss_theme`, Step 3 handoff. Step 2 explicitly approved ("approved step 2"). Scope v6 unchanged. Starting revision `54ad7a6580cc04d3553d6da92689eb2829401944`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Whole-cycle projected total **0.95** reaffirmed. Stage-work originals 0.08/0.10/0.10/0.06/0.08/0.18/0.08/0.10; annotations, not additive. No implementation, measured labor or accepted outcome.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
