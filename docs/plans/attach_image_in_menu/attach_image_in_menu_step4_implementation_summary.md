> **Feature plan:** [Step 1](./attach_image_in_menu_step1_solution_assessment.md) · [Step 2](./attach_image_in_menu_step2_feature_description.md) · [Step 3](./attach_image_in_menu_step3_development_plan.md) · [Step 4](./attach_image_in_menu_step4_implementation_summary.md)

Debug mode: enabled

# Attach image in the "…" menu: Step 4 implementation summary

Branch: `attach_image_in_menu`. Planning commit: `0bd7b1c4`.

## Enhancement Requests
- Request: "x" remove button on the selected image / Disposition: separate work, recorded in `todo.txt` / Approval: not requested for this cycle / Outcome: deferred.
- Request: leave the `…` button position unchanged / Disposition: out of scope / Outcome: unchanged.

## Stage 1 - Shared menu partial
- Changes:
  - New `templates/partials/compose_action_menu.php` renders the `…` menu around supplied items.
  - `thread_compose_form.php` builds its items and renders them through the partial; markup is otherwise identical.
- Verification:
  - `php tests/run.php LocalAppSmokeTest`: passed (142 run, 142 passed), including the existing `compose-action-menu` markup assertion.
  - `node tests/browser/image_content_browser.mjs`: passed (all listed checks).
  - Rendered markup diff: covered by the smoke-test assertion; no separate byte-level diff was captured.
- Notes:
  - `todo.txt` has an unrelated uncommitted entry (the x button item) and is excluded from this commit.

## Stage 2 - Attach image in the thread menu
- Changes:
  - Thread compose renders a hidden `Attach image` entry (`.image-upload-choose`) in the `…` menu; `image_compose_fields.php` omits its own button when `imageChooseInMenu` is set, and otherwise renders it captioned `Attach image`.
  - `image_upload.js` finds the entry from the form, hides it until the member is eligible, disables it while busy or ineligible, labels it `Replace image` once an image is attached, and returns cancel focus to the `…` toggle when the menu is closed.
  - Browser test opens `…` before using the entry and asserts it is hidden when ineligible and shown when eligible.
- Verification:
  - `node tests/browser/image_content_browser.mjs`: passed (all listed checks, including the 390px 44px target check and picker cancel).
  - `php tests/run.php LocalAppSmokeTest ImageContentTest`: passed (146 run, 146 passed).
  - Busy-state disabling is not separately asserted in the browser test.
- Notes:
  - Reply forms still render the inline button until Stage 3; the old caption survives only in `image_prepare.js` error text ("Choose an image of at most 32 MiB…"), to be reviewed in Stage 4.

## Stage 3 - Reply form menu
- Changes:
  - `reply_form.php` now renders the shared `…` menu holding Attach image, Post anonymous reply, and Clear fields; the primary submit remains visible. Button types and `data-action` values are unchanged.
- Verification:
  - Selector review: `browser_signing.js` finds these buttons by `data-action`; Forte CSS rules on `.compose-form-actions > button` only affect direct children and the default-button ring targets the primary submit. No selector changes were needed.
  - `node tests/browser/image_content_browser.mjs`: passed, now asserting the Forte reply menu lists Attach image, Post anonymous reply, Clear fields, and no standalone chooser remains.
  - `php tests/run.php LocalAppSmokeTest BrowserSigningNormalizationTest`: passed (223 run, 223 passed).
  - `php tests/run.php WriteApiSmokeTest`: 1 failure (`testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence`), which reproduces identically on unchanged Stage 2 code.
- Notes:
  - Anonymous reply and Clear fields are now one tap further away in reply forms, as approved in Step 2. Reply flows on the thread page and compose-reply page were not clicked through in a browser beyond the Forte pane.
