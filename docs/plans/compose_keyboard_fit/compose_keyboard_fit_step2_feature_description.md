> **Feature plan:** [Step 1](./compose_keyboard_fit_step1_solution_assessment.md) · [Step 2](./compose_keyboard_fit_step2_feature_description.md) · [Step 3](./compose_keyboard_fit_step3_development_plan.md) · [Step 4](./compose_keyboard_fit_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/compose_keyboard_fit` ([ledger](../effort_estimation_trial.md#cycle-v3compose_keyboard_fit)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.20**

Step 1 approved 2026-11-02: Option A, phone layout from CSS and markup, no script.
Revision 1, 2026-11-02: the maintainer asked for the Title below the Post row as well (scope v1.1).

## Problem

On a phone, focusing the body on `/compose/thread` opens the keyboard and pushes Post out of view, because the full composer stacks a 7-row body, the tags chip and a reserved hidden image-picker footprint above it. The homepage's compact form does not have this problem.

## User stories

- As a member writing a new post on a small phone, I want the body and Post on screen while the keyboard is open so that I can see what I type and send it without hunting.
- As a member who sometimes adds a title or tags, I want those controls below Post so that they are there when needed without crowding the main action.
- As a member on a desktop or in the paned board's New Thread dialog, I want the composer to keep working as it does today, apart from the new control order.

## Core requirements

- Full composer order becomes: body, Post row (with the ··· menu), Title, then tags and event. The body is the first field, as on the homepage form; the Title keeps its optional "or we'll use your first line" behavior. Field names, values, submit, signing, drafts, anonymous posting, Outbox and attachments are unchanged.
- On phone widths, the full composer's body starts at the compact form's height instead of 7 rows, and no hidden picker footprint is reserved between the body and Post. Wider screens keep today's body height and footprint.
- With the keyboard open on a 375px phone, the body and Post are both visible while typing a short or a long draft; long drafts scroll inside the body.
- First paint is final: no post-paint reordering or resizing; the layout is the same with JavaScript off.
- Compact composers (board, board list, QDB add) and reply composers are unaffected.

## Concepts touched

- Shared composer (`templates/partials/composer.php` with `thread_compose_form.php`): proposed inventory entry; changed (Title and tools after Post, phone body height). Add the entry with this cycle.
- Compact board composer: used unchanged; its body height is the reference.
- Compose page styles (`public/assets/compose.css`): changed (phone rules for the full composer).
- Paned board New Thread dialog: used unchanged; it renders the full composer and gets the new order.
- Reply keyboard docking (`reply_viewport.js`): used unchanged; not touched this cycle.

## Delivery scope

- Work type: application change.
- Includes the shared composer partial, compose page styles, the browser and PHP tests that check composer structure, the UI copy audit record (no copy change expected), the proposed concept entry, and this cycle's plans, debug log and ledger bookkeeping.
- Excludes theme chrome overflow (Word 97, Midnight, Sticker, Arena), keyboard docking for the thread composer (Step 1 Option B), reply and private message composers, and deployment.

## Completion boundary

- Normal entry: New post in the phone tab bar, or `/compose/thread` directly, as member or guest. The body is the first field.
- Outcome: with the keyboard open, the member types a post with Post visible, presses it and gets exactly one new thread; Title and tags are available below Post.
- Recovery: a validation, signing or network error keeps the draft and shows the message with Post still reachable; JavaScript off gives the same layout.
- Release: existing compose, composer, disclosure, layout stability, signing, Outbox and image browser tests pass; the form is checked at 375 by 667 and 375 by 300 in every permitted theme; the maintainer's iPhone SE result is recorded, pending until it arrives.

## Known user or environment

The maintainer on an iPhone SE (3rd generation) in Firefox for iOS, confirming in Safari (same WebKit engine). Ordinary entry: New post from the tab bar, tap the body. Failure to handle: the keyboard covers Post. Next action unaided: keep typing; Post stays under the body above the keyboard; tap it. Verification: the new thread opens, or an error shows with the draft kept and Post visible. Evidence: a note in the Step 4 summary with device, iOS version, browser and result for a one-line and a long draft. Headless WebKit cannot open a keyboard, so it does not satisfy this item.

## Risks

- **Browser scroll position on focus.** Impact: iOS may scroll the focused body so Post sits just below the visible area even though the form is short enough. Earliest validation: the maintainer's SE check after Stage 1; a 375 by 300 headless measurement of the body-to-Post distance before that. Mitigation: keep the body, status line and Post row within roughly 250px on phones; if the real phone still hides Post, open Option B as a follow-up rather than adding a script here.
- **Reorder reaches the paned dialog and tests.** Impact: desktop New Thread dialog changes order; tests that assume Title first or tags before Post fail. Validation: render the full and compact composer in PHP and run the composer browser tests in Stage 1. Mitigation: gate the new order on the full composer only and update assertions deliberately.
- **Removing the reserved picker footprint on phones.** Impact: when uploads become enabled after paint, the hidden fieldset could shift Post. Validation: the layout stability test at a phone viewport. Mitigation: the footprint only moves below Post on phones if the test shows a shift; it is not reintroduced between body and Post.
- **Title after Post.** Impact: a member used to typing a title first now meets the body first, and on a 375px phone the Title sits just below Post. Accepted: the Title is optional and the first line already stands in for it; keyboard Tab order follows the new visual order so nothing is skipped.
- **Attached image preview.** Impact: a prepared image preview (up to 120px) still sits between body and Post. Accepted: attaching is a deliberate step and the preview is the thing being posted; noted, not solved here.

## Shared component inventory

- `templates/partials/composer.php` via `thread_compose_form.php`: **extend**; the only composer, renders every form. Full mode is used by `/compose/thread` and the paned New Thread dialog; compact mode by board, board list and QDB add.
- `public/assets/compose.css`: **extend** with phone rules for the full composer; compact and reply rules untouched.
- `public/assets/site.css` phone shell (`--app-tabbar-offset`): **reuse** unchanged.
- No new component, API, schema or dependency.

## User flow

1. Tap New post in the tab bar.
2. Tap the body; the keyboard opens.
3. Type; the body scrolls internally for long drafts and Post stays under it.
4. Optionally add a Title or open tags below Post.
5. Tap Post; the new thread opens, or an error shows with the draft kept.

## Success criteria

- In headless WebKit and Chromium at 375 by 667 and 375 by 300, the Post button's bottom edge is within 300px of the body's top edge in all 13 permitted themes, with no horizontal overflow at 375 by 667.
- The Title and the tools block render after the Post row in full mode and do not render in compact mode; reply composers are byte-identical to today.
- On the maintainer's iPhone SE in Firefox and Safari, Post is visible while typing a one-line and a 30-line draft (recorded pending until checked).
- Existing compose, composer, disclosure, layout stability, signing, Outbox and image browser tests pass.

*— Claude · 2026-11-02 UTC · Model: claude-fable-5-1*
*— Revised by Claude · 2026-11-02 UTC · Model: claude-fable-5-1 (Revision 1: Title below the Post row)*

## Approval and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-11-02T01:20Z | 0.20 (reaffirmed) | v1 / R1 / P2, baseline `2a7d356f8` | Claude (claude-fable-5-1), single exposed rater; additionally inspected the image picker partial, the paned New Thread dialog and the tests that reference composer structure | Requirements match Option A; the paned dialog and test updates were expected; no scope change |

| Revision 1 | 2026-11-02T01:25Z | 0.20 (reaffirmed) | v1.1 / R1 / P2, same baseline | Same | Title moved below the Post row at the maintainer's request; same partial and styles, one more field in the reorder; not approval of Step 2 |

Earlier snapshots: [Step 1](./compose_keyboard_fit_step1_solution_assessment.md#effort-study). R1 hashes verified 2026-11-02.

Debug: [diagnostic log](./compose_keyboard_fit_debug_log.md#2026-11-02t0120z--step-2).

Reply "Approved Step 2" (or send changes) and I will write Step 3.
