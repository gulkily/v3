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

Name: Private foreground key activity
Purpose: Help recently used same-account devices receive recovery material promptly without starving older devices.
Scenario: Approved sign-in or foreground unread refresh records private coalesced activity. Three recent-target selections alternate with a stable-order fairness selection; background sync and recipient activity never promote a sender's targets.
Sources: [activity and schedule store](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncStore.php), [scheduler](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php).

Name: Bounded history continuation
Purpose: Finish useful recovery during an ordinary visible visit, then stop repeated work.
Scenario: Ten-original, sixteen-attempt background turns yield between batches and honor admission delays. Foreground readers take priority between turns. Settled revision-aware passes use one small response; new relevant data reopens recovery. A recorded submitting key is a verification hint, not proof of the original encrypted signer; bounded fallback preserves other approved signers and existing send acceptance.
Sources: [coordinator](../../public/assets/private_message_history_sync.js), [service](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php), [acceptance](../plans/message_sync_activity/acceptance.txt).

Name: Saved message copies
Purpose: Let a conversation this browser has already read open without decrypting it again.
Scenario: With `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED`, each verified read on a conversation page or Messages list preview is saved in the browser, encrypted with a key the browser generated and cannot export, and bound to the identity fingerprint. The next visit shows those messages before revealing the transcript (and fills list previews before the list request returns), with no fetch or OpenPGP; new messages read live. Only messages the server still lists are looked up, so retracted messages never reappear. A saved copy keeps the check mark it earned when first read. Any storage failure is a miss. Removing the browser identity deletes the copies and their key. This is the approved exception to keeping plaintext in browser memory only.
Sources: [cache](../../public/assets/private_message_cache.js), [reader](../../public/assets/private_message_reader.js), [list](../../public/assets/private_message_list.js), [plan](../plans/dm_instant_load/dm_instant_load_step2_feature_description.md).
