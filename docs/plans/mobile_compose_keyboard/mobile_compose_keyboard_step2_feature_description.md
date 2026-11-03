Source-Thread: thread-20261031051903-0e8e7df4
Source-Post: reply-20261031060257-3e498fdf
Source-Record: ../../../state/local_repository/records/posts/2026/10/31/reply-20261031060257-3e498fdf.txt
Collected-At: 2026-10-31
Collection-Status: Step 2; approval is reported in the Step 3 source comment, with no separate approval comment in the collected thread.
Collection-Note: Working plan revised from the signed source comment; consult Source-Record for the unchanged original. Historical estimates and attribution are retained.
Revised-At: 2026-10-31
Revised-By: Codex
Revision: v1.1; documentation corrections authorized by the user, without implementation approval.

Railspike Step 2: Feature Description
File: docs/plans/mobile_compose_keyboard/mobile_compose_keyboard_step2_feature_description.md

> **Feature plan:** [Step 1](./mobile_compose_keyboard_step1_solution_assessment.md) · [Step 2](./mobile_compose_keyboard_step2_feature_description.md) · [Step 3](./mobile_compose_keyboard_step3_development_plan.md) · [Step 4](./mobile_compose_keyboard_step4_implementation_summary.md)

Debug mode: enabled (corrected from Step 1's provisional "disabled": `.railspike_debug_on` was already present at the repository root when this workflow began, and Step 1 was written without repository access)

Effort study: `v3/mobile_compose_keyboard` · [ledger](../effort_estimation_trial.md#cycle-v3mobile_compose_keyboard)

Extended estimates: enabled

**Cycle estimate: 0.40 (historical v1 projection; v1.1 reassessment pending)**

## Problem

On the dedicated `/compose/thread` page on a phone, focusing the text area brings up the keyboard and pushes Post out of view, because the text area has a fixed 7-row height and the tag control sits between it and Post. The homepage's compact composer doesn't have this problem.

## User stories

- As a member writing a new post on a small phone, I want the text area and Post to stay on screen while the keyboard is open so that I can see what I'm typing and send it without scrolling hunts.
- As a member who wants tags, I want the tag control below Post so that it's there when I need it without crowding the main action.
- As a member on a larger screen or with the keyboard closed, I want the text area to use the space available so that the page doesn't feel cramped.

## Core requirements

- With the on-screen keyboard open on `/compose/thread`, the text area shrinks to the space above the keyboard after the title and the Post row, and the caret line and the Post button both stay visible while typing, including long drafts. When the keyboard closes, normal flow returns without losing focus, selection, draft content or the user’s reading position. Test the transition rather than requiring unchanged geometry during a real keyboard state change.
- Order on the dedicated form: title, text area, Post row (with the "..." menu), then tags (and event, when enabled). Field names, values, submit, signing, drafts, anonymous posting, Outbox and attachments behave as today.
- The page looks right on first paint. Without JavaScript, or when no keyboard is detected (desktop, hardware keyboard), the form uses the ordinary flow with the new order and Post reachable.
- Compact composers (homepage/board, QDB add) and reply composers keep their current behavior.

## Delivery scope and completion boundary

**Work type:** application change. Covers the full thread composer on `/compose/thread`, plus the paned new-thread dialog, which renders the same full form (`compact => false`); board, board list and QDB add use the compact form and are unaffected. Excludes the theme overflow issues (separate request below), reply and private-message composers, and deployment.

Completion: from the ordinary New post tab, the member types a post with the keyboard open, presses Post, and gets exactly one thread. Validation, signing and network errors keep the draft and make feedback and Post reachable for retry. Ordinary typing must keep Post visible; oversized feedback, expanded tools, attachments or zoom may restore normal scrolling instead of forcing everything into the docked panel. Release needs automated browser checks at phone sizes in all explicit themes from `ThemeRegistry::explicitNames()` (currently 16) and the maintainer's iPhone SE result; device acceptance stays recorded as pending until then.

## Known user and environment

Ilya on an iPhone SE (3rd gen) in Firefox (and Safari to confirm WebKit), opening New post from the bottom tab bar. Failure to handle: the keyboard covers Post. Next action unaided: keep typing; Post stays visible above the keyboard, and tapping it publishes. To verify, he sees the new thread open, or on error sees the message and Post still visible and retries without retyping. Evidence: a note in the Step 4 summary with device, iOS and browser versions and the result. Headless WebKit can't open a keyboard, so it doesn't count as this evidence.

## Risks

- **Keyboard detection on iOS Firefox:** the existing heuristic in `reply_viewport.js` treats the keyboard as open when the visible height drops below 75% of the tallest seen. Earliest check: record viewport geometry and editing intent on the SE in Firefox and Safari before implementing the docking extension in implementation Stage 2. Use a maintainer-reachable development/test instance; production deployment is not included. Automated work that does not depend on the device result may continue, but docking implementation and final device acceptance remain gated on the relevant evidence. Mitigation: reuse the reply composer's detection rather than a second implementation, and keep the ordinary-flow fallback.
- **Shared composer partial:** moving tags in `composer.php` changes every full thread composer. Checked: only the compose page and the paned new-thread dialog render the full form; board, board list and QDB add pass `compact => true`. Mitigation: gate the new order on the full composer only.
- **Too little space:** on the SE with a long title or an error message open, the text area may get only a few lines. Mitigation: set a minimum text area height and let the page scroll as a fallback rather than hide controls.
- **First-paint shift:** a script-sized text area could jump after load. Mitigation: size only while the keyboard is up, never on initial render.

## Shared component inventory

- `templates/partials/composer.php`: extend (order of tools and actions for the full thread composer). Canonical composer for every form.
- `templates/partials/thread_compose_form.php`, `templates/pages/compose_thread.php`: reuse; options only if needed.
- `public/assets/reply_viewport.js`: reuse its keyboard detection for the thread composer (extend or factor out the shared part) instead of writing new viewport code. It already docks the reply composer above the keyboard.
- `public/assets/compose.css`: extend for the keyboard-up sizing and the new order.
- No new API, schema or dependency.

## Simple user flow

1. Tap New post in the bottom tab bar.
2. Optionally type a title, then tap the text area; the keyboard opens.
3. The text area shrinks to fit; Post stays visible below it.
4. Type; the caret and Post stay visible as the draft grows.
5. Optionally close the keyboard and open tags below Post.
6. Tap Post; the new thread opens (or an error shows with the draft kept and Post visible).

## Success criteria

- On the iPhone SE in Firefox and Safari with the keyboard open, Post and the caret line are visible while typing a 1-line and a 30-line draft (maintainer check).
- In WebKit and Chromium, simulate a visual viewport shrinking from 375 x 667 to 375 x 300 while retaining the layout viewport. In ordinary body editing, the editor and Post fit without overlap in every explicit registered theme. Capture baseline overflow at both sizes: introduce no new overflow, keep the composer reachable, and record unrelated existing theme defects separately. An existing defect that blocks composer use requires scope review, not an automatic waiver or an unapproved theme fix.
- Verify title-first focus, title/body transitions, keyboard close/reopen, viewport scrolling/offsets, rotation and zoom. Title-only editing may remain in ordinary flow; body editing establishes docking intent, and returning to the title preserves reachability.
- Exercise actual validation, signing and network failure/retry, including server-rendered open tags/event controls. When content exceeds the available height, normal scrolling exposes the feedback, tools and Post without losing the draft. Successful submit/retry creates exactly one thread.
- Cover anonymous, signed and attachment-eligible/ineligible viewers, delayed initialization and no JavaScript; verify compact, reply and desktop paned consumers retain their intended behavior.
- Tags render below the Post row on `/compose/thread`; compact board, QDB and reply composers render as before.
- Existing compose, signing, Outbox, attachment and image browser tests pass.

## Enhancement Requests

- **Request:** Word 97 / Midnight phone overflow and Sticker / Arena Post padding at 375px. **Disposition and reason:** separate work; different cause (theme chrome vs. keyboard). **Approval status:** pending. **Follow-up:** separate thread or cycle on request.

## Historical effort snapshot

2026-10-31T06:05Z · Step 2 handoff · cycle `v3/mobile_compose_keyboard` · scope v1 (Option B as above, baseline `63f0849691902e665506ad4f23f374353a32ae78`) · reference R1 (three hashes verified) · protocol P2 · rater Claude, single exposed rater (saw R1 feedback scores and calibration examples, and inspected the composer, `reply_viewport.js` and related plans before rating) · **0.40 projected total**. Reason: the composer is already shared and the reply composer already solves keyboard docking, so this is mostly reuse plus a reorder; the 13-theme check and phone acceptance keep it above the smallest references. Compare `v3/ui_tab_bar_scroll_away` (0.45) and composer decluttering (0.60). Intake and Step 1 missing, not backfilled.

## Approval

Approval-Status: The Step 3 source says Step 2 was approved; a separate maintainer approval was not recovered. The user authorized this documentation revision, not implementation. Recover that evidence or obtain approval of the revised requirements before advancing; the existing Step 3 is a revised proposal.

*Original source author: Claude · 2026-10-31 UTC · Model: Claude Opus 5.5*

#meta #bug #railspike

## Revision record

Revision-Date: 2026-10-31
Revision-Author: Codex
Authorization: User requested editing the imported plans after review; documentation only, no implementation, branch, commit or deployment approval.
Scope-Version: v1.1; original v1 outcome retained, with explicit overflow baseline, scrolling recovery, device gate and focus behavior.
Reuse-Review: Inspected templates/partials/composer.php, templates/partials/thread_compose_form.php, templates/pages/compose_thread.php, public/assets/reply_viewport.js, public/assets/compose.css, tests/browser/reply_viewport.mjs, tests/browser/compose_layout_stability.mjs and src/ForumRewrite/View/ThemeRegistry.php. Extend the existing full composer and docking contract; no new component, API, schema or dependency.
Verification: Reviewed working-plan consistency, source references and relative links. No application tests or physical-device replay performed; historical reported measurements are not new verification.
Study-State: Existing v3/mobile_compose_keyboard observation under standing railspike-effort P2 enrollment, not a new cycle. Original Step 2/3 total 0.40 and S1-S4 work projections 0.08/0.15/0.12/0.07 remain historical Claude single-exposed-rater judgments. Missing intake and Step 1 scores remain missing. Original baseline 63f0849691902e665506ad4f23f374353a32ae78 retained. This editorial revision does not reaffirm or replace those ratings; current reassessment and frozen R1 verification are pending before a new planning handoff. No independent rating, measured labor or accepted outcome.
Next-Gate: Reconcile earlier maintainer approvals and study references before formal handoff; revised Step 3 remains proposed. Preserve the signed source records unchanged.
