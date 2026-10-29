> **Feature plan:** [Step 2](./ui_messages_step2_feature_description.md) · [Step 3](./ui_messages_step3_development_plan.md) · [Step 4](./ui_messages_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_messages` ([ledger](../effort_estimation_trial.md#cycle-v3ui_messages)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

## Step 4 entry

- Step 3 approved 2026-10-29 ("Approved Step 3"). Branch `feat/ui-messages` from `main` at `b3a5b1621`; planning commit `b129c36fa`.
- Whole-cycle projection **0.85 reaffirmed** at entry; scope v1.1 (split) / R1 / P2 unchanged. Claude, single exposed rater. Stage originals S1–S6 0.18/0.15/0.12/0.15/0.15/0.12 are in the [Step 3 plan](./ui_messages_step3_development_plan.md), copied into each stage below without re-rating.
- Enhancement requests: none.

*— Claude · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 1 - Send states on the bubble

**Original estimate: 0.18**

**Retrospective stage work: 0.22**

- Changes:
  - `private_message_compose.js`: attempt logic unchanged. In a conversation (`data-mailbox="conversation"`) the composer no longer writes sending/sent/unconfirmed text or shows "Check previous send"; it reports `private-message-sending` and `private-message-send-failed` (message ID, text, unconfirmed or refused, reason) and exposes `ForumPrivateMessageComposer.controls(root)` (`pending`, `check`, `resend`). A server answer with an error marks the failure as refused ("Not sent"); no answer stays "Not confirmed". The profile page composer keeps its text feedback.
  - The saved attempt still holds only its ciphertext; the message text stays in memory (an existing test guarantees this, and caught a first draft that saved it).
  - `private_message_conversation.js`: a faded outgoing bubble (`data-pending-message-id`, `is-pending`) appears at once and is replaced in place by the delivered message once it has been read (`is-arriving` keeps the delivered copy out of view meanwhile). Failures show "Not sent · Retry" (resends the same attempt, so never twice) or "Not confirmed · Check" on that bubble, with the server's reason underneath; a check shows "Checking…". After a reload, a restored unconfirmed attempt marks its delivered copy if the server has it, or an outgoing bubble without text otherwise. "Latest message" also reaches a sending bubble.
  - `private_message_conversation.css`: faded, status and reason styles; the fade stops under reduced motion.
- Verification:
  - `private_message_list_browser.mjs`, which runs the chat layout, chat recovery, history, history recovery, unavailable, seen and feature-flag modules: passes, twice. Chat recovery now checks "Not confirmed" on the message (no separate notice), Check after a reload with no duplicate, a refused send shown as "Not sent" with its reason, Retry faded then replaced by exactly one delivered copy. Feature flag checks Check on the delivered copy after re-enabling.
  - `php tests/run.php` for the composer, page controller, list, reader, feature flag and release isolation tests: 18/18 (`private_message_compose.cjs` passes under `PrivateMessageComposerTest`). `private_message_live_navigation.mjs` passes.
  - Test updates beyond this stage's assertions: waits for a sent reply now target the delivered copy (the faded copy shows the text at once) in five modules.
- Notes:
  - Pre-existing, fixed only where needed to reach this stage's checks: the list test still used the old Users link and expected composers on `/user/<name>` (cycle 7 replaced them with a Message button); `checkHistory` clicked the Messages tab after it had scrolled away.
  - Pre-existing, not fixed: history scroll anchoring moves 8.8px when the last older page is short and the page is at the top (reproduced on clean code by adding one message; this stage's test keeps the original message count). `private_message_history_sync_browser.mjs` fails at `private_message_operator_flag.mjs:133` on clean code as well.
  - Earlier "exit 0" runs of the module files were empty: they only export checks for the list test.
- Estimate details: original scope as planned; retrospective higher for the test-harness repairs and the "Checking…" state found during verification (new understanding, not scope).

*— Claude · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 2 - Conversation reads like a chat

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes:
  - `pages/private_message_conversation.php`: a header card with a back chevron (accessible name "Back to Messages"), the person's name as the title (linking to `/user/<name>`) and "Only you and {name} can read these, on your approved devices." always visible under it. Removed "Conversation with". Empty state "No messages with {name} yet." with an empty starfield slot. The browser tab title is the name.
  - `partials/private_message_composer.php`: in a conversation, an arrow Send button (accessible name "Send") after "Latest message"; on the profile page, a "Send" text button and the same privacy line in place of "Encrypted to all approved keys." Removed "Send private message".
  - `partials/private_message_item.php`: sender and time are visually hidden (still read out, time keeps its full title); the ✓ stays in the markup for the reader but is never shown in a conversation.
  - Day separators read "Today", "Yesterday" or a short date (`ForumMessageTime.day`, full date as the title), replacing the full weekday dates.
  - Hooks (inert): `data-person` and `--person` (a hue from the name) on the conversation, `data-incoming` on received messages, `data-starfield-slot` in the empty state.
- Verification:
  - New `tests/browser/private_message_redesign.mjs`: Light, Word97, Chicago and Midnight at 390px and 1280px. Checks the title, back link, privacy line, no "Conversation with", no visible ✓, separators Oct 26 / Yesterday / Today, `data-incoming` and outgoing classes, `--person`, the Send button, header position unchanged while messages decrypt, no overflow with a long unbroken word, no page errors. Passes; contact sheets reviewed.
  - `private_message_list_browser.mjs` (all its modules, "Send private message" → "Send"): passes. One run failed in `checkSeenWindows` (Messages tab unread count read before the page loaded); the rerun passed with no change, so it is recorded as flaky. `private_message_live_navigation.mjs` passes.
  - `php tests/run.php PrivateMessageComposerTest PrivateMessagePageControllerTest PrivateMessageListTest LocalAppSmokeTest`: 162/163, the one failure is the long-standing archive filename test.
- Notes:
  - "All history loaded." still shows at the top of a short conversation; it is a routine success notice and goes in Stage 3 with the other history controls.
  - Outgoing bubbles keep the existing `--button-alt-bg` colour; the accent colour from the mockup is the theme's job (Phase 3).
- Estimate details: as planned.

*— Claude · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 3 - Unreadable messages and conversation recovery

**Original estimate: 0.12**

**Retrospective stage work: 0.12**

- Changes:
  - `private_message_reader.js`: a message that can't be read shows one line on the bubble ("Couldn’t verify this message", "Couldn’t decrypt this message" or "Couldn’t load this message"), the reader's full explanation under Details, and Retry beside it (accessible name "Retry reading message"). Before, undecryptable messages used "Message unavailable · Details" with Retry inside the disclosure and other failures showed the raw error. While decrypting, a quiet placeholder replaces "Decrypting and verifying message..." (the text stays for screen readers; the pulse stops under reduced motion).
  - Grouped runs read "Couldn’t decrypt N messages · {sender} · {times}" (was "N messages unavailable").
  - History: "Restart history" lives under a ··· menu, which opens itself when a restart is required so the recovery stays in view. Progress and success ("All history loaded.", "N older messages loaded.") are announced but not shown; failures stay visible.
  - Seen status: "Couldn’t mark this conversation as seen." with Retry (accessible name "Retry seen status"), or "…Copy your draft, then reopen it." with Reopen conversation; it stays beside the composer.
- Verification:
  - `private_message_list_browser.mjs` (all modules, including unavailable groups, history recovery with Restart history, seen recovery and seen windows): passes. Wording assertions updated; checks that read the explanation now read it under Details.
  - `tests/browser/private_message_redesign.mjs` extended: the unverifiable message shows its line, Details opens the reason, Retry is visible, no visible history notice, Restart history not shown until needed. Four themes, two widths: passes; contact sheet reviewed.
  - `php tests/run.php PrivateMessageReaderTest PrivateMessageComposerTest PrivateMessagePageControllerTest`: 11/11 after updating the reader's expected label and the history-controls markup check.
- Notes:
  - In Word97, Chicago and Midnight, Retry keeps those themes' button frame; it reads as their button style.
- Estimate details: as planned.

*— Claude · 2026-10-29 UTC · Model: claude-opus-5-5*
