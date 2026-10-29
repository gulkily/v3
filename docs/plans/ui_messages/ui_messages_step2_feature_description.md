> **Feature plan:** [Step 2](./ui_messages_step2_feature_description.md) · [Step 3](./ui_messages_step3_development_plan.md) · [Step 4](./ui_messages_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_messages` ([ledger](../effort_estimation_trial.md#cycle-v3ui_messages)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

Cycle 8 of the [UI redesign checklist](../ui_redesign/implementation_checklist.md#cycle-8-ui_messages), mockups [messages](../ui_redesign/mockups/messages.html) and [conversation](../ui_redesign/mockups/conversation.html). Process: Railspike without Step 1 (decided 2026-10-26). User request: "Go ahead, write Step 2" (2026-10-29).

## Proposed split (needs your decision)

The checklist item is larger than one cycle (intake 1.10). Proposed:

- **This cycle, `ui_messages`:** the list and the conversation read like a messaging app, every failure is recovered where it happens, and anyone who isn't an approved member gets told their next step. Single-pane on every width.
- **Follow-up, `ui_messages_panes`** (its own cycle and ID, about 0.55): two panes on desktop, a new-message button with a member picker, and loading more as you scroll in both directions.

## Problem

Messages work and recover from failures, but the pages read like a debugging tool: "Conversation with x", "Send private message", a ✓ on every message, "Decrypting preview…" down the list, detached "Check previous send" and "Retry seen status" buttons, and a bare "Authentication Required" for anyone not signed in as an approved member.

## User stories

- As a member, I want my conversations listed by person with a short preview, so I can see at a glance who wrote and what.
- As a member in a conversation, I want my messages on one side and theirs on the other, with days marked, so it reads like a chat.
- As a sender, I want a failed or uncertain send shown on that message with one action, so I never lose text or send twice.
- As a reader, I want a message that can't be decrypted or verified marked on that message, with Details and Retry, and nothing extra on the ones that are fine.
- As a guest or waiting member who opens Messages, I want to be told why I can't see them and what to do next.

## Core requirements

- **List:** each row shows the name, time, a two-line preview, an unread dot, and "You:" before your own last message. Rows waiting for decryption show a placeholder (no repeated "Decrypting…"); a row that fails shows "Couldn't decrypt preview" with Retry on that row only. A failed page load shows one "Couldn't load messages. Retry" line. "Load more" and the no-JavaScript line stay.
- **Conversation:** the person's name as the title with a back link; "Only you and {name} can read these, on your approved devices." at the top, always visible; bubbles (yours right, theirs left); day separators instead of per-message timestamps; no ✓ on verified messages; a Send button (arrow, accessible name "Send"); "Load older" stays; "Restart history" moves under a ··· menu.
- **Sending:** the bubble appears at once at reduced opacity and turns solid when delivered. A failure shows "Not sent · Retry" on that bubble and keeps the text; an uncertain send shows "Not confirmed · Check" on that bubble. Both survive a reload, as the draft and attempt do today.
- **Unreadable messages:** "Couldn't verify this message" (or "Couldn't decrypt") on the bubble with Details and Retry; runs of them stay grouped as today.
- **Not an approved member:** "Messages are for approved members" with a line for their actual state and one action: a guest gets "Set up account"; a waiting member gets "You're waiting for approval as {name}. Messages unlock once someone approves you." An approved key that isn't signed in is signed in where it is and lands on Messages. Chosen in the browser before first paint; the server page stays the same for everyone.

## Delivery scope

- Work type: application change.
- Includes the conversation list, conversation and composer templates and their scripts and styles, the not-yet-approved page for `/messages` and `/messages/conversation/{name}`, hooks (`--person` on the conversation, an empty starfield slot on an empty conversation, `data-incoming` on received bubbles; all inert), tests, the copy record, this cycle's plans and ledger bookkeeping. Every site where private messages are enabled.
- Excludes: two panes, the member picker and scroll loading (follow-up); avatars (none exist yet anywhere); "Seen" under your last message, which would tell senders when the other person has read it, a new privacy behavior and API; queuing messages in the Outbox while offline (deferred 2026-10-26); the legacy `private_messages.php` mailbox, already redirected to `/messages`; any change to encryption, signing, history sync or the message store.

## Completion boundary

- Normal entry: the Messages tab and a profile's Message button, on phone and desktop, as an approved member; `/messages` as a guest, a waiting member and an approved key without a session.
- Outcome: a member finds a conversation, reads it, and sends a message that turns solid; a non-member sees their next step.
- Recovery: Retry on a failed preview, a failed list load, an unreadable message and a failed send; Check on an unconfirmed send, including after a reload; the seen-status failure keeps its current recovery, moved next to the composer.
- Release: every permitted theme at 390px and 1280px in loaded, empty, failure and sending states; existing private-message browser and unit tests pass, updated for wording only.

## Risks

- **Moving send recovery onto the bubble loses today's guarantees** (no duplicate send, attempt survives reload, identity change blocks sending). Impact: lost or doubled messages. Earliest validation: run `private_message_chat_recovery`, `private_message_compose.cjs` and `private_message_history_recovery` in the first stage, before any visual change. Mitigation: keep the composer's attempt logic; change only where its state is shown.
- **Wrong not-a-member state on first paint.** Impact: an approved member briefly sees "Messages are for approved members", or a waiting member a guest message. Validation: cold-load browser test per state. Mitigation: reuse cycle 4's account-state decision from browser storage; an approved key without a session shows nothing until sign-in finishes.
- **Visual state racing decryption** (bubble or row changes after the placeholder). Impact: layout jumps and a broken first-paint rule. Validation: layout-stability check while previews and messages decrypt. Mitigation: fixed-height placeholders; only real state changes update after paint.
- **Wording tests across ~20 private-message tests.** Impact: churn hides real regressions. Mitigation: change copy in one stage and keep behavior assertions unchanged.

## Shared component inventory

- `pages/private_message_list.php`, `partials/private_message_conversation_row.php`, `private_message_list.js`, `private_message_list.css`: **extend**.
- `pages/private_message_conversation.php`, `partials/private_message_item.php`, `private_message_conversation.js` (bubbles, day separators and grouped unavailable messages already exist), `private_message_conversation.css`: **extend**.
- `partials/private_message_composer.php`, `private_message_compose.js` (draft, attempt and duplicate-send protection): **reuse** the logic, **extend** the display.
- `private_message_reader.js`, `private_message_seen.js`, `private_message_unread.js`, `message_time.js`: **reuse** unchanged.
- Account states from cycle 4 and the existing background sign-in: **reuse** for the not-a-member page. The account menu's `<details>` disclosure: **reuse** for ···.
- `templates/pages/private_messages.php`: unchanged (not reachable; mailbox routes redirect).

## User flow

1. A member taps Messages: rows show "onthebus · 9:41 · ok the new reply box is way better on my phone", one with "You: thanks, fixed", one with "Couldn't decrypt preview · Retry".
2. They open onthebus: the privacy line at the top, bubbles by day, one "Couldn't verify this message · Details · Retry".
3. They write and press Send: the bubble appears faded, then solid. With the network off, it shows "Not sent · Retry"; they retry and it turns solid.
4. A waiting member opens `/messages`: "Messages are for approved members. You're waiting for approval as mouse…".

## Success criteria

- No ✓, "Decrypting…" list text, "Conversation with" or "Send private message" remains; the privacy line is visible on every conversation.
- Each failure (preview, list load, message, send, unconfirmed send, seen status) has its action next to what failed, and the existing recovery tests pass.
- Each not-a-member state renders its own message on first paint.
- No page overflow or layout jump in any permitted theme at both widths.

*— Claude · 2026-10-29 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-10-29T18:46Z | 1.10 | v1 (whole checklist item 8) / R1 / P2, baseline `main` `b3a5b1621` | Claude, single exposed rater; authored the mockups and checklist; rated before inspecting this cycle's code | Two screens, two panes, picker, send states, not-a-member page; over 1.0, so a split discussion |
| Step 2 handoff | 2026-10-29 | 0.85 | v1.1 (proposed split: this cycle without two panes, picker and scroll loading) / R1 / P2 | Same, after reading the templates, `private_message_compose.js`, `private_message_conversation.js`, the list script and the page controller | Scope change (split) plus new understanding: bubbles, day separators and grouped failures exist; the send attempt logic is reusable |
| Step 2 approved | 2026-10-29 | 0.85 | v1.1 (split accepted) / R1 / P2 | Same | "Approvee Step 2", read as Approved Step 2; no scope change |

Step 1 skipped (not applicable, not zero). R1 hashes verified 2026-10-29. Proposed follow-up `v3/ui_messages_panes`: about 0.55, not summed with this cycle.
