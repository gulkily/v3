> **Feature plan:** [Step 1](./private_message_sender_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_sender_history_sync_step2_feature_description.md) · [Step 3](./private_message_sender_history_sync_step3_development_plan.md) · [Step 4](./private_message_sender_history_sync_step4_implementation_summary.md)

# Sender-assisted private message history synchronization — Step 4

Planning commit: `95d1dfa8`. Branch: `feature/private-message-sender-history-sync`. No production deployment.

## Stage 1 - Authorization and signed bindings
- Changes: central original-direction/current-membership eligibility; signed v2 sender-account bindings, with unchanged v1 same-account output and decoding. Grouped four planning artifacts and updated navigation.
- Verification: `php tests/run.php PrivateMessageHistorySyncTest PrivateMessageHistoryCryptoTest` — 13 passed; `git diff --check` passed.
- Notes: cross-account upload remains disabled until Stage 2. Negative tests cover unrelated accounts, recipient-to-sender direction, membership revocation, changed account/purpose/key bindings and original signatures on both bundled OpenPGP versions.

## Stage 2 - Sender transfer to canonical reading
- Changes: recipient-scoped uploads with per-item direction checks; retrieval, receipts and coverage recheck current donor membership. Canonical candidate decoding supplies the server-derived source account to signed v2 validation. Recipient confirmations remain separate from donor confirmations.
- Verification: focused PHP/crypto suite — 14 passed. `node tests/browser/private_message_history_sync_browser.mjs --sender --direct` passed with real signed recipient approval, sender upload, canonical preview/read and reload after sender closure; artifacts `/tmp/history-sync-browser-dYJZ56`. Original ciphertext unchanged. `git diff --check` passed.
- Notes: mixed-direction batches, unrelated recipients and revoked donors/targets rejected; malformed candidates and unsigned originals remain untrusted. Automatic sender discovery not yet enabled.
