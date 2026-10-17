> **Feature plan:** [Step 1](./word97_chicago_disabled_and_dark_step1_solution_assessment.md) · [Step 2](./word97_chicago_disabled_and_dark_step2_feature_description.md) · [Step 3](./word97_chicago_disabled_and_dark_step3_development_plan.md) · [Step 4](./word97_chicago_disabled_and_dark_step4_implementation_summary.md)

Debug mode: enabled

# Step 2: Feature Description — Word 97 disabled buttons and dark Win9x theme

## Problem
In the Word 97 theme, disabled buttons do not look disabled. Users who like the Chicago/Word 97 look have no dark option.

## User stories
- As a Word 97 theme user, I want disabled buttons to look clearly grayed out so that I know which actions are unavailable.
- As a reader who prefers dark themes, I want a dark theme with the Chicago/Word 97 look so that I get the Win9x feel without a bright page.

## Core requirements
- Word 97 disabled buttons show visibly grayer text than enabled buttons, readable but clearly inactive. Chicago is unchanged.
- One new dark theme (blend of Chicago and Word 97 chrome, dark palette) is selectable from the theme menu, cycle button and persisted choice, with its own swatch and no flash on load.
- The dark theme styles every page surface the Word 97 and Chicago themes cover (header, cards, forms, buttons, nav, scrollbars, compact thread view, tool launcher icons), and disabled buttons in it also look disabled.
- Existing themes' appearance and the theme menu's representative rows are unaffected.

## Delivery scope
- Work type: application change (theme CSS, theme registry, and smoke-test assertions).
- No server routes, data, or JS behavior changes.

## Completion boundary
- Entry: theme menu → pick the new dark theme (or Word 97 for the fix).
- Outcome: pages render fully themed and persist on reload; Word 97 disabled buttons look disabled.
- Recovery: switching to any other theme restores its prior look; removing the registry entry reverts the feature.
- Release: smoke tests pass and manual check of key pages in Word 97 and the dark theme.

## Risks
- **Diagnosis:** a `button[disabled]` rule already exists in `theme-word97.css`, so the defect may be a specificity override, a non-`button` disabled control (e.g. disabled nav links), or low contrast. Impact: fix misses the real cause. Validate first in Step 3 stage 1 by inspecting the live page. Mitigation: confirm which elements are affected before changing CSS.
- **Blend ambiguity:** "best of both" is subjective. Impact: rework. Mitigation: Step 3 names the borrowed elements per theme (Chicago's teal-derived desktop and soft shell; Word 97's title bars, bevels, navy active color and fonts) for review before implementing.
- **Coverage drift:** Word 97 has ~500 lines of scoped rules with hardcoded hexes; a copy-and-recolor can miss surfaces or leave light bits. Mitigation: page-by-page visual checklist.
- **Shared selectors:** nav-icon and other rules are keyed to `word97`/`chicago`; the new theme needs them extended without altering existing ones. Mitigation: extend `:is(...)` lists and run smoke tests.

## Shared component inventory
- Theme registry (`ThemeRegistry.php`): extend with one entry; feeds menu, cycle, allow-list, and stylesheet map.
- Stylesheets: new `theme-<name>.css` modeled on `theme-word97.css`/`theme-chicago.css`; `site.css` base disabled rules reused.
- `theme-nav-icons.css`: extend existing `:is([data-theme="word97"], [data-theme="chicago"])` selectors rather than duplicating icons.
- `SiteProfileRegistry.php` theme lists and `tests/LocalAppSmokeTest.php` allow-list/option assertions: update for the new theme.
- `docs/runbooks/theme_development_guide.md`: reused as the procedure; no new components needed.

## Simple user flow
1. User opens the theme menu.
2. User picks the new dark theme; the page re-renders dark with Win9x chrome.
3. User reloads or navigates; the choice persists.
4. User switches to Word 97; disabled buttons appear grayed out.

## Success criteria
- Word 97 disabled buttons are visibly distinguishable from enabled ones (text grayer, confirmed in browser on a real disabled control).
- The dark theme appears in the menu with a swatch, persists across reloads, and has no unthemed light areas on the checked pages.
- Chicago and all other themes are visually unchanged; smoke tests pass.
