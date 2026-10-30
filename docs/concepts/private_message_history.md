# Private-message history

Name: History recovery
Purpose: Let approved devices read older originals using retained encrypted access from eligible devices.
Scenario: A newly approved device visits Messages, validates a donor transfer and the original signature, and can still read after a reload. Private keys and plaintext stay browser-side.
Sources: [service](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php), [browser crypto](../../public/assets/private_message_history_crypto.js), [operations and API](../plans/private_message_history_sync/private_message_history_sync_rollout.md).

Name: Sender assistance
Purpose: Let original senders contribute access to their outgoing messages for approved recipient keys.
Scenario: A sender visits an ordinary page and supplies encrypted access; subsequent combined checks omit that donor's retained contribution without consulting recipient read or recovery receipts. Another donor and explicit legacy repair remain possible.
Sources: [service](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php), [contribution store](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncStore.php).

Name: Messaging history eligibility
Purpose: Avoid dedicated background history synchronization for accounts with no messages.
Scenario: An unused account browses quietly. Its first send or receipt changes the next unread response and enables synchronization on ordinary refresh, including on a new device. Eligibility is derived account history, not membership or a browser-local usage marker.
Sources: [message store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php), [coordinator](../../public/assets/private_message_history_sync.js).

Name: Unread status
Purpose: Report unread conversations and supply account history eligibility through the existing authenticated status request.
Scenario: Navigation and conversation lists refresh the same unread state; the coordinator shares validated identity-bound eligibility with history sync without adding another discovery request. Unread/read semantics and existing retry controls are retained.
Sources: [unread coordinator](../../public/assets/private_message_unread.js), [read state](../../src/ForumRewrite/Messaging/PrivateMessageReadState.php).
