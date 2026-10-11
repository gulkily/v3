> **Feature plan:** [Step 1](./forte_dark_theme_step1_solution_assessment.md) · [Step 2](./forte_dark_theme_step2_feature_description.md) · [Step 3](./forte_dark_theme_step3_development_plan.md) · [Step 4](./forte_dark_theme_step4_implementation_summary.md)

## Problem

Forte always renders in a fixed light palette, ignoring both the viewer's OS dark mode and the theme they chose on the site. Dark-mode viewers get a bright page that clashes with the rest of the site.

## User Stories

- As a viewer whose system prefers dark, I want Forte to render dark when my site theme is Auto so that it matches my device setting.
- As a viewer who chose a dark site theme, I want Forte to render dark so that it matches the pages I came from.
- As a viewer who chose Light on the site, I want Forte to stay light even if my OS is dark so that my explicit choice is respected.
- As a dark-mode reader, I want the dark Forte to look polished and keep its windowed-chrome character so that it feels deliberate, not inverted.

## Core Requirements

- Forte is dark when the resolved site theme is dark, or when the theme is Auto and the system prefers dark; otherwise it is unchanged.
- One cohesive dark palette covers every Forte surface: window chrome, panes, toolbar, lists, thread/reply cards, compose panel and dialog, user detail pane, forms, and focus/selection states.
- Text and interactive states meet readable contrast; form controls and scrollbars follow the dark scheme.
- The correct theme is applied on first paint, with no visible flash of the light palette.
- No new theme control is added to Forte, and light-mode rendering is visually unchanged.

## Delivery Scope

- Work type: application change (Forte stylesheet plus the shared standalone-page theme resolution; no schema or content changes).
- Applies to all Forte pages: board/thread, activity, profile, username, and user directory.
- Non-Forte pages and site themes are out of scope.

## Completion Boundary

- Entry: a viewer with a dark site theme, or Auto on a dark system, opens any Forte page.
- Outcome: the page renders in the dark palette end to end across all Forte surfaces and stays consistent while navigating between them.
- Recovery: viewers with no stored preference, unsupported storage, or a light result get today's light palette; reverting the change restores the prior look.
- Release: visual check passes on every Forte page in light and dark, and existing Forte, theme, and presentation tests still pass.

## Risks

- **Theme resolution drift.** Impact: Forte could disagree with the main layout (dark in Forte, light elsewhere). Earliest validation: compare resolved theme across Auto, Light, Dark, and a dark named theme on both layouts. Mitigation: reuse the main layout's resolution rules and theme mode data rather than re-implementing them; settle the approach in Step 3.
- **Flash of light palette.** Impact: dark viewers see a white flash on load. Earliest validation: throttled reload in a dark theme. Mitigation: resolve the theme before first paint, as the main layout already does.
- **Hard-coded colors outside the palette tokens.** Impact: leftover light patches (borders, icons, embeds, inline styles). Earliest validation: walk every Forte page in dark and inspect for light regions. Mitigation: inventory non-token colors in Step 3 and route them through the palette.
- **Shared partial regressions.** Impact: partials also used on non-Forte pages could shift. Earliest validation: render non-Forte pages before and after. Mitigation: scope all dark rules to Forte's own container.

## Shared Component Inventory

- `public/assets/forte.css`: the single Forte stylesheet and its palette tokens; extended with a dark palette, not forked.
- `templates/standalone_layout.php` and `TemplateRenderer::renderStandalonePage`: shared by all Forte pages; extended to resolve the viewer's theme, since they do not today.
- Main layout theme resolution (`templates/layout.php`, `theme_toggle.js`, `ThemeRegistry` mode data): canonical source of the theme choice and dark/light mapping; reused, not duplicated.
- Forte controllers (`ForteBoardController`, `ForteProfileController`, `ForteUserDirectoryController`, `ForteActivityController`) and `forte_*` pages / `paned_*` partials: all route through the standalone layout, so no per-page changes are expected.
- `activity.css`: loaded alongside Forte on the activity page; checked for dark compatibility, extended only if needed.
- No new component is required.

## User Flow

1. Viewer opens any Forte page.
2. The page resolves the viewer's theme (saved choice, else system preference).
3. A dark result paints the dark palette immediately; a light result paints as today.
4. Viewer moves between Forte pages and sees a consistent appearance.

## Success Criteria

- Each of Auto/dark system, Auto/light system, Light, Dark, and a dark named theme yields the expected Forte appearance on every Forte page.
- No light-palette flash is visible on a dark load.
- Primary text and controls meet WCAG AA contrast in the dark palette.
- No light-colored regions remain on any Forte page in dark.
- Light-mode Forte and non-Forte pages are visually unchanged, and existing tests pass.

Waiting for "Approved Step 2" before drafting Step 3.
