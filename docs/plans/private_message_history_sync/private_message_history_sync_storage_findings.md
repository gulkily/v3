# Private message history synchronization storage findings

> **Feature plan:** [Step 1](./private_message_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_history_sync_step2_feature_description.md) · [Step 3](./private_message_history_sync_step3_development_plan.md) · [Step 4](./private_message_history_sync_step4_implementation_summary.md)

The user selected a separate private database for history-sync transfers and metadata after reviewing the storage choices. The design still reuses applicable messaging components and derives work from current membership and history. The review removed a request queue, approval-event ledger, persistent progress counters, permanent failure records, and work leases. Local experiments found that a small scan checkpoint prevents starvation and lock contention is measurable; the measurements alone do not establish a need for separate databases.

These findings were recorded on 2026-10-10 for implementation planning. Steps 1 and 2 and the subsequent separate-database direction are approved; Step 3 remains pending. The original recommendation to share the message database is superseded by that user decision. Experimental evidence below is preserved as recorded. No application implementation or production configuration changes were made during these experiments.

## Messaging infrastructure reuse

Record type, reusable components, and physical database placement are separate decisions. A dedicated transfer protocol can reuse the existing private database and browser cryptography without becoming an ordinary chat message.

| Choice | Benefit | Cost or constraint |
| --- | --- | --- |
| Ordinary or typed system messages | Reuses envelope acceptance, retention, and duplicate-send handling | Requires target-key addressing and consistent exclusion from conversation queries, presentation, and recursive synchronization |
| Dedicated transfer records in the existing database | Preserves conversation behavior and keeps related durable state in one backup | Requires transfer-specific contracts and shares database contention |
| Dedicated transfer records in a separate database | Separates write locking and permits independent storage operations | Adds configuration and backup coordination; restoration must reconcile messages and transfers captured at different times |

The existing [envelope helper](../../../public/assets/private_messages.js) automatically encrypts for the approved keys of both usernames. A transfer needs an explicit target key, so that helper cannot be used unchanged. The [message store](../../../src/ForumRewrite/Messaging/PrivateMessageStore.php) treats message rows as conversation activity, while the [mailbox service](../../../src/ForumRewrite/Messaging/PrivateMessageMailboxService.php) rejects opening a conversation with oneself. Merely hiding transfers in the interface would leave those underlying contracts unresolved. Existing unread queries already exclude self-sent messages; the problem is broader than the unread count alone.

Reuse browser identity/authentication, the [approved-key resolver](../../../src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php), OpenPGP loading, and the [original-message reader](../../../public/assets/private_message_reader.js). Use dedicated transfer records and target-key contracts. A general system-message channel could justify a different choice if independently needed, but it is outside this feature's current scope.

## Durable and derived synchronization state

| State | Proposed treatment | Reason |
| --- | --- | --- |
| Encrypted transfer bundles and message/digest/source/target indexes | Retain in private storage after acknowledgment | A bundle may be the target's only retained route to an original message after the donor disappears; reload must not lose access |
| Target-confirmed message coverage | Persist as resettable scheduling metadata | Avoid repeated work; associate it with a retained eligible transfer or verified direct-envelope access |
| Scan checkpoint per donor/account | Persist a small resettable position | Rotate through messages and targets across short visits, then revisit remaining gaps |
| Requests and approval-event ledger | Derive from current approved membership, history, and coverage | Handles new and already-approved keys without depending on a particular approval hook |
| Progress counters | Derive from existing records | Avoid redundant state and extra writes |
| Failed attempts | Keep retryable; do not create permanent loss claims | Another donor, restored key, or later contribution may succeed |
| Work leases and a general job queue | Omit | Idempotent uploads and safe overlapping donors remove the need for exclusive ownership |
| Plaintext and unwrapped session keys | Browser memory only | Keep secrets out of server storage/logs and persistent browser caches |

Target confirmations report browser verification; they are not independent server proof and never replace verification when reading. Historical confirmations must not be presented as fresh verification by the current browser. Lost or ineligible transfers reopen coverage unless direct access remains valid. Losing scheduling metadata may cause repeated work, but must not destroy access or falsely close gaps.

An approval creates synchronization demand through membership reconciliation. No separately stored request is necessary. Checkpoints must advance past unsuccessful items, rotate fairly through targets, wrap to revisit gaps, and recover safely after reset. Uploads and checkpoint updates should be bounded and coalesced; a visit with no work should not require a progress write.

## Counterexamples that constrain simplification

An abstract model used 75 messages, pages of 25, and a donor able to recover only messages 51–75. Restarting at the first unresolved page restored zero messages after six visits. Advancing a checkpoint reached the recoverable page after three visits and restored all 25. When access to messages 1–25 became available, wrapping the scan reached those earlier gaps again. This supports a scheduling checkpoint rather than a permanent failure classification.

A second model kept a bad donor candidate separate from a later good candidate. The bad upload did not establish coverage or suppress the good donor. A third check removed the transfer referenced by a confirmation and reopened the gap. These are checks of abstract state rules, not cryptographic or application-protocol validation. Their outcomes are retained in the [challenge assessment](./evidence/storage_challenge_assessment.json).

## Database lock experiment

The local experiment compared a no-sync baseline, shared storage, and separate storage under both rollback journaling (`DELETE`) and WAL. Each combination ran twice, for twelve runs in total. Each run used fresh temporary databases with 1,000 synthetic message envelopes.

- Environment: PHP 8.1.2-1ubuntu2.26 and SQLite 3.37.2; `synchronous=2` (`FULL`). The local PDO connections reported a 60,000 ms busy timeout without an explicit application override.
- Foreground workload: one process performed 150 actual `PrivateMessageStore` sends and seen updates; another performed 150 unread-state and conversation-history reads. Both loops paused 10 ms between iterations.
- Sync workload: three processes each performed 80 transactions. Each transaction inserted an 8 KiB placeholder bundle and 25 indexed items and updated a checkpoint, followed by a 10 ms pause.
- The split layout moved only the synthetic transfer tables to another database on the same local storage. The probe did not implement transfer authorization, encryption, or browser synchronization.

The table shows the range of each run's p99, rounded to two decimal places. Each foreground operation had 150 samples per run. It is not a confidence interval or a pooled percentile.

| Journal | Layout | Send p99 in ms | History-read p99 in ms | Database errors |
| --- | --- | ---: | ---: | ---: |
| DELETE | No-sync baseline | 5.68–6.04 | 9.41–9.73 | 0 |
| DELETE | Shared database | 106.17–106.19 | 104.89–105.36 | 0 |
| DELETE | Separate databases | 22.87–29.82 | 19.86–55.76 | 0 |
| WAL | No-sync baseline | 4.11–18.12 | 1.21–1.53 | 0 |
| WAL | Shared database | 10.55–54.67 | 1.24–1.24 | 0 |
| WAL | Separate databases | 4.39–5.33 | 1.11–1.44 | 0 |

The [full per-run results](./evidence/lock_probe_results.json) retain sample counts, p50, p95, p99, maximum latency, and error lists for sends, seen updates, unread retrieval, history retrieval, and synthetic sync transactions. The [assessment](./evidence/storage_challenge_assessment.json) preserves methodology, ranges, and the historical same-database recommendation preceding the user's separate-storage decision. These copies do not depend on the temporary experiment directory surviving.

The results qualify the initial intuition that locking would not matter: contention did affect foreground latency in this probe. They do not establish production capacity or a decisive storage architecture. The small sample, local filesystem, shared disk, and scheduling noise limit comparisons. Foreground samples include operations both during and after sync activity, and the synthetic write cadence is not a production traffic estimate. A 60-second busy timeout also means zero errors alone cannot demonstrate responsive behavior.

WAL permits readers and a writer to overlap but still allows only one writer at a time per database, consistent with the need to keep write transactions short. See [SQLite's concurrency documentation](https://www.sqlite.org/wal.html#concurrency). The [application connection setup](../../../src/ForumRewrite/Application.php) does not explicitly set journal mode or busy timeout; deployed settings must be checked rather than inferred from this local probe. No production setting was changed.

## Selected storage choice and remaining validation

Use a dedicated sync database, defaulting to `state/private/message_history_sync.sqlite3`, with a new `PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH` override. Keep messages and unread metadata in `PRIVATE_MESSAGE_DATABASE_PATH`, defaulting to `state/private/messages.sqlite3`. Reject configuration that resolves both paths to the same file. A separate sync-store interface and lazy connection keep ordinary messaging usable when sync storage is unavailable.

Reference original messages through stable IDs and envelope digests, with bounded reads through the message store. Do not rely on joins, foreign keys, or atomic commits across databases. Revalidate after interrupted work or restore; orphaned transfers cannot establish coverage, and missing transfers may require another donor. Keep original message records untouched by synchronization.

Extend the [private mailbox operations](../../runbooks/production_deploy.md#private-message-mailbox-operations) to cover both configured paths. Use coordinated consistent backups, documenting write-quiescence when a common recovery point is required, and test reconciliation of mismatched snapshots. Rollback retains both databases. Separate files still share disk resources, and sync still reads original messages, so representative foreground latency/error checks, short transactions, and explicit journal/timeout policy remain required before enabling donors.

The full transfer protocol, original-signature checks under both bundled OpenPGP versions, exact table definitions and indexes, candidate limits, and the browser recovery journey remain implementation validation work. The storage experiments do not satisfy those gates. Step 3 approval is still required before application implementation.
