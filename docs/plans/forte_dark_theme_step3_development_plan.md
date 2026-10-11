> **Feature plan:** [Step 1](./forte_dark_theme_step1_solution_assessment.md) · [Step 2](./forte_dark_theme_step2_feature_description.md) · [Step 3](./forte_dark_theme_step3_development_plan.md) · [Step 4](./forte_dark_theme_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a viewer with a dark site theme, or Auto on a dark system, opens any Forte page (board/thread, activity, profile, username, user directory).
- End-to-end outcome: the page paints in one cohesive dark palette on first load across all Forte surfaces and stays consistent when navigating between Forte pages; an explicit Light choice stays light.
- Recovery: no stored preference, blocked storage, or a light result falls back to today's light palette; reverting the stylesheet and standalone-layout edits restores the prior look. No schema or content changes.
- Deployment/external verification: none beyond serving updated assets; a visual walk of every Forte page in light and dark on a local instance.
- Release condition: visual check passes on all Forte pages for Auto/dark system, Auto/light system, Light, Dark, and one dark named theme; no light patches or paint flash; existing Forte, theme, and presentation tests pass.

## Key Risks

- **Theme resolution drift:** Forte could disagree with the main layout. Early validation: compare resolved result across the five theme cases on both layouts. Mitigation: pass the registry's theme→mode data and the site's storage/default settings into the standalone layout instead of re-deriving them.
- **Flash of light palette:** Early validation: throttled reload in a dark theme. Mitigation: resolve in the document head before stylesheets paint, as the main layout does.
- **Non-token colors leave light patches:** Early validation: walk each page in dark. Mitigation: Stage 3 inventories hard-coded colors and routes them through palette tokens.
- **Leakage to non-Forte pages:** Early validation: render a non-Forte page before and after. Mitigation: all dark rules keyed to the Forte window scope only.

## Stage 1
- Goal: Forte pages know, before first paint, whether the viewer resolves to dark or light.
- Dependencies: none.
- Expected changes: the standalone renderer passes the theme→mode map, storage key, default theme, and permitted themes to the standalone layout; the layout head resolves the saved choice (falling back to default, then system preference) and marks the document with a light/dark scheme value; the page's declared color-scheme becomes "light dark". About 30 lines across `TemplateRenderer` and `standalone_layout.php`.
- Verification approach: render a Forte page and check the marker for each of the five theme cases; run the Forte reader and theme tests; confirm the main layout is unchanged.
- Risks or open questions:
  - Impact: storage key or default differs between site profiles, so resolution is wrong on some sites.
  - Early warning / validation: check the marker under at least two site profiles.
  - Mitigation: read both values from the active profile, as the main layout does.
- Canonical components/API contracts touched: `TemplateRenderer::renderStandalonePage`, `templates/standalone_layout.php`, `ThemeRegistry` (read-only), `SiteProfileRegistry` (read-only).

## Stage 2
- Goal: a cohesive dark palette applies to the Forte window chrome and main panes when the scheme is dark.
- Dependencies: Stage 1.
- Expected changes: add a dark override of the `--paned-*` tokens and body background in `public/assets/forte.css`, keyed to the dark scheme marker; set the dark color-scheme so native controls and scrollbars follow. Palette keeps the windowed chrome (bevels, title bar) in muted, low-glare tones. About 30 lines.
- Verification approach: view the board/thread page in dark and light; confirm light is pixel-identical to before; check text contrast on primary surfaces.
- Risks or open questions:
  - Impact: bevel highlights and shadows lose their raised look when simply darkened.
  - Early warning / validation: view toolbar, pane borders, and buttons side by side with light.
  - Mitigation: tune light/dark border tokens separately instead of inverting.
- Canonical components/API contracts touched: `public/assets/forte.css` palette tokens; `paned_*` partials (read-only).

## Stage 3
- Goal: no light patches remain anywhere in dark.
- Dependencies: Stage 2.
- Expected changes: list hard-coded colors in `forte.css` (about 20) and any in `activity.css` that show on Forte pages; route each through a palette token or give it a dark value; cover inputs, dialog, selection, focus, links, embeds, and reaction states. About 30 lines.
- Verification approach: walk board, thread with replies, compose panel and dialog, user detail pane, activity, profile, username, and user directory in dark; inspect for light regions and unreadable text.
- Risks or open questions:
  - Impact: inline styles or embedded content (media embeds, avatars) stay light.
  - Early warning / validation: inspect computed backgrounds on embed cards and images.
  - Mitigation: scope dark overrides for embeds to Forte's container only.
- Canonical components/API contracts touched: `public/assets/forte.css`, `public/assets/activity.css`, `templates/pages/forte_*.php` (read-only unless an inline style is found).

## Stage 4
- Goal: confirm release condition and lock behavior with tests.
- Dependencies: Stages 1-3.
- Expected changes: add a test asserting the scheme marker and standalone head output for the theme cases; no other code changes expected beyond fixes found in the walk-through.
- Verification approach: full five-case walk of every Forte page in light and dark; flash check on throttled reload; non-Forte page spot check; run the Forte, theme, and presentation test files.
- Risks or open questions:
  - Impact: a case missed in Stages 1-3 surfaces late.
  - Early warning / validation: the five-case matrix is recorded in the Step 4 summary.
  - Mitigation: fix in place; return to Step 2 if scope grows beyond the approved boundary.
- Canonical components/API contracts touched: `tests/ForteBoardReaderTest.php`, `tests/ThemeRegistryTest.php`, `tests/PresentationProfileMatrixTest.php` (run; extend only the Forte test).
