# Compact unavailable private messages rollout and rollback

## Behavior

Unavailable messages use compact details. Consecutive settled failures from the same sender and local date collapse into a count/time summary; expanding it exposes individual details and manual retry. Readable messages, loading failures, and signature warnings break groups. Loading/support errors and unreadable saved keys stay visible with direct retry; missing keys and decryption failures can be compacted. No failure proves a particular key mismatch.

Groups retain message identities and chronological order. History joins preserve expanded choices, while verified retry recovery restores the message and splits the surrounding group. Group/details state and reader outcomes are ephemeral; no plaintext or verification state is persisted.

A visible summary can represent its latest message for the existing seen acknowledgment. Focus, settled reading, identity, and signed-window boundaries still apply; expansion, older history, and sending do not broaden the receipt. Seen does not mean decrypted, verified, or understood.

## Deployment and rollback

- Deploy the updated message template and reader/list/conversation/seen assets together using the normal asset-fingerprinting/release workflow. Do not mix older conversation code with the new visibility lookup. Verify Messages → conversation → expand → details/retry and a grouped latest-message acknowledgment after deployment.
- No schema migration, envelope rewrite, key change, or new API is required. Existing Cycle 4 private metadata remains untouched.
- Roll back the template and assets together to the previous release. Keep private message and seen-state storage intact; this presentation change requires no data repair or reset. Rollback restores the larger error presentation.
- Private conversation markup and behavior remain excluded from static/offline reading. No production deployment, merge, or push was performed during implementation.

## Verification boundary

The isolated encrypted browser journey covers twenty-to-one collapse, sixty-message cross-page grouping, retry recovery, focus/drafts, grouped unread races, desktop/mobile/zoom, and warning visibility. Its layout comparison reconstructs the legacy error/retry presentation with the same twenty-message fixture and theme; it is not a production before/after measurement. See the [implementation summary](./private_message_unavailable_step4_implementation_summary.md) for final commands, results, and artifacts.

Physical mobile keyboards, assistive-technology announcements, and other browser engines remain manual checks. Automated keyboard/accessibility-state checks do not substitute for those checks. Historical-key recovery remains deferred: approving a new key does not retroactively make old ciphertext readable.
