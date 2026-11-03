> **Feature plan:** [Step 1](./compose_keyboard_fit_step1_solution_assessment.md) · [Step 2](./compose_keyboard_fit_step2_feature_description.md) · [Step 3](./compose_keyboard_fit_step3_development_plan.md) · [Step 4](./compose_keyboard_fit_step4_implementation_summary.md)

Debug mode: enabled (`.railspike_debug_on` present at the project root; new workflow)
Effort study: `railspike-effort` / `v3/compose_keyboard_fit` ([ledger](../effort_estimation_trial.md#cycle-v3compose_keyboard_fit)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.20**

## Original Query

Hi, is anyone working on this page looking normal?
http://zenmemes.com/compose/thread
iPhone SE3 Firefox

(Forum thread `thread-20261031051903-0e8e7df4`, 2026-10-31, and the maintainer's clarification recorded in `reply-20261031055720-3f71bafb`: on the dedicated New post page, when the text area gets focus and the keyboard opens, the Post button is no longer visible. The homepage form works well. Make the dedicated form fit the screen like the homepage form: let the text area shrink when the keyboard opens, keep the text area and Post visible while typing, and move the tag selector below Post. Check on the actual phone with the keyboard open.)

Session request, 2026-11-02: "please ignore the written steps, write a new step 1". The three Railspike posts in that thread are superseded; their plan files were never written to any checkout.

## Understood Intent

On a 375px-wide iPhone with the keyboard open, `/compose/thread` should keep the body field and Post on screen while typing, with tags out of the way below Post. Theme chrome overflow (Word 97, Midnight, Sticker, Arena) is a different cause and is not this cycle (see Requests).

## Problem

The full composer stacks a 7-row body, the tags chip and a reserved hidden image-picker footprint above Post, so with roughly 300px visible above the keyboard Post is off screen.

## Concepts touched

- Shared composer (`templates/partials/composer.php`, `thread_compose_form.php`): proposed entry; changed in every option. The paned new-thread dialog renders the same full form.
- Compact board composer (same partial, `compact` mode): used unchanged; it is the layout the maintainer says works.
- Reply keyboard docking (`public/assets/reply_viewport.js`, `.reply-panel-active` rules in `compose.css`): proposed entry; used unchanged in A, extended in B, not used in C.
- Phone app shell and tab bar (`site.css`, `--app-tabbar-offset`): used unchanged.

## Option A: Phone layout from CSS and markup, no script

**Estimate: 0.20**

Render the tools block (tags, event) after the Post row for the full composer only. On phones, give the full composer the compact form's body height instead of 7 rows and stop reserving the hidden picker footprint between the body and Post. The page then matches the homepage form the maintainer already finds usable: the body scrolls internally for long drafts and Post sits directly under it.

- Pros: smallest change; same behavior with or without JavaScript; first paint is final.
- Pros: reuses the compact form's measurements rather than inventing new ones.
- Cons: the body does not grow to use free space when the keyboard is closed.
- Cons: relies on the browser scrolling the focused field into view; Post still depends on page scroll position rather than being pinned.
- Driver: the shared partial also feeds the paned dialog; the picker footprint exists to prevent layout shift when uploads enable. Uncertainty: low, apart from the real-phone check.

## Option B: Option A plus reuse of the reply docking

**Estimate: 0.40**

Do A, then let the full thread composer use the same docking the phone reply composer already has: mark the compose card as the panel and the form with the docking attribute, generalizing the script's selector. While the keyboard is up the panel is fixed to the visible area, the body flexes to the remaining height and Post is pinned at the bottom; the script's existing guards fall back to page flow when the panel would overflow or there is no on-screen keyboard.

- Pros: Post is pinned above the keyboard at any draft length; same code path as replies, so one keyboard heuristic to maintain.
- Pros: A remains the no-script fallback.
- Cons: the script is shared with replies and with the unapproved `v3/composer_anchor_exit` plan, so every reply viewport test must be rerun.
- Cons: the title field and tools add height inside the panel; on the SE with an error message open the guards may bail to page flow, which is A's result anyway.
- Driver: generalizing the reply-only selectors and CSS without changing reply behavior; real-phone verification in Firefox and Safari. Uncertainty: medium, mainly the 75% keyboard heuristic on iOS Firefox.

## Option C: New compose-page sizing script

**Estimate: 0.50**

What the forum posts proposed: do A's reorder, then add a separate script on `/compose/thread` that writes the visual viewport height to a CSS variable and sizes the body from it.

- Pros: independent of the reply code, so no reply regression risk.
- Cons: a second keyboard heuristic beside `reply_viewport.js`, contrary to the reuse rule; more code to keep working on iOS.
- Cons: nothing it achieves is beyond B.
- Driver: new viewport code and its own tests. Uncertainty: medium.

## Recommendation

Option A as this cycle. It is a releasable slice: entry is New post in the phone tab bar, the outcome is body and Post on screen while typing with tags below Post, recovery is the same layout without JavaScript. Verify with the existing compose browser tests at a phone viewport plus one headless WebKit measurement at 375 by 300, then the maintainer's iPhone SE check in Firefox and Safari. If that check still loses Post with a long draft, open B as a follow-up cycle rather than widening this one. C is not recommended.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Word 97 and Midnight status bar, frame and background overflow at 375px; Sticker and Arena Post under the tab bar; "New post" tab label wrapping (forum `reply-20261031052311-1c6dbdc3`) | Separate work: theme chrome, different cause from the keyboard layout | Pending; new cycle when the maintainer wants it | Pending |
| Reply in the forum thread saying the three posted Railspike steps are superseded and what is being changed | Separate: a forum post, not a code change; the maintainer decides whether and when | Not requested | Pending |

*— Claude · 2026-11-02 UTC · Model: claude-fable-5-1*

## Effort study

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-11-02T01:15Z | 0.20 | v1 / R1 / P2, baseline `main` `2a7d356f84dc5b613b46ea08e75be86f5ec42ca8` | Claude (claude-fable-5-1), single exposed rater; read the forum thread including its 0.40 projection for a heavier scope, and inspected the composer partial, `compose.css`, `reply_viewport.js` and the tab bar rules before rating. No pristine pre-inspection intake. | Reorder and phone sizing in a shared partial already matching the compact form |
| Step 1 handoff | 2026-11-02T01:15Z | 0.20 (Option A) | v1 / R1 / P2 | Same | Recorded together with intake; no change |

Option estimates (A 0.20, B 0.40, C 0.50) are complete-cycle alternatives, not summed. All three frozen R1 hashes verified 2026-11-02. Predecessor: the forum-only cycle `v3/mobile_compose_keyboard` (2026-10-31) produced no repository artifacts and is replaced by this cycle. No implementation, measured labor or accepted outcome.

Debug: [diagnostic log](./compose_keyboard_fit_debug_log.md#2026-11-02t0115z--step-1).

Reply "Approved Step 1" (or send changes, including a different option) and I will write Step 2.
