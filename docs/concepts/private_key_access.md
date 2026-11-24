# Private key access

Name: Private access
Purpose: Let a key read its account's private messages only when the account itself vouches for it; approval alone lets a key post.
Scenario: A key has private access when it is approved and active and one of four things holds. It is a root seed or the username's first key (a `new_account` receipt under the current name; for legacy grants without receipts, the earliest key without one). A same-username key with private access approved it. An operator approved it, as the receipt's `actor_is_operator` records at approval time. Or a confirm record names it. Every other approved key is public only. Retired keys still vouch as links in a chain but have no access themselves. With `FORUM_PRIVATE_KEY_ACCESS_ENFORCED` on, new messages are encrypted only to keys with access, history sync neither targets nor serves public-only keys, and their mailbox answers 403 `reason: public_only`. With it off, messaging trusts plain approval again.
Sources: [check](../../src/ForumRewrite/Security/PrivateKeyAccess.php), [recipient keys](../../src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php), [mailbox](../../src/ForumRewrite/Messaging/PrivateMessageMailboxService.php), [history sync](../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php), [plan](../plans/private_key_access/private_key_access_step3_development_plan.md).

Name: Confirm record
Purpose: Give one approved, public-only key private access by a signed act of its own account or an operator.
Scenario: A same-username key with access (`member`) or an active operator (`operator`) prepares, signs and submits the record. It is kept in Git with its signature and receipt, survives rebuilds, and is left out of instance imports like retirements. A repeat of the accepted request is a no-op. Readers check the confirmer's authority again, so a confirm from a key that has since lost access gives none.
Sources: [record](../../src/ForumRewrite/Security/PrivateAccessConfirmation.php), [policy](../../src/ForumRewrite/Security/PrivateAccessConfirmationPolicy.php), [spec](../specs/private_access_confirmation_record_v1.md).

Name: Finding a waiting key
Purpose: Make a waiting key visible from both ends, so nobody has to know to look.
Scenario: The public-only device's Messages says "This device can't read messages yet", with a short code (the fingerprint's last 12 hex digits) and Show QR. The QR opens `/account/confirm/<fingerprint>`. The account's devices with access see a card on Unread and Messages: the codes, Confirm, and Not me, which leads to the key's User Access page to retire it. The card also doubles as the alarm when a key appears under the name that the owner did not add. Operators see every waiting key in Tools → User Access. `./v3 approval private-access-report` lists every key's reason from the shell.
Sources: [device line](../../templates/pages/private_message_access.php), [card](../../templates/partials/private_access_waiting.php), [confirm page](../../templates/pages/private_access_confirm.php), [User Access](../../templates/pages/user_access.php), [report](../../scripts/private_access_report.php).
Boundary: Agents have no Quill confirm command yet ([PF-20261124-F01](../feedback/items/PF-20261124-F01.txt)). A local import does not carry confirmations.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
