> **Feature plan:** [Step 1](./reply_composer_focus_layout_step1_solution_assessment.md) · [Step 2](./reply_composer_focus_layout_step2_feature_description.md) · [Step 3](./reply_composer_focus_layout_step3_development_plan.md) · [Step 4](./reply_composer_focus_layout_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Inline reply switches focus between two textareas, and the floating Post row can cover the editor. Approved Option B replaces this with one editor and separately reserved writing, feedback and action space, preserving the established collapse/expand interaction.

## User stories

- As an iPhone reader, I want Reply to expand directly into one editor so that writing starts without a focus handoff or viewport jerk.
- As a replying user, I want Post and recovery controls reachable without covering my draft so that I can submit once or retry without retyping.

## Core requirements

- Keep one editable textarea through compact, expanded and submitted states. Focus visibly expands it; leaving an empty, attachment-free, warning-free composer collapses it. Drafts, attachments and errors keep it expanded. Remove the redundant disclosure arrow; retain the action menu.
- Render the correct initial layout before deferred scripts run. Initialization must not flash or relocate Post or resize/reposition the untouched compact editor. Avoid competing focus transfers and scrolling.
- On phones, reserve distinct space for editor, feedback and actions: Post remains reachable above the keyboard without intersecting the textarea, caret or recovery feedback. Handle viewport changes, long drafts, menus, rotation and zoom; provide usable ordinary-flow fallback when space is inadequate.
- Preserve draft/selection, identity, anonymous posting, attachments, pending/duplicate-submit protection and retry. Preserve desktop, keyboard, assistive-technology, no-script, Forte and the corrected board composer behavior.

## Delivery scope and completion boundary

**Work type:** Application change. Cover standard thread inline replies and the dedicated reply page's shared action layout. Exclude board redesign, private messages, Forte redesign, schema changes and deployment. Record verification in this cycle's implementation summary and applicable UI audit notes.

Start at ordinary Reply or dedicated reply entry; finish with exactly one accepted reply and existing navigation/update. Validation or recoverable failure retains the draft and exposes correction/retry. Automated browser coverage permits implementation handoff; actual iPhone acceptance remains pending user testing, without an upfront device-infrastructure gate.

## Known environment and recovery

On iPhone Safari and Firefox, the user opens Reply, types with the keyboard visible, scrolls a long draft, rotates and reopens the keyboard. After failure, they can read the error, correct or retry, and verify one resulting reply. Record device/browser versions and outcome; desktop engines cannot establish this device result.

## Risks

- **Keyboard geometry:** Controls may cover text or leave too little writing space. Validate short viewports and explicit editor/action/error separation early; reserve space and retain fallback before acceptance.
- **Interaction regression:** Removing the placeholder may lose collapse behavior or introduce a first-paint shift. Verify actual height transitions and delayed-script geometry early; preserve one editor and its selection.
- **Shared lifecycle:** Layout changes may break menus, signing, retry or shared consumers. Exercise both real reply flows, failures and repeated pending activation; reuse canonical contracts and check board/desktop/Forte.

## Shared component inventory

Extend the canonical reply form used by thread inline replies, the dedicated reply page and Forte (regression only). Reuse shared inline-compose lifecycle and compose styles, viewport behavior, action menu, normalization/feedback, image fields, identity/signing, draft/Outbox handling and existing reply submission APIs. Reuse the board's direct-focus lessons without changing its delivered behavior; introduce no competing form or submission contract.

## Simple user flow

1. Focus the compact Reply editor or enter the dedicated page; write, scroll and rotate with separate editor/action space.
2. Post once, or correct/retry a recoverable failure with the draft intact.
3. Verify one reply; an empty inline composer can return to its compact state.

## Success criteria and request state

Both entry points pass first-focus, non-overlap, viewport-change and real submission/recovery checks. Inline collapse/expansion and pre-script/initialized geometry pass explicit assertions across phone/desktop and affected themes, including Word97, Chicago and Midnight. Physical iPhone acceptance is reported separately. Step 1 was reapproved after the board lessons; the pause is lifted for Step 2 planning only. No reply implementation or branch reuse is authorized yet.
