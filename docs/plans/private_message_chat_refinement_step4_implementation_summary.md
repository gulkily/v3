# Private message chat refinement Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_chat_refinement_step1_solution_assessment.md) · [Step 2](./private_message_chat_refinement_step2_feature_description.md) · [Step 3](./private_message_chat_refinement_step3_development_plan.md) · [Step 4](./private_message_chat_refinement_step4_implementation_summary.md)

## Stage 1 - Safe send retries

- Changes: atomic insert-or-confirm acceptance matches sender username/identity, recipient, and exact ciphertext; conflicts never replace messages or disclose their contents. Accepted attempts return original metadata even after recipient key eligibility changes. Shared composers retain ciphertext for retries.
- Verification: `php tests/run.php PrivateMessageMailboxServiceTest PrivateMessageStoreTest PrivateMessageComposerTest PrivateMessageApiRoutingTest` — 17 passed, including four concurrent SQLite writers, replay/conflict/authorization/key-eligibility cases, and exact-ciphertext retries. Syntax and whitespace checks passed.
- Notes: no schema or response-shape changes. Reload recovery and draft separation follow in Stage 2. Planning commit: `3ec3ac74`.

## Stage 2 - Draft and attempt recovery

- Changes: sender/recipient-scoped drafts retain independent encrypted attempts before dispatch. Checking a previous send reuses its ID/ciphertext across reloads, preserves newer edits, and prevents edited text from replacing an unresolved attempt. Identity changes block dispatch; legacy drafts are adopted only for the matching saved username with a delivery warning. Storage failures explicitly warn to keep the page open.
- Verification: `php tests/run.php PrivateMessageComposerTest PrivateMessageMailboxServiceTest` — 11 passed. The new Node fixture covers pending edits, failed-response reload/retry, separate IDs for newer content, account/key isolation, legacy migration, and unavailable storage. JavaScript/PHP syntax and whitespace checks passed.
- Notes: only existing draft plaintext is persisted; attempt recovery adds ciphertext and metadata, not transcript plaintext or verification caches. Other composer entry points retain their existing navigation behavior. Resolve the previous attempt before dispatching an edited draft.

## Stage 3 - Isolated read recovery

- Changes: individual message retries refresh key lookup, retain their encrypted envelope in memory, and serialize concurrent reads. Failed loads can refetch; failures never expose plaintext. Added initial-reader settlement notification, supplied-envelope rendering, and corrected verification labels in conversation/legacy templates.
- Verification: `php tests/run.php PrivateMessageReaderTest PrivateMessageListTest PrivateMessagePageControllerTest` — 6 passed. Tests exercise real signed/unsigned encryption, unavailable/wrong keys, invalid signatures, isolated successful retry, concurrent retry deduplication, supplied envelopes, and zero persistent plaintext writes. JavaScript/PHP syntax and whitespace checks passed.
- Notes: retry never bypasses signature checks. Initial settlement includes failed messages so one failure cannot prevent the conversation from becoming usable; retries do not emit a second initial-settlement event.

## Stage 4 - Compact conversation presentation

- Changes: shared message partial/template, directional bubbles with accessible sender attribution, consecutive-sender grouping, local-date separators, and shared friendly/exact timestamps. Conversation timestamp ties now follow insertion order, consistent with the list. New styles/scripts are wired through the canonical page controller/renderer.
- Verification: `php tests/run.php PrivateMessageStoreTest PrivateMessagePageControllerTest PrivateMessageReaderTest` — 12 passed. `PRIVATE_MESSAGE_DENSITY_BASELINE=1 node tests/browser/private_message_list_browser.mjs` passed before and after presentation changes, including real encryption and navigation. At identical 1100×800 viewports, representative short-message cards decreased from 154px to 45.98px (3.35× card density); screenshots inspected. Syntax and whitespace checks passed.
- Notes: baseline artifacts `/tmp/private-message-browser-N8efOi/`; compact artifacts `/tmp/private-message-browser-oaV05m/` (ephemeral). Composer sizing and scrolling are intentionally unchanged until Stage 6. No database migration.

## Stage 5 - Confirmed inline replies

- Changes: composer validates acknowledgment identity/recipient/time and emits a confirmation event. Conversation integration clones the canonical shell, deduplicates message IDs, updates groups/empty state, and passes retained ciphertext through shared decryption/verification. Other composer navigation is unchanged; delivery and read failure remain distinct.
- Verification: `php tests/run.php PrivateMessageComposerTest PrivateMessageReaderTest PrivateMessagePageControllerTest` — 9 passed, including malformed-acknowledgment draft retention. `node tests/browser/private_message_list_browser.mjs` passed with real first-send/follow-up encryption, a persistent document marker proving no reload, and duplicate-confirmation deduplication. Artifacts: `/tmp/private-message-browser-sCEZoh/`. JavaScript syntax and whitespace checks passed.
- Notes: no optimistic plaintext or verification marks. Confirmed envelopes remain available for isolated read retry; older recovered messages insert chronologically rather than becoming the newest reply.

## Stage 6 - Composer and reading position

- Changes: accessible three-row growing composer, muted encryption note, conversation-only removal of redundant labels/headings, and Ctrl/Cmd+Enter with composition protection. Sticky positioning falls back inline for constrained/zoomed viewports. Initial settlement reveals latest unless navigation has begun; sends preserve earlier reading position. Latest-message control remains within the composer, without autofocus.
- Verification: 9 focused composer/reader/page checks passed. `node tests/browser/private_message_list_browser.mjs` passed with Enter/Shift+Enter, Ctrl+Enter, Meta+Enter, composition protection, long-draft height limits, 375px viewport, reduced-height fallback, 2× page-scale fallback, visible latest control, no initial autofocus, and sending while reading earlier. Syntax/whitespace checks passed. Screenshots inspected; final artifacts `/tmp/private-message-browser-qZ7xNW/`.
- Notes: browser viewport/zoom simulations passed; a physical mobile keyboard was not available. Visual inspection caught and fixed a covered latest-message button. No automatic keyboard opening or focus transfer is used.
