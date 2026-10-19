> **Feature plan:** [Step 1](./attach_image_in_menu_step1_solution_assessment.md) · [Step 2](./attach_image_in_menu_step2_feature_description.md) · [Step 3](./attach_image_in_menu_step3_development_plan.md) · [Step 4](./attach_image_in_menu_step4_implementation_summary.md)

Debug mode: enabled

# Attach image in the "…" menu: Step 2 feature description

## Problem
The image chooser is a standalone "Choose an image" button that adds weight to the compose form. Image attachment should be an "Attach image" entry in the `…` (More actions) menu of both the thread and reply compose forms.

## User stories
- As an approved member, I want "Attach image" in the `…` menu so that the compose form stays compact.
- As a reply author, I want the same menu as in thread compose so that actions are in one familiar place.
- As a member on a phone, I want touch-sized menu entries so that attaching is easy.

## Core requirements
- The `…` menu holds Attach image in thread and reply forms (including Forte panes and dialogs). The reply menu also holds Post anonymous reply and Clear fields, matching thread compose; the primary submit stays visible.
- The entry is captioned "Attach image" and opens the existing file picker. Drag-drop, paste, preview, description, Remove image, and 44px touch targets are unchanged.
- The entry appears only when the member is image-eligible, and is disabled while an upload is in progress. Users without image access see no entry and no changed layout.
- Label after attaching: "Replace image" (current behavior preserved). **Assumption, unconfirmed.**
- Recovery: the picker-cancel focus return, the "uploading unavailable" recovery notice, and failure statuses keep working with the entry inside a closed menu.

## Delivery scope and completion boundary
**Work type:** application change. Normal entry: open `…` → Attach image → pick/drop/paste → preview → post. Recovery: ineligible or offline states hide or disable the entry; the remove/recovery actions stay visible beside the preview. Release when thread and reply forms (page, Forte pane, new-thread dialog) pass the browser image tests updated for the new entry. Out of scope: other menu items' wording, the todo "module should look like the other modules" restyle, and image processing.

## Risks
- **Hidden control state:** the entry sits in a closed `<details>` menu, so focus return, disabled state, and the `outbox_compose.js` menu insertion could break. Validate early with the browser test; keep the menu list as the single insertion point.
- **Reply-form regression:** moving anonymous reply into a menu changes a primary flow and its tests. Validate by running the reply tests before Step 3 stages depend on it; keep the same button behavior and `data-action`.
- **Test and copy drift:** `.image-upload-choose` tests and UI copy audit entries reference the old caption. Mitigate by updating both in the same stage.

## Shared component inventory
- `partials/image_compose_fields.php` (shared; used by thread and reply forms): remove its chooser button in favor of the menu entry.
- `partials/thread_compose_form.php` has the only `…` menu markup; `partials/reply_form.php` has none. Extract a shared menu partial rather than duplicating it.
- `public/assets/image_upload.js` (chooser wiring and caption), `compose_disclosure.js` (menu close), `outbox_compose.js` (menu insertion), `compose.css` (menu and image styles) are extended.
- Navigation icon mappings: not applicable (no navigation destination).

## Simple user flow
1. Open a thread or reply form; tap `…`.
2. Tap "Attach image"; the menu closes and the picker opens.
3. Pick an image; preview and status appear; `…` now offers "Replace image".
4. Submit; or Remove image / cancel to recover.

## Success criteria
- No standalone chooser button remains in any compose form; "Choose an image" appears nowhere in the UI.
- Thread and reply `…` menus list Attach image, and the reply menu lists Post anonymous reply and Clear fields.
- Updated browser image tests pass for thread, reply, Forte pane and dialog; ineligible users see no entry.

## Enhancement Requests
- Request: choose Option B (reply-form menu) / Disposition: incorporated in scope / Approval: Step 1 approved / Outcome: pending.
