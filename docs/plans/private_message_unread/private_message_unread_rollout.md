# Private message unread state rollout and rollback

Related: [implementation and verification](./private_message_unread_step4_implementation_summary.md) · [approved plan](./private_message_unread_step3_development_plan.md).

## Enable tracking

Deploy server code and browser assets together, retiring old workers before enabling the new version. Take a consistent backup of the configured private-message SQLite database first; it must remain outside public/static/offline artifacts.

The first new-version `PrivateMessageStore` initialization creates two private metadata tables: `private_message_tracking` (one baseline and receipt-signing secret) and `private_message_seen` (username/counterpart progress). A serialized transaction records the latest existing message insertion as the baseline exactly once. All existing messages are treated as seen; new deliveries through the new store initialize tracking before insertion and can become unread. Empty stores use an empty baseline. Reopening the store or using another identity/device does not reset it. Envelopes and message columns are unchanged.

Verify in a controlled environment: history starts seen, two new incoming conversations show count 2, and presenting one current conversation shows count 1 after acknowledgment and refresh on another device. Check hidden tabs, mixed read failures, a later/backdated arrival, and a lost acknowledgment retry. Preserve no-store responses and private release exclusion. No production deployment or migration was performed during this implementation.

## Preserve state during rollback

Restore the Cycle 3 code/assets together, but retain the private database and both metadata tables. Old code can continue inserting unchanged envelopes. Re-upgrading reuses the same baseline, signing secret, and progress; messages delivered during rollback remain eligible for unread tracking. Do not delete metadata or regenerate the secret as part of routine rollback.

## Handle inconsistent metadata

Insertion positions are checked against message IDs. Missing/reassigned baseline or seen anchors cause an unavailable-state error instead of silently treating history as seen. Database rewrites, partial restores, or manual deletion can invalidate those anchors. Back up before maintenance and preserve messages and metadata as one consistent unit. Investigate or restore a known-consistent backup; do not reset tracking to suppress the error. Automated repair is not included in this cycle.

## User-visible boundaries

Unread means a conversation needs attention, not proof of human reading or successful decryption. A visible focused current window acknowledges earlier received history through its captured boundary, including displayed failures. Own sends and loading older pages do not broaden it. Unavailable counterparts cannot be acknowledged until access is restored. Other tabs/devices update on navigation or visible return, not through live polling. An unread-status failure displays unknown status and offers retry; it never claims zero.

The large decryption-warning widget and access to historical ciphertext after key changes remain separate deferred work. Chromium automation covers desktop, constrained mobile layout, focus/visibility gates, and two authenticated contexts; physical mobile keyboards and other browser engines remain manual follow-ups.
