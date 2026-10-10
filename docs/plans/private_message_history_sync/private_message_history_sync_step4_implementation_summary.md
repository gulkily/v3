# Private message history synchronization Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_history_sync_step2_feature_description.md) · [Step 3](./private_message_history_sync_step3_development_plan.md) · [Step 4](./private_message_history_sync_step4_implementation_summary.md)

Branch: `feature/private-message-history-sync`. Planning-only commit: `d7e89582`. Implementation is in progress; no merge, push, or deployment.

## Stage 1 - Cryptographic protocol validation

- Changes: added a versioned, signed, target-encrypted session-key bundle helper; account/source/target and per-message envelope-digest bindings; limits of ten entries and 64 KiB per bundle. SHA-256 supports HTTP/v5 contexts without SubtleCrypto. Reused the bundled OpenPGP library. Preserved planning findings/evidence and repaired plan navigation after grouping.
- Verification: `php tests/run.php PrivateMessageHistoryCryptoTest` passes; real v6/v5 key transfers, original-signature checks, wrong-key/account rejection, unsigned-original rejection, forwarding to a third key, and SHA-256 vectors against Node crypto. JavaScript syntax and whitespace checks pass.
- Notes: bundles contain only session keys and bindings; verification of a transfer does not verify the original message. Stages 2–8 remain. Work remains within the approved eight-stage scope; no production assets are wired yet.

## Stage 2 - Separate storage and bounded discovery

- Changes: added independently configured/lazily opened private sync SQLite storage, one-second busy timeout and FULL durability; reject identical paths, symlinks and hardlinks to the message database. Added resettable donor/account checkpoints and bounded account-wide message enumeration. Work discovery uses current approved key membership with no request queue or approval hook. Original message/unread storage remains independent.
- Verification: `php tests/run.php PrivateMessageHistorySyncTest PrivateMessageStoreTest PrivateMessageDatabaseConfigTest PrivateMessageApiRoutingTest` — 16 passed. Covers three-plus pages, both directions, foreign key rejection, approval changes, checkpoint reset, path aliases, and existing mailbox contracts. PHP syntax and whitespace checks pass.
- Notes: only the work endpoint is exposed at this stage; no browser contribution is enabled. Separate database defaults to `state/private/message_history_sync.sqlite3`; override `PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH`. Rollback journaling remains compatible with existing filesystems; concurrent API behavior will be checked in Stage 3 and the release journey.
