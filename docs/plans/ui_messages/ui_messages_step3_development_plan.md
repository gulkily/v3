> **Feature plan:** [Step 2](./ui_messages_step2_feature_description.md) · [Step 3](./ui_messages_step3_development_plan.md) · [Step 4](./ui_messages_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_messages` ([ledger](../effort_estimation_trial.md#cycle-v3ui_messages)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

Step 2 approved 2026-10-29 ("Approvee Step 2", read as Approved Step 2), including the split: two panes, the member picker and scroll loading go to the follow-up `v3/ui_messages_panes`. No requests since approval.

## Completion Contract

- Normal entry: the Messages tab and a profile's Message button, phone and desktop, as an approved member; `/messages` and `/messages/conversation/{name}` as a guest, a waiting member and an approved key without a session.
- Outcome: the list shows name, time, two-line preview, unread dot and "You:"; a conversation shows the name, the privacy line, bubbles by day and a Send button; a sent message turns solid; a non-member sees their state and next step.
- Recovery: Retry on a failed preview, list load, unreadable message and failed send; Check on an unconfirmed send, also after a reload; seen-status Retry/Reopen next to the composer; no duplicate send, no lost text.
- External: no deployment or server change beyond the not-a-member page; private-message store, API and encryption unchanged.
- Release: every permitted theme on each site with messages, 390px and 1280px, in loaded, empty, failure and sending states; existing private-message tests pass (wording updates only); full suite once at the end.

## Key Risks

- **High risk:** moving send recovery onto the bubble breaks no-duplicate, reload-survival or identity-change guarantees. Validation: `private_message_chat_recovery`, `private_message_compose.cjs`, `private_message_history_recovery` pass in Stage 1 before any other change. Mitigation: `private_message_compose.js` attempt logic stays; only its display moves.
- **High risk:** an approved member is shown "Messages are for approved members", or sign-in loops. Validation: cold-load test per state in Stage 5. Mitigation: shared account-state decision; named keys show only the heading until sign-in answers; one sign-in attempt, then a Retry.
- Layout jumps while previews and messages decrypt. Validation: geometry before and after decryption in Stages 2 and 4. Mitigation: placeholders with the final height; text only replaces them.
- Wording churn across ~20 tests hides behavior regressions. Mitigation: each stage changes its own strings and keeps behavior assertions.

## Stage 1 - Send states on the bubble

**Estimate: 0.18**

- Goal: sending shows a faded bubble that turns solid; failures show "Not sent · Retry" or "Not confirmed · Check" on that bubble, also after a reload.
- Dependencies: none.
- Expected changes: the conversation inserts a pending bubble from the composer's `private-message-sent`/prepare events (new: a `private-message-sending` event with message ID and text); the composer's detached "Check previous send" button and error text move onto the bubble; an unconfirmed attempt restored from the draft renders as its bubble on load.
- Verification approach: the three recovery tests above; new browser checks for failure, uncertain send, reload with an unconfirmed attempt, and identity change.
- Estimate details: driver: event wiring between composer and transcript, restored attempts; uncertainty: how history reload merges a pending bubble with the delivered one.
- Risks or open questions:
  - Impact: a pending bubble and the delivered message both show. Early warning: duplicate IDs in the transcript test. Mitigation: the pending bubble uses the attempt's message ID and is replaced by it.
- Canonical components/API contracts touched: `private_message_compose.js` (events only), `private_message_conversation.js`, `partials/private_message_item.php`; `POST /api/private_messages` unchanged.

## Stage 2 - Conversation reads like a chat

**Estimate: 0.15**

- Goal: name as title with a back link, privacy line at the top, no ✓, day separators instead of per-message time, Send arrow button, hooks.
- Dependencies: Stage 1 (bubble markup settled).
- Expected changes: conversation template header and privacy line; message item drops the ✓ and visible timestamp (kept as `datetime` for the title); composer button and note; `--person` on the page, `data-incoming` on received bubbles, an empty starfield slot when there are no messages (all inert).
- Verification approach: `private_message_chat_layout`, `private_message_history`, `private_message_live_navigation`; copy assertions updated; four themes at both widths with geometry stable during decryption.
- Estimate details: driver: copy and template changes across several tests; uncertainty: low.
- Risks or open questions: none beyond layout jumps (Key Risks).
- Canonical components/API contracts touched: `pages/private_message_conversation.php`, `partials/private_message_item.php`, `partials/private_message_composer.php`, `private_message_conversation.css`.

## Stage 3 - Unreadable messages and conversation recovery

**Estimate: 0.12**

- Goal: "Couldn't verify this message" / "Couldn't decrypt" on the bubble with Details and Retry; "Restart history" under ···; seen-status recovery next to the composer.
- Dependencies: Stage 2.
- Expected changes: reader result kinds map to the two bubble labels with the current explanation under Details; grouped runs keep working; a `<details>` ··· menu holds Restart history; seen-status text and actions shortened.
- Verification approach: `private_message_unavailable`, `private_message_unavailable_seen`, `private_message_seen_recovery`, `private_message_history_recovery`.
- Estimate details: driver: mapping five reader kinds and the grouping code; uncertainty: grouped-run labels.
- Risks or open questions: none new.
- Canonical components/API contracts touched: `private_message_reader.js` result kinds (read only), `private_message_conversation.js`, `private_message_seen.js` (text only), the account menu's `<details>` pattern.

## Stage 4 - Conversation list rows

**Estimate: 0.15**

- Goal: name, time, two-line preview, unread dot, "You:"; placeholder while decrypting; "Couldn't decrypt preview · Retry" per row; one "Couldn't load messages. Retry" line.
- Dependencies: none (parallel to Stages 1–3; done after them to keep commits ordered).
- Expected changes: row partial and list script text/state; list errors merged into one line with Retry (restart stays as its action when the server requires it); unread indicator becomes a dot with an accessible label.
- Verification approach: `private_message_list.cjs`, `private_message_list_browser`, `private_message_unread_ui`, `private_message_unread_api`; geometry stable while previews decrypt; long names and previews at 390px.
- Estimate details: driver: list script states and unread wiring; uncertainty: low.
- Risks or open questions: none beyond layout jumps.
- Canonical components/API contracts touched: `partials/private_message_conversation_row.php`, `private_message_list.js`, `private_message_unread.js`, `private_message_list.css`; `/api/private_messages/conversations` unchanged.

## Stage 5 - Not-an-approved-member page

**Estimate: 0.15**

- Goal: `/messages` and conversation URLs without an approved session say "Messages are for approved members" with the visitor's state and one action; an approved key is signed in and lands on Messages.
- Dependencies: none.
- Expected changes: the controller's 401 renders a messages template (same HTML for everyone) instead of the generic message page; the account page's state decision moves into a shared script used by both pages; guest → "Set up account"; waiting → waiting line; named key → heading only until sign-in answers, then reload into Messages or the waiting line, with Retry on failure.
- Verification approach: new cold-load browser test per state (no key, unnamed guest key, waiting key, approved key without session, failed sign-in); `account_states`, `private_site_auth_contract.cjs`, `background_sign_in` tests still pass.
- Estimate details: driver: shared state extraction and the sign-in continuation; uncertainty: members-only sites, where the shell itself may require sign-in.
- Risks or open questions:
  - Impact: account page regresses after extraction. Early warning: `account_states` in this stage. Mitigation: move code unchanged first, then add the messages caller.
- Canonical components/API contracts touched: `PrivateMessagePageController`, `pages/account_key.php` state script, existing authentication service and background sign-in; no new API.

## Stage 6 - Sites, themes, copy record and closeout

**Estimate: 0.12**

- Goal: release checks and records.
- Dependencies: Stages 1–5.
- Expected changes: none to features; fixes found by the checks; copy record in `docs/plans/ui_copy_audit_checklist.md`; checklist cycle 8 status; Step 4 closeout and ledger.
- Verification approach: every permitted theme on each site with messages at 390px and 1280px in list, conversation, empty, failure, sending and not-a-member states, contact sheets reviewed; no overflow; full suite once; reduced-motion respected for the fade.
- Estimate details: driver: theme × state matrix; uncertainty: theme-specific fixes.
- Risks or open questions: none new.
- Canonical components/API contracts touched: none beyond the above.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-10-29T18:46Z | 1.10 | v1 / R1 / P2, baseline `b3a5b1621` | Claude, single exposed rater | See Step 2 |
| Step 2 handoff | 2026-10-29 | 0.85 | v1.1 (split) / R1 / P2 | Same | See Step 2 |
| Step 3 handoff | 2026-10-29 | 0.85 (reaffirmed) | v1.1 / R1 / P2 | Same, after reading the reader result kinds, conversation events, the account state script and `renderMessagePage` | Stage 5's shared state extraction adds work; Stage 3 reuses existing grouping. Stage estimates S1–S6 0.18/0.15/0.12/0.15/0.15/0.12, not summed; Stage 6 owns shared acceptance |

R1 hashes verified 2026-10-29.

*— Claude · 2026-10-29 UTC · Model: claude-opus-5-5*
