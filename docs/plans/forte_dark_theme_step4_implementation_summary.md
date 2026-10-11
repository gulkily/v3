> **Feature plan:** [Step 1](./forte_dark_theme_step1_solution_assessment.md) · [Step 2](./forte_dark_theme_step2_feature_description.md) · [Step 3](./forte_dark_theme_step3_development_plan.md) · [Step 4](./forte_dark_theme_step4_implementation_summary.md)

## Stage 1 - Resolve Forte color scheme before paint
- Changes:
  - `templates/standalone_layout.php`: head script resolves the saved theme (stored choice, else site default, else system preference) and sets `data-forte-scheme="dark|light"` on `<html>`; declared color-scheme is now `light dark`.
  - `TemplateRenderer::renderStandalonePage` passes the theme storage key, default theme, and theme→mode map to the layout.
  - `TemplateRenderer`: extracted `explicitThemeNames()` and `defaultThemeName()` helpers, now shared by `renderLayout` and `renderStandalonePage` so both resolve from the same rules.
- Verification:
  - Rendered a standalone page and ran its head script in node for seven cases: Auto/dark OS → dark, Auto/light OS → light, Light on dark OS → light, Dark on light OS → dark, Console → dark, Whitehot on dark OS → light, stored `auto` on dark OS → dark.
  - `php tests/run.php ForteBoardReaderTest ThemeRegistryTest ProfileThemePresentationTest PresentationProfileMatrixTest TemplateRendererMediaEmbedsScriptTest`: 21 run, 21 passed.
  - Full `tests/run.php` reports 8 failing tests in unrelated areas (feature-flags page, offline snapshot, QDB, agent reply); the run history shows them failing since 2026-10-09/10, before this branch. They were not re-run on a baseline checkout.
- Notes:
  - The marker has no visual effect yet; Stage 2 consumes it. Stage 2 must also set the root color-scheme from the marker so a Light choice on a dark OS does not get a dark canvas from the new `light dark` meta.
  - Verified under the default profile only; a second-profile check remains for Stage 4.

## Stage 2 - Dark palette for window chrome and main panes
- Changes:
  - `public/assets/forte.css`: appended a dark-scheme section keyed to `:root[data-forte-scheme]`. It sets root and body `color-scheme`, a dark body background, and dark values for every `--paned-*` token. Border light/dark tokens are a tuned highlight/shadow pair so bevels still read as raised or sunken.
  - A `data-forte-scheme="light"` rule pins the root to `color-scheme: light`, resolving the Stage 1 note about the `light dark` meta.
- Verification:
  - Chrome headless screenshots of `/forte` on a local dev server: dark OS with Auto → marker `dark`, dark palette; light OS with Auto → `light`, original look; Light stored on a dark OS → `light`, original look.
  - Contrast ratios of the new tokens: ink on chrome 12.1, ink-soft on chrome 6.4, ink on content 14.2, ink-soft on content 7.5, select text on select background 6.7, white on title bar 12.0 (all above WCAG AA 4.5).
  - Only the board page with no thread selected was viewed; the other Forte pages and surfaces are Stage 3.
- Notes:
  - Light rules are untouched, so the light palette is unchanged by construction.
  - Hard-coded colors and the `color-scheme: light` declarations on inputs and dialogs remain; Stage 3 handles them.

## Stage 3 - Route remaining colors through the palette
- Changes:
  - `public/assets/forte.css`: new tokens `--paned-hover-bg`, `--paned-highlight-bg`, `--paned-highlight-edge`, and `--paned-link`, with light values equal to the previous hard-coded colors and dark values in the dark section. Row/folder hover, new-reply highlight, and the summary-dialog footer link now use them.
  - Removed five `color-scheme: light` declarations on compose inputs and dialogs so native controls follow the page scheme.
  - Added `:root:not([data-forte-scheme])` to the light root rule, so a page with no marker (script blocked or failed) stays light even on a dark OS.
  - Left as-is: the amber focus outline (`#f2b705`) and the agent badge text (`#dfe8ff`), both legible on dark; the dialog backdrop and title-bar text are neutral.
- Verification:
  - Chrome headless walk in dark (dark OS, Auto): board, thread with reply and reaction buttons, New Thread dialog with inputs, users, activity with detail pane, and profile. No light patches or unreadable text seen.
  - Light values equal the previous literals, so light rendering is unchanged by construction.
  - `activity.css` needs no change; it uses `--line` through `color-mix` and renders correctly under dark.
- Notes:
  - Not exercised visually: media-embed card inside a Forte thread, the content-summary dialog, username page, and reply-composer states. These stay on the Stage 4 walk.

## Stage 4 - Verify and lock behavior with tests
- Changes:
  - `tests/ForteBoardReaderTest.php`: added `testStandaloneLayoutResolvesColorSchemeFromSavedThemeOrSystem`, which renders a standalone page, runs its head script in node, and asserts the marker for seven cases (Auto on dark/light system, Light on dark system, Dark on light system, a dark named theme, a light named theme on a dark system, and an unknown stored theme).
  - No other code changes were needed after the Stage 3 walk.
- Verification:
  - `php tests/run.php ForteBoardReaderTest ForteActivityReadModelRecoveryTest ThemeRegistryTest ProfileThemePresentationTest PresentationProfileMatrixTest TemplateRendererMediaEmbedsScriptTest`: 24 run, 24 passed.
  - Theme matrix on a live local instance (default profile): Auto on dark OS → dark; Auto on light OS → light; Light stored on dark OS → light. Second profile (`FORUM_SITE_ID=mitrapclub`, default theme dark): no stored theme on a light OS → dark; stored Light on dark OS → light; stored Whitehot on dark OS → light.
  - Script-disabled load on a dark OS renders the original light palette.
  - First paint: the resolver is an inline script in the document head before any stylesheet, so the marker is set before paint; this was not measured on a throttled connection.
  - Non-Forte pages: only Forte controllers use the standalone layout, and `layout.php` is unchanged; its theme helpers were refactored and are covered by the theme presentation tests above.
  - Full `tests/run.php` still lists the 8 unrelated failures noted in Stage 1; they were not re-run on a baseline checkout.
- Notes:
  - Not visually exercised in dark: a media-embed card inside a Forte thread, the content-summary dialog, and the username page. They use the same tokens, but they were not inspected.
  - Release condition met for the surfaces walked (board, thread, new-thread dialog, users, activity, profile); the three above remain a follow-up check.
