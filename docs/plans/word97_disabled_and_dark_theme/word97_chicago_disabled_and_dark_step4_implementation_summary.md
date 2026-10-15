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
