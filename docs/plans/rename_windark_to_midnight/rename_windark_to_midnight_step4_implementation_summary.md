> **Feature plan:** [Step 1](./rename_windark_to_midnight_step1_solution_assessment.md) · [Step 2](./rename_windark_to_midnight_step2_feature_description.md) · [Step 3](./rename_windark_to_midnight_step3_development_plan.md) · [Step 4](./rename_windark_to_midnight_step4_implementation_summary.md)

Debug mode: enabled

# Rename the windark theme to midnight: Step 4 implementation summary

Branch: `rename_windark_to_midnight` (from `main`). Planning commit: `a5e1a150`.

## Enhancement Requests
- Request: add a todo to remove the alias later / Disposition: in scope, done in Stage 4 / Outcome: pending.

## Stage 1 - Mechanical rename
- Changes:
  - `windark` became `midnight` in `ThemeRegistry`, `SiteProfileRegistry`, `theme-nav-icons.css`, and the two tests; `theme-windark.css` was renamed with `git mv` to `theme-midnight.css` and its selectors updated.
- Verification:
  - `php tests/run.php LocalAppSmokeTest InvitationIssuanceTest`: passed (151 run, 151 passed).
  - `git grep -i windark` outside `docs/plans`: only `AGENTS.md` and `todo.txt` wording remain, scheduled for Stage 4.
- Notes:
  - A user-edited, uncommitted `todo.txt` line ("qr codes should match theme…") is excluded from stage commits until Stage 4 touches that file.
  - Saved `windark` values are not yet handled; Stages 2 and 3 add the alias, so this commit alone would reset Midnight users to the default.

## Stage 2 - Browser alias
- Changes:
  - `ThemeRegistry::LEGACY_ALIASES` (`windark` to `midnight`) is the single legacy-name list; `TemplateRenderer` passes it to the layout.
  - `layout.php` maps a stored legacy theme to its replacement before validation and rewrites localStorage, so the first paint is Midnight and the `theme-hint` cookie is refreshed by the existing hint update; `theme_toggle.js` applies the same map when reading the stored value.
  - `image_content_browser.mjs` preloads `windark` and asserts `data-theme="midnight"`, stored `midnight`, and cookie `midnight`.
- Verification:
  - `IMAGE_PICKER_ONLY=1` and default `node tests/browser/image_content_browser.mjs`: passed; the new assertion fails (`data-theme` null) when the `layout.php` change is stashed.
  - `php tests/run.php LocalAppSmokeTest`: passed (142 run, 142 passed).
- Notes:
  - The alias test lives in the image browser test only because it already provides a running server and signed-in context; it is not about images.
  - The `theme_toggle.js` branch is covered only indirectly, since `layout.php` rewrites the value first.
