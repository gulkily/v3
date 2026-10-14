> **Feature plan:** [Step 1](./bookmarklet_one_line_copy_step1_solution_assessment.md) · [Step 2](./bookmarklet_one_line_copy_step2_feature_description.md) · [Step 3](./bookmarklet_one_line_copy_step3_development_plan.md) · [Step 4](./bookmarklet_one_line_copy_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Icon-only copying

- Changes: Added seven icon-only copy buttons with names/tooltips, keyboard focus, transient accessible feedback, and retry. Link and clipboard share a single encoded JavaScript URL; URL encoding preserves source and content without unsafe whitespace stripping.
- Verification: `BOOKMARKLET_BASELINE=/tmp/bookmarklet-baseline.js node tests/browser/bookmarklet_copy.cjs` passed all seven variants against original behavior, including quotes, percent signs, Unicode, content newlines, attribution, window modes, failed writes, and focus. The targeted PHP bookmarklet smoke test passed (1/1). Chromium live Tools → Bookmarklets pointer and Enter activation both copied the exact link source. qutebrowser 2.5.0 launched in an isolated temporary profile with a local debugging endpoint.
- Notes: Planning commit `02dfe203`; no production deployment. Stage 2 will verify actual qutebrowser binding execution and clipboard fallback. Its older QtWebEngine rejects Playwright context management, so acceptance will use its native commands and page debugging protocol.

## Stage 2 - Recovery and browser acceptance

- Changes: Added a temporary off-screen clipboard fallback for unavailable or rejected Clipboard API writes, guaranteed cleanup, and retry feedback. Kept the empty live region accessible. Fixed theme/mobile stretching so the icon remains small beside its link. Added rendered-control smoke assertions and Chromium/qutebrowser acceptance scripts with an isolated PHP fixture.
- Verification: Targeted PHP smoke test passed (1/1); `node tests/browser/bookmarklet_copy.cjs` passed all seven source/behavior cases. Chromium verified actual clipboard values, keyboard activation, denied/unavailable clipboard paths, fallback success/failure, retry, focus restoration, and removal of temporary fields. Both live rendering and the regenerated anonymous static page passed the UI suite (static route marker confirmed). Layout checks passed all 13 available theme options at 375px and 1280px; mobile screenshot inspected.
- Verification: qutebrowser 2.5.0 / QtWebEngine 5.15.9 (Chromium 87), running under Xvfb in a fresh isolated profile, passed all seven entries. Each test clicked Copy, read the actual system clipboard through Qt, compared it with the link, saved that literal value in a `jseval --url` binding, invoked the binding, and checked Compose Thread parameters and form content. Same-window and new-window behavior both passed.
- Notes: Existing Compose Thread template indentation and client punctuation normalization remain unchanged; tests assert exact bookmarklet URL content separately from normalized form text. An earlier long-lived Qt test session crashed; the final fresh-profile run completed all seven cases successfully. No application/runtime API, schema, or production deployment changes.

## Reproducing verification

- Run `php tests/run.php LocalAppSmokeTest::testToolsPageRendersBookmarkletsAndComposeThreadAcceptsPrefills` and `node tests/browser/bookmarklet_copy.cjs`.
- Create a temporary directory and export `BOOKMARKLET_TEST_ROOT` to it; set `FORUM_APPROVED_MEMBERS_ONLY=false`. Run `php tests/Support/bookmarklet_browser.php`, then serve it with `php -S 127.0.0.1:18765 tests/Support/bookmarklet_browser.php`. Run `node tests/browser/bookmarklet_copy_ui.mjs` for live UI checks.
- Run `php tests/Support/bookmarklet_browser.php static` to generate and activate the fixture's local static release. Run `BOOKMARKLET_EXPECT_STATIC=1 node tests/browser/bookmarklet_copy_ui.mjs`; this requests anonymous HTML without background-session cookies and asserts the static route marker before testing controls.
- Start qutebrowser on an X display with a fresh temporary `--basedir`, `QTWEBENGINE_REMOTE_DEBUGGING=18766`, and the fixture's Tools URL. Export that directory as `QUTE_TEST_BASEDIR`, keep the same `DISPLAY`, and run `node tests/browser/bookmarklet_copy_qutebrowser.mjs`. The script requires Node WebSocket support and system Python with PyQt5. It changes only the disposable browser profile's test binding.

## Follow-up - Stable feedback and descriptions below controls

- Changes: Positioned transient copy feedback above the controls as an overlay so it never occupies layout space. Stacked each bookmarklet description below its controls at every width, using a bookmarklet-specific class to preserve other Tools layouts.
- Verification: Browser geometry checks across all 13 available themes at 375px and 1280px confirmed every description is below its controls and every item's position/size stays identical for success, failure, and cleared feedback. Actual clipboard copying passed; mobile feedback screenshot inspected. PHP template syntax and `git diff --check` passed.
- Notes: Implements the user's requested layout refinements; clipboard behavior is unchanged.

## Follow-up - Feedback to the right

- Changes: Moved copy feedback to the right of the controls, vertically centered. Reserved horizontal room at narrow widths so the overlay remains on-screen without shifting the description or neighboring entries.
- Verification: Browser geometry checks passed all 13 available themes at 1280px, 375px, and 320px: success/failure feedback appeared to the right, stayed within the viewport, and left item positions and dimensions unchanged. `git diff --check` passed.
