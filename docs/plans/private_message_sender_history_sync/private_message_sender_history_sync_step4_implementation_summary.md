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

## Stage 3 - Bounded outgoing discovery
- Changes: optional `work?mode=sender`, outgoing-only bounded message paging, original-recipient key rotation, and additive `history_sync_sender_scan` checkpoints in the existing dedicated sync database. Default account scans and their checkpoints are unchanged. Sender discovery never consults recipient coverage or read state.
- Verification: `php tests/run.php PrivateMessageHistorySyncTest PrivateMessageHistoryCryptoTest PrivateMessageStoreTest PrivateMessageApiRoutingTest` — 26 passed; `git diff --check` passed. Tests cover multiple recipient accounts/keys, existing targets, stale-key arrivals, missing anchors, recipients without approved keys, and exact response equality across recipient confirmations.
- Notes: scan checkpoints are scheduling hints, never authority. Each batch targets one original recipient; keys rotate before advancing that batch. Empty-key recipients advance and are revisited on the next cycle. No request queue or new database.

## Stage 4 - Automatic separate visits
- Changes: account/sender work alternate within the existing eight-batch/10-second soft/15-second network deadline; session storage records only the next mode to preserve turns across short visits/reloads. Existing concurrency, identity/visibility cancellation and retries remain shared. Sender contributions wrap only verified originals and confirm the donor's own access. Interrupted sender batches leave their checkpoint unchanged for retry. Old-server responses disable sender mode for that run while retaining account work.
- Verification: focused crypto/coordinator/reader tests — 4 passed; earlier focused service/crypto run — 15 passed. `node tests/browser/private_message_history_sync_browser.mjs --sender` passed: 31 incoming messages, signed approval, partial sender donors on non-Messages pages, closed donors, in-place draft preservation, reload, recipient forwarding, paired restore and normal send during sync-store outage. Artifacts `/tmp/history-sync-browser-Nupkni`. `git diff --check` passed.
- Notes: no global recovery UI added. Session-storage scheduling preference contains no message IDs, recovery status or keys; if unavailable, in-page alternation still works.

## Stage 5 - Failure, compatibility and rollback checks
- Changes: expanded sender recovery tests for unconfirmed replacement, lost blobs, changed/missing originals and revocation reopening coverage. Added deterministic coordinator checks for shared limits, canceled responses, reload turn preservation and partial-batch retry. Documented additive upgrade, mixed clients and rollback without deleting either private database.
- Verification: service/crypto/release-isolation checks — 18 passed; added scheduler wrapper — 1 passed. Legacy PHP/store from `cdb1de0e` successfully read v1 candidates, excluded cross-account donors and left retained transfers unchanged on upgraded browser-fixture storage; rehearsal `/tmp/sender-history-rollback-3e21bm08`. Browser rehearsal with actual legacy crypto/coordinator assets rejected sender-only v2 reading, read 31 forwarded v1 originals, and passed paired restore/outage; artifacts `/tmp/history-sync-browser-8OQb3O`. `git diff --check` passed.
- Notes: initial legacy-browser rehearsal did not intercept fingerprinted asset URLs; corrected its route and asserted runtime VERSION=1 before evaluating compatibility. No product defect from that harness failure. Local rehearsals are not deployment evidence; old code cannot recover sender-only v2 history. Existing v1 output remains unchanged on both bundled OpenPGP versions.
