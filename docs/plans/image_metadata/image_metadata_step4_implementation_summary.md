# Photo details Step 4 implementation summary

> **Feature plan:** [Step 1](./image_metadata_step1_solution_assessment.md) · [Step 2](./image_metadata_step2_feature_description.md) · [Step 3](./image_metadata_step3_development_plan.md) · [Step 4](./image_metadata_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/image_metadata`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled. Stage estimates below are copied from the approved Step 3 plan; Claude single exposed rater throughout.

**Cycle estimate: 0.50**

Branch: `feature/image-metadata`, from `origin/main` at `14beeaaa`. Planning commit `d8e0060`.

## Study snapshots

- Step 4 entry, 2026-11-15 UTC: 0.50, Step 3 reaffirmed unchanged. Written after the planning commit and before Stage 1 code.

## Stage 1 - Read photo details in the browser

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes:
  - `public/assets/image_prepare.js`: a bounded EXIF reader finds the EXIF block in JPEG (APP1), PNG (`eXIf`) and WebP (`EXIF`) sources. It reads IFD0, the Exif IFD and the GPS IFD in either byte order.
  - `ForumImagePrepare.prepare` now returns `details`: `{key, label, value, display, line}` for Camera, Lens, Exposure, Taken and Location. `line` is the signed text, such as `Photo-Taken: 2026-11-14T16:12:08-05:00`.
  - Guards: 512 entries per IFD, 4096 values per tag, a visited-offset set against loops, bounds checks on every read, control characters folded to spaces, 100 characters per text value, and a catch that returns no details.
  - The prepared file still carries no metadata.
- Verification:
  - Passed: `node tests/browser/image_prepare.mjs` (Chromium). The new case covers big-endian JPEG, little-endian PNG, no EXIF, truncated, looping, oversized IFD count, and a newline inside a value.
  - Passed: `./v3 test` smoke set, 464/464. The baseline before any change was also 464/464.
  - Passed: `python3 -I tests/frm_client_test.py`; it only mentions `image_prepare.js` in a docstring.
  - Not run: WebKit (`IMAGE_BROWSER=webkit`); the iPhone replay in Stage 4 covers Safari.
- Notes:
  - Reuse: the new reader sits beside the existing `dimensions` marker walk in the same file. No third-party library.
- Estimate details: original scope as Step 3; uncertainty: low; revisions: none.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Stage 2 - Choose details at attach time and sign them

**Original estimate: 0.20**

**Retrospective stage work: 0.22** (one rendering fix and a secure-context test harness)

- Changes:
  - `templates/partials/image_compose_fields.php`: a `details.compose-disclosure` with `data-compose-disclosure="photo"` under the preview, hidden until a prepared photo has details. Every composer gets it through this partial.
  - `public/assets/image_upload.js`: builds one unticked checkbox per detail, with the value in plain words and a warning on Location. Collapses again for each new photo. New `ForumImageUpload.chosenLines(form)`.
  - `public/assets/compose_disclosure.js`: the `photo` kind summarises ticked labels, like Options.
  - `public/assets/browser_signing.js`: `imageSignedFields(fields, image, description, photoLines)` checks each line against the five known keys and adds them after `Image-v1:`. Thread, board and reply submits pass the chosen lines.
  - `public/assets/reply_continuation.js`: the reply item stores `photoLines` in its saved record, and every prepare, including Retry, sends them.
  - `public/assets/compose.css`: row styles for the checklist.
- Verification:
  - Passed: new `node tests/browser/image_photo_details.mjs` (Chromium). It covers collapsed and unticked by default, the Location warning, the summary, chosen lines, a photo without metadata, and a reply whose first prepare fails then retries with the same lines.
  - Passed: `node tests/browser/image_compose_transport.cjs`, extended. Nothing ticked still gives today's body; two ticked lines follow `Image-v1:`; an injected newline or unknown key is refused.
  - Passed: `node tests/browser/compose_disclosure.mjs` and `node tests/browser/image_prepare.mjs`.
  - Passed: `./v3 test` smoke set, 464/464.
  - Checked by screenshot: Gloss, Light, Dark and Word97 at 390 and 1280 pixels, collapsed and open. Values first rendered bold through theme label styles; fixed in `compose.css`.
  - Not run: WebKit and a live server upload; Stage 4 covers the iPhone replay.
- Notes:
  - Deviation from the Step 3 wording: the reply item stores the final lines, not the detail keys. The stored attachment is the prepared file, which has no metadata, so keys could not be resolved again on retry. The lines are already validated text.
  - Reuse: the existing compose disclosure, its summary hook and the image partial. No new composer or disclosure style.
- Estimate details: original scope as Step 3; uncertainty was the reply retry path, now covered by a test; revisions: none.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
