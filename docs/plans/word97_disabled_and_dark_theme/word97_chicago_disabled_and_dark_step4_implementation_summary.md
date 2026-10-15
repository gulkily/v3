> **Feature plan:** [Step 1](./word97_chicago_disabled_and_dark_step1_solution_assessment.md) · [Step 2](./word97_chicago_disabled_and_dark_step2_feature_description.md) · [Step 3](./word97_chicago_disabled_and_dark_step3_development_plan.md) · [Step 4](./word97_chicago_disabled_and_dark_step4_implementation_summary.md)

Debug mode: enabled

# Step 4: Implementation Summary — Word 97 disabled buttons and dark Win9x theme

## Stage 1 - Word 97 disabled buttons
- Changes:
  - Root cause found by specificity: the base Word 97 button rule (`button:not(.theme-swatch):not(.theme-menu__trigger):not(.theme-menu__option)`, specificity 0,5,1) set `color: #000000` and outranked the disabled rule (`button[disabled]:not(.theme-menu__option)`, 0,4,1), so the gray never applied.
  - `public/assets/theme-word97.css`: the disabled rule now carries the same `:not(...)` chain (0,6,1), so the `#808080` text with white emboss shadow wins.
  - `tests/LocalAppSmokeTest.php`: assertion that the stylesheet contains the strengthened selector.
- Verification:
  - Specificity analysis as above; Chicago stylesheet untouched.
  - `php tests/run.php`: 1212 passed, 1 failed. The failure, `LocalAppSmokeTest::testStartAcceptsPortShorthand` (output lacks `127.0.0.1:18939`), also fails with this stage's changes stashed, so it is unrelated to the CSS change.
  - Browser computed-style check: not run in this stage (headed Chrome under WSLg); deferred to the Stage 5 sweep.
- Notes:
  - Other disabled-looking controls (`.nav-link:disabled` via `opacity: 0.55`, `.thread-reaction-button[disabled]`, forte `.paned-toolbar-btn:disabled`) were left unchanged; the new selector also outranks forte's `:disabled` rule for buttons inside Word 97.

## Stage 2 - Win Dark selectable
- Changes:
  - `ThemeRegistry.php`: `windark` / "Win Dark" (mode `dark`) after Word 97; `SiteProfileRegistry.php`: added to all four profiles' permitted themes.
  - New `public/assets/theme-windark.css`: dark palette variables, swatch, and menu row.
  - `tests/LocalAppSmokeTest.php`: allow-list string and `data-theme-option="windark"` assertion.
  - Blend table (for review before Stage 3):
    - From Chicago: dark-teal desktop background gradient (`--page-bg`), soft rounded-less shell and widened 860px shell.
    - From Word 97: navy active color (brightened for dark), Times body font + Tahoma UI font, bevel/emboss button style, MDI-style title bars and composer bar.
    - Dark-specific: neutral charcoal panels (`#2b2b2b`), black/`#6a6a6a` bevel pair replacing white/gray.
- Verification:
  - `php scripts/build_static_artifacts.php` rebuilt; `php tests/run.php ThemeRegistryTest` 10/10; `SiteProfile` filter passed; `LocalAppSmokeTest` 138/140.
  - The two failures are `testStartAcceptsPortShorthand` (fails on the unmodified baseline) and `testStartAcceptsColonPortShorthand`, the same output-contains-port assertion on local server start; neither touches themes.
- Notes:
  - Stage 2 has no scoped chrome yet, so the theme currently renders base styles with dark variables.
  - Production needs `php scripts/build_static_artifacts.php` after deploy, per the theme guide.

## Stage 3 - Core dark chrome
- Changes:
  - `theme-windark.css`: scoped rules for shell, header, cards, feedback, post-card spacing, body inset, tool-launcher buttons, links, buttons (face, active, focus, disabled), nav links, inputs/key viewers and selection, derived from the Word 97 rules with a dark color mapping (face `#2b2b2b`, bevel `#6a6a6a`/`#000000`, ink `#e6e6e6`, link `#7aa8ff`, disabled `#7a7a7a` with black emboss).
- Verification:
  - Headed-free Chrome (`/opt/google/chrome/chrome`, headless) against `scripts/dev_server.php`: board page in Win Dark renders themed (screenshot reviewed; contrast OK, active nav uses navy).
  - Browser probe of a disabled button: Word 97 computed color `rgb(128,128,128)` (Stage 1 fix confirmed in a real browser), Win Dark `rgb(122,122,122)`.
  - `ThemeRegistryTest` 10/10.
- Notes:
  - Chicago's 860px shell width already matches Word 97's, so no separate Chicago rule was needed.
