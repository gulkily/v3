# Participant mailbox export

Purpose: Let a signed-in member take their own private mail, still encrypted, to another clone without involving the operator.
Scenario: `GET /api/private_messages/export` returns the member's sent and received envelopes, oldest first, 25 per page, with read flags and the history-sync transfers addressed to the member's keys. A plain `after_time` and `after_id` position continues the export. The local development page's messages pull is the first client.
Boundary: Approved members only, behind the private messaging feature flag. The account comes from the session alone. Another member's conversations and transfers never appear. Pages cap ciphertext at 2 MiB, and a sync-storage failure fails the page instead of silently leaving transfers out. Nothing is decrypted on the server.
Sources: [store page](../../src/ForumRewrite/Messaging/PrivateMessageStore.php), [service](../../src/ForumRewrite/Messaging/PrivateMessageMailboxService.php), [transfers](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncStore.php), [route](../../src/ForumRewrite/Http/PrivateMessageApiController.php), [export checks](../../tests/PrivateMessageExportTest.php).
