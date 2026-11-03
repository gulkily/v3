Source-Thread: thread-20261031051903-0e8e7df4
Source-Post: reply-20261031055757-d9c87784
Source-Record: ../../../state/local_repository/records/posts/2026/10/31/reply-20261031055757-d9c87784.txt
Collected-At: 2026-10-31
Collection-Status: Revised Step 1; keyboard-layout direction carried forward into Steps 2 and 3.
Collection-Note: Working plan revised from the signed source comment; consult Source-Record for the unchanged original. Historical estimates and attribution are retained.
Revised-At: 2026-10-31
Revised-By: Codex
Revision: v1.1; documentation corrections authorized by the user, without implementation approval.

Railspike Step 1: Solution Assessment (revised after Ilya's clarification)
File: docs/plans/mobile_compose_keyboard/mobile_compose_keyboard_step1_solution_assessment.md

> **Feature plan:** [Step 1](./mobile_compose_keyboard_step1_solution_assessment.md) · [Step 2](./mobile_compose_keyboard_step2_feature_description.md) · [Step 3](./mobile_compose_keyboard_step3_development_plan.md) · [Step 4](./mobile_compose_keyboard_step4_implementation_summary.md)

Debug mode: enabled (inherited from the later source plans; the original Step 1 provisional disabled state remains in the signed source).

Effort study: `v3/mobile_compose_keyboard` · [ledger](../effort_estimation_trial.md#cycle-v3mobile_compose_keyboard)

Extended estimates: enabled (inherited); original Step 1 option and cycle estimates missing, not backfilled.

## Original Query

Hi, is anyone working on this page looking normal?
http://zenmemes.com/compose/thread
iPhone SE3 Firefox

## Revised direction (from the clarification comment)

The bug is the /compose/thread form with the keyboard open, not the themes. When the text area is focused on an iPhone SE in Firefox, the Post button drops out of view. The homepage form handles this fine. Wanted: the text area shrinks to fit the space above the keyboard, the text area and Post both stay visible while typing, and the tag selector moves below Post. Acceptance is on the real phone with the keyboard open.

The Word 97 / Midnight overflow and the Sticker / Arena padding from my first post are separate work, recorded under Enhancement Requests below.

## Problem statement

The source report measured a 191px text area and Post at 400–447px with roughly 300px visible above the keyboard. These historical measurements need a baseline replay. The current shared partial uses seven rows for full composers and three for compact composers, with full-form tools before Post.

## Concepts touched

- Compose form (proposed entry): changed in all options.
- Homepage post form: used unchanged; its compact configuration informs A and C.
- App shell / bottom tab bar (proposed entry): used unchanged.

## Option A: Reorder and shrink with CSS

Move the tag selector below Post in the full composer and use a shorter phone textarea with internal scrolling for long drafts. Preserve ordinary document flow and first-paint stability.

- Pro: small CSS/template change, with no new viewport behavior or JavaScript requirement.
- Con: a fixed height cannot guarantee that Post fits above the keyboard across viewport sizes, errors and expanded tools.
- Con: a short default leaves less writing space when the keyboard is closed.

## Option B: Extend existing viewport docking

Use A's reorder and ordinary-flow fallback, then extend `public/assets/reply_viewport.js` for the dedicated thread form. Reuse keyboard detection, viewport geometry, minimum editor height and overflow recovery. Dock only after editing intent while the keyboard is detected; retain ordinary flow when it closes.

- Pro: reuses the existing reply behavior and keeps Post and the caret visible during ordinary body editing, including long drafts.
- Pro: avoids a second keyboard detector and preserves existing submit/draft contracts.
- Con: shared-script changes require reply regression checks and physical iPhone verification.
- Con: oversized tools, feedback, attachments or zoom require scrolling fallback; visibility cannot be guaranteed at every screen size.

## Option C: Align the dedicated form with compact configuration

Both placements already render `templates/partials/composer.php` through `thread_compose_form.php`. Reuse the compact sizing choices on the dedicated page while retaining its title, tags and event fields. This is configuration/layout alignment, not component consolidation; the homepage itself stays unchanged.

- Pro: uses an existing compact layout as the baseline without adding docking behavior.
- Con: the full form has more controls, so matching its textarea size alone does not establish keyboard-open visibility.
- Con: substantially overlaps Option A and provides no viewport-aware guarantee.

## Recommendation

Option B, with A's CSS as the no-JavaScript fallback. Vertical slice: /compose/thread in the default theme on the iPhone SE with Firefox. Typing several lines with the keyboard open, the cursor line and the Post button both stay visible and the tags sit below Post. Confirm the reported behavior separately in Firefox and Safari on the actual phone. Oversized content must remain reachable through normal scrolling; no-JavaScript and keyboard-closed layouts remain in ordinary flow.

## Enhancement Requests

- Word 97 / Midnight phone overflow and Sticker / Arena Post padding at 375px (from my first post). Disposition: separate work, not this cycle. Approval: pending. Follow-up: a new thread when Ilya wants it.

*Original source author: Claude · 2026-10-31 UTC · Model: Claude Opus 5.5*
*Original source revision: Claude · 2026-10-31 UTC · Model: Claude Opus 5.5*

Approval-Status: Later source comments proceeded to Steps 2 and 3, but separate maintainer approvals were not recovered from the collected thread. This revision authorizes no implementation; reconcile approval evidence before advancing the workflow.

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
