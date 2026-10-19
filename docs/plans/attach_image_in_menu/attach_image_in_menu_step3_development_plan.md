> **Feature plan:** [Step 1](./attach_image_in_menu_step1_solution_assessment.md) · [Step 2](./attach_image_in_menu_step2_feature_description.md) · [Step 3](./attach_image_in_menu_step3_development_plan.md) · [Step 4](./attach_image_in_menu_step4_implementation_summary.md)

Debug mode: enabled

# Attach image in the "…" menu: Step 3 development plan

## Completion Contract
- Entry: `…` → "Attach image" in thread and reply compose forms (page, Forte pane, new-thread dialog).
- Outcome: pick/drop/paste → preview → post works as before; no "Choose an image" text remains. The `…` button position is unchanged (out of scope).
- Recovery: ineligible users see no entry; busy state disables it; cancel returns focus; Remove image and the recovery notice stay visible.
- Release: updated browser image tests and the reply/thread smoke tests pass. No deployment or schema change.

## Key Risks
- **Hidden control state:** the entry is in a closed menu; focus return, eligibility, and busy state use `fields.querySelectorAll('button')`. Validate early in Stage 2; keep the entry reachable via `form.querySelector`.
- **Reply-form regression:** moving anonymous reply and Clear fields under a menu may break `browser_signing.js` lookups and Forte CSS selectors of the form `.compose-form-actions > button`. Validate in Stage 3 with a selector grep and smoke tests before dependent stages.
- **Test and copy drift:** tests and the UI copy checklist reference the old caption; update with the stage that changes behavior.

## Stage 1
- Goal: share the `…` menu markup between thread and reply forms, with no visible change.
- Dependencies: none.
- Expected changes: new partial for the action menu (items passed in); `thread_compose_form.php` uses it.
- Verification approach: thread compose smoke/browser tests unchanged and passing; rendered markup diff is empty.
- Risks or open questions:
  - Impact: menu items differ per form (quote-only item, outbox insertion).
  - Early warning / validation: `outbox_compose.js` still finds `.compose-action-menu-list`.
  - Mitigation: partial takes a list of items; keep class names.
- Canonical components/API contracts touched: `thread_compose_form.php`, new menu partial.

## Stage 2
- Goal: Attach image lives in the thread menu.
- Dependencies: Stage 1.
- Expected changes: partial renders `Attach image` (`.image-upload-choose`) in the menu and omits it from `image_compose_fields.php`; `image_upload.js` queries it from the form, hides/disables it by eligibility and busy state, and keeps the "Replace image" label; tooltip text updated.
- Verification approach: thread browser image test updated to open `…` first; check hidden for ineligible users, disabled while busy, focus returns on cancel.
- Risks or open questions:
  - Impact: entry visible with no fields when ineligible.
  - Early warning / validation: ineligible-user test.
  - Mitigation: `displayEligibility` toggles the entry.
- Canonical components/API contracts touched: `image_compose_fields.php`, `image_upload.js`, `thread_compose_form.php`.

## Stage 3
- Goal: reply form gets the menu with Attach image, Post anonymous reply, Clear fields.
- Dependencies: Stage 2.
- Expected changes: `reply_form.php` uses the partial and removes the inline buttons; fix any selectors that assume direct children.
- Verification approach: reply tests for anonymous post and clear; image test for reply; grep of `.compose-form-actions >` rules and `browser_signing.js` lookups.
- Risks or open questions:
  - Impact: anonymous reply unreachable or unstyled in Forte.
  - Early warning / validation: reply and Forte pane tests.
  - Mitigation: keep `data-action` values and button types.
- Canonical components/API contracts touched: `reply_form.php`, `forte.css`, `compose.css`, `browser_signing.js` (read only unless a selector breaks).

## Stage 4
- Goal: Forte pane/dialog coverage, mobile touch size, and copy cleanup.
- Dependencies: Stage 3.
- Expected changes: `tests/browser/image_content_browser.mjs` covers pane and new-thread dialog; menu entry is at least 44px; UI copy checklist and `todo.txt` (line 113) updated; confirm "Choose an image" is gone from the repo UI.
- Verification approach: full browser image test; grep confirms no old caption; mobile viewport check.
- Risks or open questions:
  - Impact: icon themes are unaffected (no navigation destination).
  - Early warning / validation: none expected.
  - Mitigation: none needed.
- Canonical components/API contracts touched: tests, `docs/plans/ui_copy_audit_checklist.md`, `todo.txt`.
