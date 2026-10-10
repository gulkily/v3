# Instance portability gaps and feasibility assessment

The current downloads preserve the canonical public forum record and the current SQLite index database, not the entire running instance. That database can also contain supplemental workflow records. Most additional server-held data is technically straightforward to back up privately. Preserving private conversations through a community fork without the original operator's cooperation is a different, harder problem: it requires access to the encrypted history as well as suitable decryption keys, without accidentally publishing the conversation graph.

This assessment catalogs current exclusions and possible future work. It does not approve an export protocol or expand the scope of the [private message history synchronization proposal](./private_message_history_sync/private_message_history_sync_step1_solution_assessment.md). Feasibility ratings describe relative implementation difficulty, not delivery estimates.

## Three portability goals

1. **Public community fork:** anyone allowed to obtain the public record can independently preserve and continue that record. No private records or secrets should be silently added to existing downloads.
2. **Operator migration or disaster recovery:** an authorized operator can restore private and public state on another deployment. This can use a protected backup and requires access to the original server or an earlier backup.
3. **User controlled transfer:** a participant can preserve their own accessible history, identity, and drafts, then deliberately import them elsewhere. This should not require routine manual key management or grant access to another account's data.

An operator backup can be attainable without making an uncooperative-operator fork attainable. Likewise, moving ciphertext does not guarantee that anyone can still decrypt it. Recreating a cache is not the same as recovering historical evidence.

## What the existing downloads preserve

The [download controller](../../src/ForumRewrite/Http/InstancePageController.php) archives the configured content repository, including `.git`, and separately serves the read-model SQLite file. In the supported deployment layout these are distinct from the application checkout and private runtime storage.

Canonical posts, stored public keys and profiles, approval evidence, reactions, thread metadata, and repository-backed feature flags belong to that public record. Published AI replies are posts and remain included even when their separately recorded LLM exchanges are absent. The canonical index is rebuildable; supplemental workflow history is not necessarily reproducible. Browser private keys are not stored in the canonical record.

Repository-backed feature flags survive, but environment/private overrides and the selected deployment profile do not automatically follow. See the [architecture and trust model](../architecture/public_architecture_and_trust.md) and [production layout and feature flag precedence](../runbooks/production_deploy.md).

The archive currently includes the repository directory rather than constructing a sanitized, allowlisted public export. Private files must remain outside that directory; future packaging must not assume that `.gitignore` excludes files from the archive.

## Supplemental records in the downloaded database

Not all operational records are excluded. [RouteServices](../../src/ForumRewrite/Http/RouteServices.php) gives [PostWorkflowService](../../src/ForumRewrite/Agent/PostWorkflowService.php) a connection to the read-model database. Its stores can add `post_analyses`, `post_generated_responses`, `codex_handoffs`, and `codex_handoff_events` there. Depending on enabled features and retained state, these tables can hold analysis responses, generated reply text and request context, and handoff history. The raw database download includes tables present in that file; this is distinct from the separate private `llm_exchanges.sqlite3` recorder.

This is both a portability and an export-boundary issue. Do not assume that a raw index download contains only data reconstructible from Git, or that every workflow record is absent. The [candidate rebuild](../../src/ForumRewrite/ReadModel/ReadModelCandidateBuilder.php) creates a fresh canonical index and [promotion](../../src/ForumRewrite/ReadModel/ReadModelCandidatePromoter.php) replaces the live file without a supplemental-table transfer step; rebuilding from the repository does not recover those historical rows.

Separating authoritative workflow history from derived indexes, classifying its disclosure rules, and explicitly migrating it is **moderately attainable**. A curated public SQLite export should allowlist intended public tables, with a separate protected export for history that should remain private. This needs a focused follow-up review before expanding downloads; it is not a claim that a particular production instance currently contains sensitive rows. Export filtering and storage migration are not implemented by this wording change.

## Authoritative history and private data

Paths below are defaults, not assumptions about a particular production server. Several stores have configurable locations. A backup tool must resolve the effective configuration.

| Data outside current downloads | Current storage and consequence | Attainable portability route | Difficulty and limits |
| --- | --- | --- | --- |
| Private message envelopes and routing metadata | `state/private/messages.sqlite3`; a public-only fork has no private history. The same database holds message read tracking. | Protected operator backup is already documented. Add validated restore tooling; separately design participant-scoped encrypted export/import. | Low to moderate for operator tooling; high for operator-independent continuity. Ciphertext is not readable without suitable keys, and sender/recipient metadata is sensitive. |
| Encrypted history transfers | Merged into local `main` in separate `state/private/message_history_sync.sqlite3` (configurable); not yet deployed. | Preserve transfers alongside original envelopes, target-key bindings and protocol version using a coordinated private backup; see the [rollout guide](./private_message_history_sync/private_message_history_sync_rollout.md). | Moderate for migration tooling. The public dump remains incomplete; approval cannot manufacture missing decryption access, and lost transfers may require a returning donor. |
| Recorded LLM exchanges | `state/private/llm_exchanges.sqlite3`; exact recorded prompts/responses and associated history do not follow the public posts. | Protected operator export/import with retention controls. Any participant-facing subset needs its own authorization and disclosure rules. | Low to moderate for private migration. High or unsuitable for unrestricted public export: exchanges can contain nonpublic context, not just the final post. Exact past exchanges cannot be regenerated. |
| Visitor statistics | `state/private/visitor_statistics.sqlite3`; retained traffic aggregates are absent on a fork. | Optional private migration preserving collection epochs and retention timestamps; optionally offer a separately reviewed public aggregate report. | Low to moderate. Do not merge overlapping imports by blindly adding counts or extend retention by resetting timestamps. Deleted historical aggregates cannot be reconstructed. |
| Fastmod scoring records | `state/private/fast_scores.sqlite3`; stored assessments and scoring work are absent. | Optional protected export keyed by post ID, content hash, and rubric revision. Recompute only when historical equivalence is not required. | Moderate. Re-running model-backed scoring can cost money and produce different results. Imported pending work must not run automatically. |

Storage references: [mailbox configuration](../../src/ForumRewrite/Messaging/PrivateMessageDatabaseConfig.php), [read state](../../src/ForumRewrite/Messaging/PrivateMessageReadState.php), [LLM storage](../../src/ForumRewrite/Llm/LlmExchangeDatabaseConfig.php), [statistics storage](../../src/ForumRewrite/Statistics/VisitorStatisticsDatabaseConfig.php), and [Fastmod store](../../src/ForumRewrite/Scoring/SqliteFastScoreStore.php). The [mailbox operations runbook](../runbooks/production_deploy.md#private-message-mailbox-operations) already describes consistent SQLite backup and restoration precautions.

## Operational state and deployment dependencies

| Data outside current downloads | Current storage and consequence | Attainable portability route | Difficulty and limits |
| --- | --- | --- | --- |
| Maintenance queue and execution history | `state/private/internal_tasks.sqlite3`; active work, outcomes, and recovery checkpoints do not follow the public archive. | Export history and reconcile pending work against restored canonical state before explicitly enabling workers. | Moderate to high for safe resumption. Copying a queue is easy; preventing duplicate publication, stale locks, obsolete paths, and unintended external calls is not. A fork normally needs fresh workers, not a replay of the original queue. |
| Private agent work files and signing identity | Private files under `state/private/agent-reply/`, plus the configured agent key material, are separate from the generated-response rows discussed above. These files and signing capability do not follow posts. | Protected same-operator migration of necessary work and keys. An independent fork should normally establish its own agent identity and credentials. | Moderate. Review generated files for embedded paths and private context; never package signing keys in public downloads or casually share one identity between diverging operators. |
| QDB vote captions and configuration | `qdb_vote_captions.sqlite3` beside the read model by default; code seeds defaults, but local customizations are not part of the content archive. | Small versioned configuration export, or promote approved nonsecret settings into canonical records. | Low to moderate. Preserve caption tags and their meanings so old reactions retain their interpretation; bootstrapping defaults is not preservation of custom values. |
| Application code and deployment settings | Separate application checkout, host configuration, site profile, environment/private overrides, and provider credentials. | Public manifest identifying the application revision and required runtime, plus nonsecret configuration templates. Transfer secrets only through a protected operator process or replace them. | Moderate for a reproducible setup. Do not export an unfiltered environment or private config. Domain names, TLS, provider accounts, and credentials require explicit destination setup. |
| Sessions and short-lived request state | Server/browser authentication state and prepared post files beside the read model. | Normally discard and reauthenticate or prepare again after restoration; preserve actual drafts separately. | Low if deliberately excluded. Portability should not copy active bearer credentials or resume expired write attempts. |

References: [queue configuration](../../src/ForumRewrite/TaskQueue/TaskQueueDatabaseConfig.php), [agent workflow](../../src/ForumRewrite/Agent/PostWorkflowService.php), [agent identity handling](../../src/ForumRewrite/Agent/AgentIdentityService.php), [QDB caption storage](../../src/ForumRewrite/Qdb/QdbVoteCaptionStore.php), [QDB database location](../../src/ForumRewrite/Qdb/QdbVoteCaptionDatabaseConfig.php), and [prepared post handling](../../src/ForumRewrite/Write/LocalWriteService.php).

## Browser state and external dependencies

| Data outside current downloads | Current storage and consequence | Attainable portability route | Difficulty and limits |
| --- | --- | --- | --- |
| User private keys | Browser local storage; the server cannot put them in a backup it creates. A new origin does not automatically receive the old origin's browser state. | Guided encrypted identity transfer or device-to-device pairing, with explicit destination confirmation and no routine manual key handling. | Moderate to high for a safe, usable flow. Never upload raw private keys to the public archive. No transfer can recover a key absent from every device and backup. |
| Unsent drafts and offline outbox | Browser local storage and IndexedDB; unpublished writing is not in canonical history. | User-initiated export/import or a guided device transfer, separate from server downloads. | Moderate. Bind drafts to their account, destination, and referenced posts; importing must not automatically submit queued writes to a different instance. |
| Display preferences and local reading state | Browser storage, plus private mailbox read tracking on the server. | Optional versioned preferences transfer; preserve mailbox tracking only within a compatible private migration. | Low to moderate. Defaults are acceptable for a fork, and stale message cursors need validation rather than blind copying. |
| External videos and other linked content | Third-party hosts; canonical posts retain URLs, not a complete media archive. | Opt-in preservation of supported resources where permitted, with provenance, integrity checks, quotas, and controlled retrieval. | High and only partially attainable. Availability, access restrictions, permissions, storage cost, and unsafe remote fetches prevent a promise to archive every link. |
| Generated HTML, offline snapshots, and preview/activity caches | Derived files and cache databases outside the canonical repository. | Regenerate static pages, the index, and offline artifacts with compatible code. Optionally retain selected preview metadata. | Low for locally derived artifacts. Preview data may depend on a remote source that has changed or vanished; cache regeneration cannot guarantee the original external content. |

References: [browser identity and drafts](../../public/assets/browser_signing.js), [private message drafts](../../public/assets/private_message_compose.js), [offline outbox storage](../../public/assets/outbox_storage.js), [media rendering](../../src/ForumRewrite/View/MediaEmbedRenderer.php), [preview cache location](../../src/ForumRewrite/View/MediaEmbedPreviewDatabaseConfig.php), and [static artifact generation](../../src/ForumRewrite/Host/StaticArtifactBuilder.php).

## Snapshot consistency and restoration

Completeness is not the only gap. Repository and index downloads are separate operations without a shared snapshot identifier guaranteeing that they represent the same state. The index may lag the repository. The archive command operates on the repository directory, and the index endpoint serves the database file; these endpoints are not a coordinated whole-instance backup protocol.

A reliable portability bundle is moderately attainable and should include:

- A manifest naming the application revision, schema/protocol versions, canonical repository commit, component capture times, checksums, included components, and deliberate exclusions. Different capture times must remain visible unless a coordinated consistent cut is actually guaranteed.
- A stable repository capture and consistent SQLite snapshots, not arbitrary copies of live databases or their sidecars. Coordinate related stores or define a restore reconciliation procedure.
- An allowlist separating public, operator-private, and participant-private components. Validate archive paths, sizes, checksums, and formats before importing; an imported manifest is data, not executable setup instructions.
- Restoration into an isolated destination with workers disabled initially. Verify canonical records and original signatures, rebuild derived data, reconcile jobs, and prove that no records cross account boundaries.
- Repeatable restore tests, including a public-only fork with documented exclusions, a private operator migration, and eventually a participant transfer. A successful download is not proof of recoverability.

## Recommended sequence

1. **Make the current promise accurate.** Describe the public record export and its exclusions on the backup page; do not call it a complete instance backup.
2. **Make public restoration reproducible and its boundary explicit.** Review supplemental tables in the raw database download; separate nonpublic workflow history and allowlist the public export. Add a manifest, application revision/setup guidance, consistent captures, and a tested public-only restore. Preserve nonsecret custom configuration such as QDB captions where applicable.
3. **Productize private operator backup.** Start with the authoritative mailbox; include the separate history-sync transfer database. Add optional LLM history, statistics, and scoring components. Keep secrets and worker activation explicit.
4. **Design participant portability.** Coordinate private-message export/import with the history-sync protocol, followed by guided identity and draft transfers. A user can preserve only the records they can obtain; offline devices cannot export server-held messages they never downloaded.
5. **Treat operator-independent private continuity as its own decision.** Assess proactive participant backups or private replication. A one-time export offered only by a cooperative live server does not protect against that server disappearing first. Do not publish the current mailbox database as a shortcut.
6. **Keep optional work separate.** Public analytics, exact operational-history preservation, and external-media archiving can follow without delaying the core public and private restore paths.

## Decisions required before implementation

- Must private conversations survive only cooperative migration, or also the original operator disappearing or refusing access?
- Who may receive a private archive, and how are account identity, counterpart privacy, and destination trust established across forks?
- Should private read state follow an account, or start fresh on an independent fork?
- Which nonsecret operational records are worth preserving, versus intentionally recomputing or discarding?
- What ongoing retention or replication is acceptable before a failure, and how will users understand the remaining loss window?

The settled history-sync policy remains unchanged: a newly approved key for the same account is authorized for all of that account's private-message history, but recovery still requires a device or backup with suitable access. This does not by itself authorize disclosure to an arbitrary new instance or public distribution of private metadata.
