# Private message conversation list Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_conversation_list_step1_solution_assessment.md) · [Step 2](./private_message_conversation_list_step2_feature_description.md) · [Step 3](./private_message_conversation_list_step3_development_plan.md) · [Step 4](./private_message_conversation_list_step4_implementation_summary.md)

## Stage 1 - Open the unified Messages list

- Changes: added viewer-scoped counterpart aggregation before the 25-row limit; authenticated `/messages` and `/api/private_messages/conversations` routes, navigation, initial rows, and empty state. Existing mailbox APIs remain unchanged.
- Verification: `php tests/run.php PrivateMessageStoreTest PrivateMessageMailboxServiceTest PrivateMessagePageControllerTest PrivateMessageApiRoutingTest PrivateMessageReleaseIsolationTest AuthNavigationTest` — 18 passed. Changed PHP files passed syntax checks; `git diff --check` passed.
- Notes: cursors retain an insertion watermark, its message anchor, and the last ordering pair. Snapshot replay (`page_cursor`) keeps initial metadata and fetched envelopes consistent. A 60-counterpart fixture verifies ties, both directions, repeat messages, intervening arrivals, retry, and foreign-cursor rejection. No schema migration or external service change; runtime browser verification follows in Stage 6.

## Stage 2 - Load all conversations with recovery

- Changes: added a list browser controller using the shared row template, serialized Load more, cursor-preserving retry, expired-list restart, end/empty states, and refresh on restoration from browser history cache.
- Verification: `php tests/run.php PrivateMessageListTest PrivateMessageStoreTest PrivateMessagePageControllerTest` — 8 passed. Node syntax and `git diff --check` passed. Browser-controller tests cover concurrent loads, three pages, duplicate prevention, failed-request retry, exhausted lists, expired cursors, and history refresh.
- Notes: initial envelope retrieval replays the server-rendered page cursor; preview rendering is added in Stage 3. No deployment or migration required.

## Stage 3 - Verify previews and format local times

- Changes: reused `decryptEnvelope` for one-line list previews and the existing key resolver, with in-memory key-request reuse, verified-only text rendering, visible failure explanations, and per-row retry. Added a reusable local-time formatter retaining exact ISO values and descriptive hover/accessibility text.
- Verification: `php tests/run.php PrivateMessageListTest PrivateMessageReaderTest PrivateMessagePageControllerTest` — 6 passed. Both changed/new browser scripts passed syntax checks; `git diff --check` passed. Tests cover mixed signatures, failed key lookup/retry, safe text rendering, appended rows, no persistent preview writes, and local-day boundaries.
- Notes: envelopes come from the same snapshot response as row metadata; failed previews retain conversation navigation. Existing transcript behavior is unchanged. Browser layout verification remains in Stage 6.

## Stage 4 - Start conversations from Messages

- Changes: added New message with labeled username input, directory suggestions, direct entry, approved-key validation, self/invalid-recipient feedback, retained input, and an empty-state action. Eligible usernames route to the existing encrypted conversation composer.
- Verification: `php tests/run.php PrivateMessageListTest PrivateMessagePageControllerTest PrivateMessageMailboxServiceTest PrivateMessageComposerTest ApprovedUserKeyResolverTest` — 16 passed. JavaScript syntax and `git diff --check` passed. Tests verify suggestions exclude self, selector focus, normalization, invalid/self/key-failure recovery, and successful conversation navigation; existing draft/key-coverage behavior remains covered.
- Notes: the directory query includes approved users without visible posts, despite its old empty-state copy. Suggestions are not an authorization boundary; the existing conversation/send services validate approved recipient keys again. Full browser sending is checked in Stage 6.

## Stage 5 - Complete navigation and unavailable-conversation recovery

- Changes: Inbox/Sent pages now redirect to Messages while their APIs retain existing contracts. Conversation headings link to composite-user profiles, with a Messages back link; unavailable conversations include the same recovery destination. Added scoped responsive list styling, full-row links, visible focus, touch-sized buttons, and wrapping error text.
- Verification: `php tests/run.php PrivateMessagePageControllerTest PrivateMessageApiRoutingTest PrivateMessageComposerTest PrivateMessageListTest AuthNavigationTest` — 11 passed. Changed PHP syntax and `git diff --check` passed. Controller fixtures cover legacy redirects, unchanged API bounds, counterpart links, lost eligibility, retained historical rows, and unavailable-conversation recovery.
- Notes: the generic message template accepts an optional escaped action link; other callers retain their existing rendering. Visual, keyboard, and narrow-viewport checks are performed in Stage 6.

## Stage 6 - Validate and hand off Cycle 1

- Changes: added a repeatable isolated HTTP/browser fixture and journey, documented the list API at `/api/`, expanded release-isolation coverage, and completed the master checklist's Cycle 1 items. Organized the four FDP artifacts in this folder and updated the plan index.
- Changes: final validation identified same-second ordering as a risk for random message IDs. The new list now uses insertion order to break timestamp ties, including snapshot continuation; a regression test proves the latest reply wins even when its ID sorts earlier. Existing mailbox/transcript API ordering is unchanged.
- Verification: `php tests/run.php PrivateMessageDatabaseConfigTest PrivateMessageApiRoutingTest PrivateMessageComposerTest PrivateMessageEnvelopeTest PrivateMessageMailboxServiceTest PrivateMessageListTest PrivateMessagePageControllerTest PrivateMessageReaderTest PrivateMessageReleaseIsolationTest PrivateMessageStoreTest ApprovedUserKeyResolverTest AuthNavigationTest OfflineNavigationWorkerTest ProfilePresentationContentTest ProfileThemePresentationTest` — 47 passed.
- Verification: `node tests/browser/private_message_list_browser.mjs` — passed with generated identities and isolated SQLite/session state. Exercised normal navigation, real signed/encrypted first send and reply, failed-send draft recovery, 60 counterparts across three pages, arrivals during pagination, failed-load retry, key-lookup retry, signature rejection, lost recipient eligibility, third-party isolation, malformed cursors, method rejection, legacy redirects, no-store headers, keyboard focus, and a 375-pixel viewport without horizontal overflow. No browser script errors occurred.
- Verification: inspected desktop and mobile screenshots; local artifacts are in `/tmp/private-message-browser-JAkNXA/` (ephemeral). PHP/JavaScript syntax, diff whitespace, and local plan links checked. Browser harness uses the existing `playwright-core` dependency; set `CHROMIUM_PATH` if Chrome is not at `/opt/google/chrome/chrome`.
- Notes: no database migration, production deployment, or external-service mutation. Browser tests send only within their generated local fixture. The original proposal is preserved; unrelated `todo.txt` edits are excluded. Cycle 1 is complete; chat refinements, older transcript loading, and unread tracking remain in Cycles 2–4.
