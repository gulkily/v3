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

## Stage 3 - Server alias
- Changes:
  - `TemplateRenderer::themeHint` maps a `theme-hint` cookie of `windark` to `midnight` through `ThemeRegistry::LEGACY_ALIASES`, so the first stylesheet is `theme-midnight.css`.
  - `ThemeRegistryTest` asserts each legacy alias is no longer a known name and points to a current explicit theme.
- Verification:
  - `php tests/run.php LocalAppSmokeTest ThemeRegistryTest`: passed (152 run, 152 passed); the new `windark` cookie assertion fails with the renderer change stashed.
- Notes:
  - No cookie rewrite on the server; the existing client hint update rewrites it on the next page load.

## Stage 4 - Browser verification, docs and todo
- Changes:
  - `AGENTS.md` and the two historical `todo.txt` lines now say Midnight; a removal todo for the alias was appended to `todo.txt`.
  - The stage commit also carries a one-line `todo.txt` note ("qr codes should match theme within reason") that the user had added and left uncommitted.
- Verification:
  - Browser (Chromium, 1100px): Tools page (header nav, subnavigation and launcher buttons) rendered for Midnight, Word97 and Chicago on this branch and on `main` (Midnight stored as `windark` there); full-page screenshots are byte-identical in all three themes, and icons are visibly present in the Midnight screenshot.
  - `git grep -i windark` outside `docs/plans`: only the alias, its two tests, and the new removal todo.
  - Full PHP suite `php tests/run.php`: 1320 run, 1316 passed, 4 failed. All four reproduce without this change: `AgentIdentityServiceTest` and `ReadModelBuilderTimingTest` fail identically on `main`; `WriteApiSmokeTest::testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence` failed the same way on unchanged Stage 2 code earlier; `PrivateMessageHistorySyncTest::testConcurrentSyncWritesLeaveForegroundMessageStoreResponsive` is flaky (passes and fails on both `main` and this branch across three alternating runs).
  - Not run: other browser suites that need external servers (for example `bookmarklet_copy_ui.mjs`, which iterates every theme option); other Tools pages and states where a different nav item is active; physical devices.
- Notes:
  - Alias lives in `ThemeRegistry::LEGACY_ALIASES`, `layout.php`, `theme_toggle.js`, and `TemplateRenderer::themeHint` until the todo is done.
