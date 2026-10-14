> **Feature plan:** [Step 1](./compose_thread_declutter_step1_solution_assessment.md) · [Step 2](./compose_thread_declutter_step2_feature_description.md) · [Step 3](./compose_thread_declutter_step3_development_plan.md) · [Step 4](./compose_thread_declutter_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Trim the page header
- Changes:
  - `templates/pages/compose_thread.php`: heading "Compose Thread" → "New thread"; replaced the "Posts are stored as canonical text files…" paragraph with one short line.
  - `tests/Support/ProfileRegressionContract.php`: compose marker updated to the new heading.
- Verification:
  - `php tests/run.php LocalAppSmokeTest PresentationProfileMatrixTest`: 140 run, 140 passed.
- Notes:
  - The document `<title>` still reads "Compose Thread" (from `QdbExperience`), which serves as the accessible page title, so no hidden heading was added.

## Stage 2 - Tags/subject expando
- Changes:
  - `templates/partials/thread_compose_form.php`: non-compact form now renders the body first, then a `<details data-compose-disclosure="tags-subject">` wrapping board tags and subject with unchanged names and status hooks. Opens server-side when tags ≠ `general`, subject is non-empty, or an error is present; summary shows current subject and tags.
  - Body normalization-status paragraph now sits directly under the body in both variants.
  - `public/assets/compose.css`: shared `.compose-disclosure` styling.
  - `tests/LocalAppSmokeTest.php`: closed-by-default and open-on-subject/open-on-tags assertions.
- Verification:
  - `php tests/run.php LocalAppSmokeTest PresentationProfileMatrixTest LazyComposeSigningTest OfflineOutboxComposeTest BrowserSigningNormalizationTest`: 225 run, 225 passed.
- Notes:
  - Query-string and quote-supplied values are covered by the same non-default rule; the error open path is not yet covered by a test and the normalization-warning open path arrives in Stage 4.
  - Event inputs are still visible; Stage 3 wraps them.

## Stage 3 - Event expando
- Changes:
  - `templates/partials/thread_compose_form.php`: the four event inputs sit in `<details data-compose-disclosure="event">` (only when event support is enabled). Opens when any event value is present or an error is shown; summary lists date/time/location.
  - `tests/LocalAppSmokeTest.php`: closed-by-default and open-when-prefilled assertions with events enabled.
- Verification:
  - `php tests/run.php LocalAppSmokeTest PresentationProfileMatrixTest LazyComposeSigningTest OfflineOutboxComposeTest`: 145 run, 145 passed.
- Notes:
  - The disabled-events case is covered by the existing `name="event_date"` absence assertions.
  - Warning auto-open and live summary updates come in Stage 4.

## Stage 4 - Expando client behavior
- Changes:
  - `public/assets/compose_disclosure.js` (new): opens a disclosure when a normalization-status node inside it becomes visible, refreshes the collapsed summary on input, and refreshes after "Clear fields".
  - `templates/layout.php` / `TemplateRenderer.php`: load the script next to `compose_draft_clear.js` on every page, so it covers all compose surfaces.
  - `tests/browser/compose_disclosure.mjs` (new, self-contained fixture) and a fingerprinted-asset assertion in `LocalAppSmokeTest`.
- Verification:
  - `php tests/run.php LocalAppSmokeTest PresentationProfileMatrixTest VersionCheckBehaviorTest`: 146 run, 146 passed.
  - `CHROMIUM_PATH=<playwright chromium> node tests/browser/compose_disclosure.mjs`: ok (summary update, warning auto-open, summary after clear).
- Notes:
  - Browser test uses a fixture, not the real `browser_signing.js` warning flow; the real flow toggles the same `hidden` attribute on the same nodes, but an end-to-end check with an unsupported character is left for Stage 7's manual pass.
  - The script is loaded globally (layout) rather than per page, matching how the draft-clear script is loaded.

## Stage 5 - Compact image widget
- Changes:
  - `templates/partials/image_compose_fields.php`: one-row widget (choose + remove buttons, small preview, status line); the border, "or drop it here" text, and the always-visible help message are gone; the legend is visually hidden; the description input is wrapped in a label hidden until an image is attached (the input stays in the DOM).
  - `public/assets/image_upload.js`: `render()` shows the description only while an image state exists.
  - `public/assets/compose.css`: slimmed image styles (no fieldset border/padding, smaller preview, drop highlight kept).
  - User request mid-stage: all "(optional)" labels removed from the thread form, event fields, and image widget; the intro line no longer says "optional".
  - `tests/browser/image_content_browser.mjs`: opens the Subject & tags expando before filling subject and fills the description after the image is attached.
- Verification:
  - `node tests/browser/image_content_browser.mjs` (Chromium): passed after the two test adjustments above.
  - `node tests/browser/image_compose_transport.cjs`: ran clean.
  - `php tests/run.php ImageContentTest LocalAppSmokeTest PresentationProfileMatrixTest LazyComposeSigningTest`: 147 run, 147 passed.
- Notes:
  - The widget markup is shared, so reply, board, QDB add, and paned dialogs get it automatically; visual checks per surface are in Stage 7.
  - Description text is preserved (hidden) if the image is removed and is only read by signing when an image is attached.
  - Removing the help message also removed the "reselect after reloading" hint, as approved; failure/recovery messages are unchanged.
