# Private messaging usability master checklist

Deliver the improvements in [the messaging proposal](../../improve_messaging.txt)
through four independently usable releases: find and start conversations, read
and reply comfortably, retrieve older history, and track unread conversations.
Use this checklist to track coverage across the releases and the
[Feature Development Process](../fdp/FEATURE_DEVELOPMENT_PROCESS.md) to plan and
implement each release.

This is a coordination checklist. Individual FDP requirements, development plans,
and implementation approvals remain separate. Cycles 1–3 are complete; Cycle 4 is pending.
Check items off when their acceptance criteria are verified, and link the relevant
FDP artifacts and verification evidence here as each cycle progresses.

## Release sequence

| Cycle | User outcome | Starting point | Status |
| --- | --- | --- | --- |
| 1 | Find a conversation or start one from Messages | [Plan](./private_message_conversation_list/private_message_conversation_list_step3_development_plan.md) · [Implementation and verification](./private_message_conversation_list/private_message_conversation_list_step4_implementation_summary.md) | Complete |
| 2 | Read and reply comfortably without reloading | [Plan](./private_message_chat_refinement/private_message_chat_refinement_step3_development_plan.md) · [Implementation and verification](./private_message_chat_refinement/private_message_chat_refinement_step4_implementation_summary.md) | Complete on feature branch; awaiting merge review |
| 3 | Retrieve messages older than the initial history window | [Plan](./private_message_history_step3_development_plan.md) · [Implementation and verification](./private_message_history_step4_implementation_summary.md); Step 1 skipped | Complete on `feature/private-message-history`, including unmerged Cycle 2; awaiting merge review |
| 4 | See reliable unread indicators and counts | Step 1 to settle read semantics and persistence | Pending |

Follow this order and reuse the components and contracts established in earlier
cycles. Unread indicators are deliberately deferred from cycle 1 to cycle 4;
older-history retrieval is deferred to cycle 3. Keep each FDP slice within a day
or eight development stages; split an oversized cycle into usable releases during
planning. Add links only when the corresponding artifacts exist.

## Baseline and shared requirements

The [message store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php)
limits each legacy mailbox and initial conversation window to the newest 25 messages.
Cycle 3 adds bounded Load older retrieval for conversation history. Message bodies are encrypted on the
server, and the [reader](../../public/assets/private_message_reader.js) decrypts
and verifies them in the browser. The message schema has no read-tracking fields.

- [x] Establish a baseline for successful sending, failed sending, draft recovery,
  decryption failure, and missing or invalid signatures using controlled test data.
  The original usability review did not exercise these paths. Cycle 1's focused
  suites and isolated browser journey provide the baseline.
- [ ] Preserve browser encryption, signing, approved-key coverage, and authorization
  boundaries. Keep preview plaintext and verification results out of server
  storage, logs, and persistent browser caches.
- [ ] Reuse shared message rendering, timestamp formatting, reader, and composer
  behavior across list, conversation, profile, and aggregate-user entry points.
- [ ] Verify keyboard access, accessible names, readable warning states, and mobile
  layouts for each changed flow. Direction and verification must remain
  understandable without color alone.
- [ ] Preserve private-message exclusion from static releases and offline snapshots.

## Cycle 1 Find and start conversations

Outcome: from normal navigation, a user can find an existing conversation or
choose a recipient and send their first message using the existing composer.

Evidence: [Cycle 1 implementation summary](./private_message_conversation_list/private_message_conversation_list_step4_implementation_summary.md). Shared requirements above remain continuing obligations for later cycles.

- [x] Assess and approve the canonical Messages route and treatment of existing
  Inbox and Sent URLs. If Sent remains, make it a filter of the same conversation
  list with a visible selected state and matching accessible state.
- [x] Replace individual mailbox cards with one row per counterpart, ordered by
  latest activity across sent and received messages. Aggregate before pagination
  so the old 25-message caps cannot hide counterparts.
- [x] Show the counterpart username, a one-line latest-message preview, and its
  time. Make the whole row a keyboard-accessible link to the conversation.
- [x] Decrypt and verify the latest envelope locally for previews; provide clear
  loading, unavailable-key, and verification-failure fallbacks without displaying
  unverified plaintext as a trusted preview.
- [x] Render list timestamps in the viewer's local time with friendly text,
  exact values in `<time datetime>`, and exact timestamps available on hover.
- [x] Add bounded list pagination or incremental loading, including loading,
  end-of-list, error, and retry states. Use deterministic ordering for tied times.
- [x] Add New message with username autocomplete from the user list; validate
  recipient eligibility and route to that user's conversation. Explain invalid,
  self, or unavailable recipients without losing the entered username.
- [x] Add the "No messages yet" empty state with the New message action.
- [x] Align Messages navigation, page titles, and headings. Add a clear back link
  from conversations and link the counterpart heading to their user profile.
- [x] Verify more than one page of counterparts, sent-only and received-only
  conversations, an empty mailbox, unavailable previews, and starting a first
  conversation through the normal Messages entry point.

Completion: users can reach every conversation through the list and start a new
one. No unread badge is promised in this release.

## Cycle 2 Read and reply comfortably

Outcome: opening a conversation presents recent messages clearly and makes
replying convenient, with recoverable failures and no successful-send reload.

Evidence: [Cycle 2 implementation summary](./private_message_chat_refinement/private_message_chat_refinement_step4_implementation_summary.md). Browser checks include constrained mobile viewports and zoom; physical-device keyboard testing remains a manual follow-up.

- [x] Replace tall cards and repeated To, From, and Sent labels with compact
  directional messages using alignment, background, and accessible sender cues.
  Group consecutive messages by sender and add local-date separators.
- [x] Show small friendly local timestamps beside messages, retaining exact
  `<time datetime>` values and exact timestamps on hover.
- [x] Compare the same representative transcript and viewport before and after;
  aim for roughly three to four times as many short messages per screen while
  preserving legibility and handling long or multiline messages.
- [x] Settle scrolling and composer placement in planning. Show the newest message
  after decryption changes layout, keep replying easy, avoid interrupting someone
  reading earlier messages, and avoid opening the mobile keyboard automatically.
- [x] Remove the redundant composer heading and visible Message label in the
  conversation context while preserving an accessible textarea name. Start at
  two or three rows, grow with content, and show a useful placeholder.
- [x] Use Ctrl/Cmd+Enter to send, with Enter and Shift+Enter inserting newlines.
  Keep the Send button and account for input-method composition.
- [x] Place the encryption explanation on a single muted line beneath the box.
- [x] Append a successfully sent message in place using the server-confirmed
  identity and timestamp; keep drafts recoverable on failure and avoid clearing
  newer text typed while a send is in progress.
- [x] Validate retry after a request whose server outcome is uncertain; recover
  without duplicate messages or a misleading success state.
- [x] Correct "Siganture verified" to "Signature verified" in tooltip and
  accessible text wherever it remains. Keep verified marks quiet and make missing
  or invalid signatures prominent with an explanation.
- [x] Show readable per-message decryption errors with a working retry action;
  a failed message must not prevent other messages from being read.
- [x] Verify successful append, failed-send draft recovery, uncertain-send retry,
  mixed verification results, decryption retry, mobile scrolling, keyboard use,
  and existing profile and aggregate-user composer behavior.

Completion: users can read and reply through the normal conversation flow without
a successful-send reload, and can understand and recover from relevant failures.

## Cycle 3 Retrieve older history

Outcome: users can read beyond the newest 25 messages without losing their place.

Evidence: [Cycle 3 implementation summary](./private_message_history_step4_implementation_summary.md). Isolated browser checks cover real encrypted history, delayed decryption/navigation, mobile/zoom, retry/restart, and concurrent sends. Physical-device keyboard testing remains a manual follow-up.

- [x] Add bounded cursor pagination for authorized conversation history, retaining
  chronological display and a deterministic tie-breaker for equal timestamps.
- [x] Add Load older with loading, exhausted-history, error, and retry states.
- [x] Prepend older messages while preserving the visible reading position after
  decryption and layout changes. Keep sender groups and date separators correct
  across page boundaries.
- [x] Reuse the reader for newly loaded messages and preserve per-message
  verification, error, and retry behavior.
- [x] Verify at least three pages of history, equal timestamps, arrivals between
  page requests, failure and retry, no skipped or duplicate messages, and exclusion
  of another user's conversations.

Completion: all stored history in the authorized conversation's opening snapshot
is reachable with bounded requests and stable reading position. Refreshing or an
explicit recovery restart opens a fresh snapshot; this is not live polling.

## Cycle 4 Track unread conversations

Outcome: conversation indicators and the navigation count consistently tell the
viewer what needs attention.

- [ ] Decide whether the navigation badge counts messages or conversations, when
  received messages become read, and how previewing, decryption failures, and
  background tabs affect read state. Record these semantics before implementation.
- [ ] Assess persistence and ownership of read positions, including multiple
  browser identities for a username, multiple devices, and initial treatment of
  existing messages. Prefer existing storage where appropriate; justify any
  schema change in the FDP assessment.
- [ ] Implement authenticated read-state retrieval and updates scoped to the
  viewer. Prevent stale updates from moving the read position backward or marking
  messages arriving after the viewed boundary as read.
- [ ] Add unread indicators to conversation rows and the agreed count to the
  top-navigation Messages item, with accessible text and a clear zero state.
- [ ] Keep list, conversation, and navigation state consistent after reading;
  define how updates become visible across pages, tabs, and devices.
- [ ] Verify new arrivals, own sent messages, refresh persistence, concurrent
  arrivals and reads, multiple browsers, failed updates, and unauthorized access.

Completion: indicators and counts agree with the approved semantics and remain
correct across refreshes and supported browser or device transitions.

## Deferred follow-ups

- [ ] Reduce the size and prominence of the per-message decryption-error/retry
  widget. The user reports that “This encrypted message could not be decrypted
  with the saved private key” plus “Retry reading message” is large and distracting,
  especially when repeated across history. Explore compact unavailable-message
  placeholders with optional details and a retry only where useful; retain clear
  security warnings for invalid or missing signatures. Verify with many unreadable
  messages on desktop and mobile. Deferred by request, not part of Cycle 3.
- [ ] Separately assess historical-message access after adding or changing keys.
  Approving a new key does not retroactively encrypt existing envelopes to it;
  retrying unchanged ciphertext with the same unsuitable key cannot resolve that
  mismatch. The generic error alone does not establish the cause for any specific
  message. Consider recovery using retained older keys or a future authorized
  mechanism, without promising automatic recovery or weakening verification.
  Plan this explicitly later; no key-management redesign is approved here.

## Final acceptance

- [ ] Link each completed cycle's FDP artifacts and verification evidence here.
- [ ] Reconcile every request in the original proposal with a completed item or
  an explicitly agreed deferral; do not mark deferred work complete.
- [ ] Exercise the complete flow: Messages, New message, send, return to list,
  open conversation, reply, load older history, and observe unread updates.
- [ ] Confirm focused automated checks and browser checks cover the changed
  behavior, encryption and authorization boundaries, accessibility, and recovery.

Next action: review completed Cycle 3 for merge, then assess Cycle 4's unread semantics in FDP Step 1 when requested. Cycle 3 includes unmerged Cycle 2; neither has been pushed or merged into main here. The decryption-widget and historical-key follow-ups remain deferred.
