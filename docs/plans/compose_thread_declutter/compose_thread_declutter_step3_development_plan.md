> **Feature plan:** [Step 1](./compose_thread_declutter_step1_solution_assessment.md) · [Step 2](./compose_thread_declutter_step2_feature_description.md) · [Step 3](./compose_thread_declutter_step3_development_plan.md) · [Step 4](./compose_thread_declutter_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Normal entry: open `/compose/thread`; also board, QDB add, paned new-thread dialog, and reply forms for the image widget.
- Outcome: the page shows a short title, the body, collapsed "Subject & tags" and "Event" expandos, a compact image row, one primary button, and a "..." menu holding all other action buttons. Posts store identically to before.
- Recovery: expandos open for non-default values, errors, and normalization warnings; image failure/retry/remove flows still work on every surface.
- Deployment/external verification: none beyond existing local test suites and a manual browser pass; no schema, API, or data changes. Release condition: all stages verified and existing compose, signing, and image tests pass.
- Out of scope: reply-form action buttons (only its image widget changes).

## Key Risks
- **High risk:** shared image partial and `image_upload.js` change on five surfaces. Validate by running image browser tests (`tests/browser/image_*`) and `ImageContentTest` per stage; mitigate by keeping existing class/`name` hooks and changing visibility only.
- **High risk:** hidden warnings. `browser_signing.js` toggles `[data-role="compose-field-normalization-status"]` nodes inside collapsed sections. Validate with a browser test typing an unsupported character; mitigate with a script that opens the parent expando when such a node becomes visible.
- Script coupling: `browser_signing.js` finds `[data-action="clear-compose-fields"]`, `submit-anonymous-compose` (via submitter), and status nodes by selector under the form. Mitigate by keeping the menu and expandos inside the `<form>` and preserving attributes.
- Menu discoverability and accidental keyboard traps: use native `<details>` semantics, close on action/Escape.

## Stage 1
- Goal: trim the page header.
- Dependencies: none.
- Expected changes: `compose_thread.php` drops the "Posts are stored…" paragraph and shows one short friendly line; visible heading shortened, with a visually hidden accessible title kept.
- Verification approach: render test/`LocalAppSmokeTest` asserts old text gone and title present.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `templates/pages/compose_thread.php`.

## Stage 2
- Goal: tags/subject expando under the body.
- Dependencies: Stage 1.
- Expected changes: in non-compact `thread_compose_form.php`, reorder body above a `<details>` wrapping tags and subject (inputs, names, status paragraphs unchanged); server-side open when tags ≠ `general`, subject non-empty, or error present; summary shows current values.
- Verification approach: PHP render tests for closed default, open on subject/tags/error; confirm posted fields unchanged.
- Risks or open questions:
  - Impact: tests or scripts relying on field order.
  - Early warning / validation: run `LazyComposeSigningTest`, `OfflineOutboxComposeTest`.
  - Mitigation: keep names and `data-compose-field-*` hooks.
- Canonical components/API contracts touched: `thread_compose_form.php`, `compose.css`.

## Stage 3
- Goal: event expando.
- Dependencies: Stage 2.
- Expected changes: wrap the four event inputs in a `<details>`, open when any event value or error exists; hidden entirely when events are disabled.
- Verification approach: render tests for disabled, empty, and prefilled.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `thread_compose_form.php`.

## Stage 4
- Goal: client behavior for expandos.
- Dependencies: Stages 2–3.
- Expected changes: small script (added to the existing compose asset set) that opens a `<details>` when a normalization-status node inside it becomes visible and refreshes the collapsed summary on input/clear.
- Verification approach: browser test with an unsupported character in subject; clear-fields resets summary.
- Risks or open questions:
  - Impact: script not loaded on some surfaces.
  - Early warning / validation: check where compose scripts are included.
  - Mitigation: load alongside `compose_draft_clear.js`.
- Canonical components/API contracts touched: `public/assets/` compose scripts; asset versioning.

## Stage 5
- Goal: compact image widget on all surfaces.
- Dependencies: none (can precede 2–4).
- Expected changes: `image_compose_fields.php` becomes a one-row "Add image" control; description input and status shown only when an image is attached; the always-visible help message ("Images are resized on your device… reselect after reloading.") is removed; `image_upload.js` `render()` toggles description visibility; `compose.css` image rules slimmed.
- Verification approach: run `ImageContentTest`, `tests/browser/image_compose_transport.cjs`, `image_prepare.mjs`; manual select/replace/remove on each surface.
- Risks or open questions:
  - Impact: description not submitted when hidden or recovery state broken.
  - Early warning / validation: hidden-description submit test.
  - Mitigation: keep input in DOM, only visually hidden.
  - Impact: removing the help text drops the "reselect after reloading" hint. Early warning / validation: confirm the separate recovery notice and status line still explain unavailable-upload and failure states. Mitigation: keep those messages; add no replacement help text.
- Canonical components/API contracts touched: `image_compose_fields.php`, `image_upload.js`, `compose.css`.

## Stage 6
- Goal: "..." overflow menu for thread-composer actions.
- Dependencies: Stage 2.
- Expected changes: in `thread_compose_form.php`, a `<details>` menu inside `.compose-form-actions` containing "Create thread anonymously", "Clear fields", and quote-only "Remove timestamps"; primary submit stays outside; CSS for menu, 44 px targets, close on action/Escape.
- Verification approach: tests that anonymous submit still signs anonymously, clear works, quote cleanup works; keyboard pass.
- Risks or open questions:
  - Impact: `submitter.dataset.action` lost or form validation bypass.
  - Early warning / validation: `LazyComposeSigningTest` anonymous cases.
  - Mitigation: keep buttons as form descendants with unchanged attributes.
- Canonical components/API contracts touched: `thread_compose_form.php`, `compose.css`, `browser_signing.js` (read-only check).

## Stage 7
- Goal: cross-surface verification and closeout.
- Dependencies: Stages 1–6.
- Expected changes: add/adjust tests for open rules and menu; update stale assertions; mark `todo.txt` only for items verified done.
- Verification approach: full PHP test suite and browser tests; manual pass on compose, board, QDB add, paned dialog, reply, and mobile width.
- Risks or open questions:
  - Impact: asset hash/versioning or static artifacts stale.
  - Early warning / validation: run `check_static_artifacts.php`.
  - Mitigation: regenerate per repo convention.
- Canonical components/API contracts touched: tests, `todo.txt`.
