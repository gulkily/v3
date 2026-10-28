# Read-Model SQLite Schema Reference

This documents the actual current table/column structure of the primary
SQLite database, as a living reference (not a feature-history narrative like
`docs/plans/php_incremental_read_model_write_slices_v1.md`, which describes
*how* incremental updates were built, not what the schema currently is).

If this drifts from the code, the code wins — regenerate this from
`ReadModelBuilder::createSchema()` and the individual store classes listed
below rather than trusting this file blindly.

## Where it lives

The default path is `state/cache/post_index.sqlite3`, overridable via
`FORUM_DATABASE_PATH`. It's a derived artifact: fully disposable and rebuilt
from canonical repository records (`ReadModelBuilder`, `./v3 rebuild`) or
updated incrementally on writes (`IncrementalReadModelUpdater`). See
`docs/specs/php_forum_rewrite_spec_v1.md` §6 for the design principles
behind this layer.

## Schema-change contract

When changing a core read-model table, column, index, constraint, or a query
that requires one, increment `ReadModelMetadata::SCHEMA_VERSION` in the same
change. Application startup uses that version to identify an incompatible
derived database and rebuild it before queries run. Also update this reference
and add a regression test for startup from the prior schema version. See
[`CONTRIBUTING.md`](../../CONTRIBUTING.md) for the complete contributor
checklist.

Adding a **table** needs only its `CREATE TABLE` statement in
`ReadModelSchema::statements()`: the rebuild drop step
(`ReadModelBuilder::dropSchema()`) derives the tables to drop from
`ReadModelSchema::tableNames()` (parsed from those statements), so a new table is
dropped-and-recreated on in-place rebuild automatically — there is no separate
drop list to keep in sync. (A forgotten entry previously caused in-place rebuilds
to fail with `table <name> already exists`.) Indexes need no drop step; dropping
a table drops its indexes. Read-model tables declare no foreign keys, so drop
order is immaterial.

## Core read-model tables

Version 35 switches Forte initial/selected/compatibility details and category/commit counts to stored projections. RSS and full commit inspection retain their independent contracts; older schema startup rebuilds before the changed queries.

Version 34 switches standard Activity to the indexed-evidence read contract; startup rebuilds prior versions before rendering it.

Version 33 adds `activity_counts` (view, total, without_profile_names) and
`activity_local_state` (singleton, events, revision). SQL triggers maintain counts
on every Activity writer and target-post visibility change, plus commit totals.
Local-event mutations advance a cursor token transactionally; canonical-only
changes do not. Standard paging reads that token instead of hashing local history.
Activity indexes cover the standard date/coalesced-key order and Forte date,
kind and label/action-key/id orders. Schema-32 startup rebuild is required.

Version 32 adds compact `activity.evidence_json`: an internal tuple of signature path/status and relevant-file tuples (path, status, previous path, role, signer, key path, key status, optional false for an unavailable link). No rendered HTML, deleted prose or full commit manifests are stored. NULL means indexing is incomplete; a partial pending index and source-field triggers mark affected evidence for write-time refresh. Source-less events receive explicit empty evidence. Startup rebuilds version 31 before querying the new projection.

Version 31 adds the public identity-history query contract (no new tables):
counts and bounded histories select authored posts by exact identity, excluding
hidden, retracted, identity/bootstrap-board and scripture-expansion rows. Thread histories
add authored-root and visible thread requirements. Counts use the same predicate
as the corresponding history. Approval and username do not establish authorship.
Startup rebuilds version 30 read models before these queries run.

Defined in `src/ForumRewrite/ReadModel/ReadModelBuilder.php`'s
`createSchema()`. Fully rebuilt (not incrementally patched) on every
`ReadModelBuilder::build()` run.

### `metadata`

Single-row-per-key bookkeeping about the last rebuild.

| Column | Type | Notes |
| --- | --- | --- |
| `key` | TEXT PK | e.g. `schema_version`, `schema_fingerprint`, `repository_root`, `repository_head`, `rebuilt_at`, `rebuild_reason`, `thread_label_invalid_count` |
| `value` | TEXT | |

### `posts`

One row per canonical post record (both thread roots and replies).

Schema 27 adds `posts_author_identity_idx (author_identity_id)`. Together with
the primary-key index on `post_id`, it supports the identity-link predicate
`post_id = :bootstrap_post_id OR author_identity_id = :author_identity_id`
using SQLite's multi-index OR plan, retaining single-update semantics when a
row matches both branches. Normal startup rebuilds schema-26 and older models.

| Column | Type | Notes |
| --- | --- | --- |
| `post_id` | TEXT PK | |
| `created_at` | TEXT | |
| `thread_id` | TEXT | |
| `parent_id` | TEXT NULL | null for thread-root posts |
| `subject` | TEXT NULL | |
| `body` | TEXT | |
| `board_tags_json` | TEXT | JSON array |
| `thread_type` | TEXT NULL | |
| `agent_response_json` | TEXT NULL | Optional canonical response kind, source/version, and expansion parent/version; introduced in schema 18. |
| `author_identity_id` | TEXT NULL | |
| `author_profile_slug` | TEXT NULL | |
| `author_label` | TEXT | default `'guest'` |
| `post_tags_json` | TEXT | JSON array, default `'[]'` |
| `post_score_total` | INTEGER | default 0 |
| `approved_flag_count` | INTEGER | default 0 |
| `is_hidden` | INTEGER | default 0; moderation flag |
| `hidden_reason` | TEXT NULL | |
| `retracted_at` | TEXT NULL | accepted retraction timestamp |
| `retraction_class` | TEXT NULL | author or operator authorization classification for an effective retraction |
| `retraction_attribution` | TEXT NULL | accepted display attribution: author or operator, independent of authorization |
| `sequence_number` | INTEGER | |

Indexes: `posts_thread_sequence_idx (thread_id, sequence_number)` backing the
per-thread reply query (`ORDER BY sequence_number`); `posts_author_identity_idx
(author_identity_id)` backing the `profiles` identity join used by the board and
reply queries. Added in schema version 26.

### `threads`

One row per thread (keyed by its root post).

| Column | Type | Notes |
| --- | --- | --- |
| `root_post_id` | TEXT PK | |
| `root_post_created_at` | TEXT | |
| `last_activity_at` | TEXT | |
| `subject` | TEXT NULL | |
| `body_preview` | TEXT | |
| `reply_count` | INTEGER | |
| `last_post_id` | TEXT | |
| `board_tags_json` | TEXT | JSON array |
| `thread_labels_json` | TEXT | JSON array |
| `score_total` | INTEGER | default 0 |
| `vote_count` | INTEGER | default 0; distinct upvote/downvote events |
| `retracted_at` | TEXT NULL | root retraction timestamp |
| `retraction_class` | TEXT NULL | root author/operator authorization classification |
| `retraction_attribution` | TEXT NULL | root accepted display attribution |

Index: `threads_last_activity_idx (last_activity_at DESC, root_post_id DESC)`
backing the board/tags/RSS listing sort. Added in schema version 26.

### `profiles`

Schema 28 changes identity-registration derived-state writes to update profile
approval/attribution/count fields only when their values differ. Comparisons are
NULL-safe (`IS NOT`), and the updater retains the changed profile identities for
targeted downstream work. No columns or indexes change; the version increment
and prior-schema startup check accompany this core query change.

One row per identity with a bootstrapped profile.

| Column | Type | Notes |
| --- | --- | --- |
| `identity_id` | TEXT PK | |
| `profile_slug` | TEXT UNIQUE | |
| `username` | TEXT | |
| `username_token` | TEXT | |
| `fallback_label` | TEXT | |
| `signer_fingerprint` | TEXT | |
| `bootstrap_post_id` | TEXT | |
| `bootstrap_thread_id` | TEXT | |
| `public_key` | TEXT | |
| `is_approved` | INTEGER | default 0 |
| `approved_by_identity_id` | TEXT NULL | |
| `approved_by_profile_slug` | TEXT NULL | |
| `approved_by_label` | TEXT NULL | |
| `post_count` | INTEGER | default 0 |
| `thread_count` | INTEGER | default 0 |

### `username_routes`

Username-token → identity lookup (username bootstrap route resolution).

| Column | Type | Notes |
| --- | --- | --- |
| `username_token` | TEXT PK | |
| `identity_id` | TEXT | |

### `instance_public`

Singleton row for the project-info/instance page.

| Column | Type | Notes |
| --- | --- | --- |
| `singleton` | INTEGER PK | `CHECK (singleton = 1)` |
| `instance_name` | TEXT | |
| `admin_name` | TEXT | |
| `admin_contact` | TEXT | |
| `retention_policy` | TEXT | |
| `install_date` | TEXT | |
| `body` | TEXT | |

### `activity`

Feed of indexed activity entries (see `docs/specs/php_forum_rewrite_spec_v1.md`
§11 and `ActivityService::normalizeActivityView()` for the `all`/`content`/
`identity`/`bootstrap`/`approval`/`commits` view filter this backs).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | INTEGER PK AUTOINCREMENT | |
| `created_at` | TEXT | |
| `kind` | TEXT | |
| `record_family` | TEXT | default `'post'` |
| `action_key` | TEXT NULL | |
| `post_id` | TEXT NULL | |
| `thread_id` | TEXT NULL | |
| `label` | TEXT | |
| `board_tags_json` | TEXT | JSON array |
| `author_identity_id` | TEXT NULL | |
| `author_profile_slug` | TEXT NULL | |
| `author_username_token` | TEXT NULL | |
| `author_label` | TEXT | |
| `author_is_approved` | INTEGER | default 0 |
| `source_path` | TEXT NULL | |
| `source_commit_sha` | TEXT NULL | |

Indexes: `activity_recent_idx (created_at DESC, post_id DESC, id DESC)`,
`activity_post_id_idx (post_id)`, `activity_action_key_idx (action_key)`,
`activity_author_identity_idx (author_identity_id)` (schema 26). The author
index supports identity-specific attribution/approval updates without scanning
all activity rows for each profile. Schema-25 databases are rebuilt at normal
startup before serving the current application; this index is also present in
manual rebuilds and candidate databases.

Schema 29 limits registration activity refresh to the newly linked identity
and profiles whose derived state changed. Attribution updates use NULL-safe
changed-value comparisons and the computed approval state for those profiles;
they no longer refresh every profile's activity. Normal startup rebuilds older
derived models before the changed query path runs. Table/index shapes are
unchanged from schema 27.

### `commits`

Indexed git commit metadata, backing the `commits` activity view.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | INTEGER PK AUTOINCREMENT | |
| `sha` | TEXT UNIQUE | |
| `author_name` | TEXT | |
| `author_email` | TEXT | |
| `committed_at` | TEXT | |
| `subject` | TEXT | |
| `file_count` | INTEGER | |

Index: `commits_committed_at_idx (committed_at DESC, id DESC)`.

## Agent/workflow tables sharing the same database file

These live in the same SQLite file as the tables above (each store opens it
via the same lazy `$this->pdo()` factory as the read model — see
`PostWorkflowService`), but unlike the core tables, they're written directly
by request handling and are **not** rebuilt by `ReadModelBuilder`; each store
creates its own table with `CREATE TABLE IF NOT EXISTS` on first use.

### `post_analyses`

Defined in `src/ForumRewrite/Analysis/SqlitePostAnalysisStore.php`. Cached
LLM analysis results per post/content-hash.

Key columns: `post_id`, `content_hash` (composite PK), `status`,
`requested_at`, `completed_at`, `provider`, `provider_model`,
`provider_request_id`, `post_summary`, `moderation_json`, `engagement_json`,
`quality_json`, `respondability_json`, `related_content_json`,
`related_content_assessment_json`, `raw_response_json`, `failure_code`,
`failure_message`, `retry_after`.

### `post_unicode_risks`

Defined in `src/ForumRewrite/Analysis/SqliteUnicodeRiskStore.php`. Unicode
risk-detection results per post/content-hash.

Key columns: `post_id`, `content_hash` (composite PK), `schema_version`,
`status`, `deterministic_facts_json`, `llm_review_json`, `failure_message`,
`created_at`, `updated_at`. Index: `idx_post_unicode_risks_status (status,
updated_at)`.

### `post_generated_responses`

Defined in `src/ForumRewrite/Agent/SqliteAgentReplyGenerationStore.php`.
Tracks generated `reply-agent` responses per target post/content-hash — see
`docs/specs/agent_reply_one_step_analyze_publish_contract_v1.md` for the
state machine this drives.

Key columns: `id` (PK), `target_post_id`, `target_content_hash`,
`analysis_hash`, `status`, `requested_at`, `completed_at`, `provider`,
`provider_model`, `provider_request_id`, `response_text`, `response_style`,
`response_intent`, `agent_identity_id`, `agent_profile_slug`,
`agent_post_id`, `posted_at`, `failure_code`, `failure_message`,
`retry_after`, `request_context_json`, `raw_response_json`. Unique on
`(target_post_id, target_content_hash)`.

### `codex_handoffs` and `codex_handoff_events`

Defined in `src/ForumRewrite/Codex/CodexHandoffStore.php`. Tracks Codex
handoff requests and their status-change event log.

`codex_handoffs` key columns: `id` (PK), `handoff_id` (unique), `origin_post_id`,
`origin_thread_id`, `origin_content_hash`, `requester_identity_id`,
`requester_profile_slug`, `requester_username`, `status`, `user_story`,
`fdp_step1`, `confidence_summary`, `draft_text`, `status_context_json`,
`requested_at`, `draft_ready_at`, `approved_at`, `rejected_at`, `running_at`,
`completed_at`, `failed_at`, `updated_at`. Unique on
`(origin_post_id, origin_content_hash)`.

`codex_handoff_events` key columns: `id` (PK), `handoff_id`, `event_status`,
`created_at`, `origin_post_id`, `origin_thread_id`, `label`,
`author_identity_id`, `author_profile_slug`, `author_username_token`,
`author_label`, `author_is_approved`, `context_json`. Index:
`codex_handoff_events_recent_idx (created_at DESC, id DESC)`.

## Adjacent, separate SQLite databases

Not part of `post_index.sqlite3` — each opens its own file, so a query
against the read-model database won't see these:

- **Activity commit-manifest cache** —
  `state/cache/activity_commit_manifest_cache.sqlite3` (sibling of the read
  model, same directory). One table, `activity_commit_manifests`
  (`commit_sha` PK, `files_json`, `cached_at`) — defined in
  `src/ForumRewrite/Activity/SqliteActivityCommitManifestCache.php`.
- **LLM exchange log** — path from `LlmExchangeDatabaseConfig` (private
  config-driven, not under `state/`). One table, `llm_exchanges` — defined
  in `src/ForumRewrite/Llm/LlmExchangeRecorder.php`.
- **Task queue** — path from `TaskQueueDatabaseConfig`. One table,
  `internal_tasks` — defined in
  `src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php`; see the
  `./v3 task-queue` section of `docs/reference/v3_cli.md`.

These three are small enough that this section plus their source files is
sufficient; they don't warrant their own schema sections here.

## `reaction_signatures` (schema version 17)

One row per thread-label or post-reaction file, including repeated labels absent
from the activity reducer. Full rebuild and incremental family writes refresh
this derived evidence index. Signature status does not replace target validation
or imply every vote in an aggregate is verified.

| Column | Type | Meaning |
| --- | --- | --- |
| `source_path` | TEXT PK | Canonical record path |
| `author_identity_id` | TEXT NULL | Claimed author |
| `signature_status` | TEXT NOT NULL | `verified`, `unsigned`, or `invalid` |

Metadata also includes `post_reaction_invalid_count`, parallel to
`thread_label_invalid_count`. A missing signer key makes a supplied signature
invalid, never unsigned. Raw source bytes are unchanged; current-source responses
expose `X-Forum-Signature-Status`, and activity source metadata shows the status.

## Operator retraction attribution

Schema version 18 adds nullable `retraction_class` to `posts` and `threads`.
It is `author` or `operator` for an effective retraction and null otherwise.
The root classification is copied into the thread row for paned views.
Target, actor, signature, timestamp, and operator acceptance authority remain
canonical evidence; this column is derived presentation metadata. Startup
rebuilds a version 17 cache before executing queries using the new column.


## Account retraction attribution (schema version 19)

Nullable `retraction_attribution` on `posts` and `threads` separates displayed
account authorship from `retraction_class`, which continues to reflect signed
author/operator authorization. Both are null for unretracted posts. Startup
rebuilds version 18 caches before queries use the new field.

New retractions retain a separate [text acceptance receipt](post_retraction_record_v1.md)
with `Retraction-Attribution`, bound to the exact signed request and detached
signature. This is local acceptance metadata, not a signed attribution claim or
imported authority. Action-time account evidence determines author/operator
wording; later approval changes and rebuilds preserve that decision. A missing
or mismatched receipt/evidence set is not accepted retraction input. The earlier
JSON-trailer representation is no longer written or required for new retractions.

## Profile name projections (schema versions 20–21)

Signed immutable `records/profile-names/profile-name-<32 hex>.txt` updates
override bootstrap usernames without changing keys, profile slugs or approvals.
Only valid records with verified owner signatures and matching filenames apply.
The greatest `(Created-At, Record-ID)` determines the current name; writes use
strictly increasing timestamps per identity. Existing profile, username-route,
author and activity fields carry the effective name; no new tables are required.
Name writes rebuild the derived model and invalidate generated presentation.

Schema version 21 adds one `activity` row per verified name update: kind
`profile_name_change`, `record_family` `profile_name`, label
`Changed username from <previous> to <new>` (the previous name is the prior
verified update, or the bootstrap username), tags `["identity","profile-name"]`,
no post or thread, the record as `source_path` and its commit as
`source_commit_sha`. Rows are always indexed; Activity lists, counts, details,
feeds and the pending-users latest-activity column show them only while the
`FORUM_USERNAME_CHANGES_ENABLED` flag is on. They appear in the `all` and
`identity` views.
Unsigned/malformed updates are ignored. Existing duplicate-name route policy remains.
### Agent response provenance (schema version 22)

Optional `Agent-Response` JSON on canonical reply records identifies
`scripture_oriented_analysis` or `scripture_expansion`, its source post/version,
and (for expansion) the initial reply/version. `posts.agent_response_json`
replays this metadata on rebuild. Expansion records use a deterministic ID
based on their initial reply; publication retries reconcile that canonical file
rather than creating another reply. Older records remain valid without metadata.

Offline snapshot schema 4 includes `posts.agent_response_json` and an
`unavailable_expansions` table containing IDs only. Exports omit expansions whose
source/initial response is unavailable or version-mismatched. Offline updates
upgrade schema 3 metadata columns, replace complete updated threads, and remove
unavailable expansion IDs even when their source thread is outside the update's
recent-thread window. The reader shows stored expansion beneath its initial
answer without generation controls; legacy snapshots remain readable.

### Source-removal placeholders (schema version 23)

No columns are added. Version 23 requires rebuilding old derived rows: accepted
physical source removals materialize content-free post placeholders using retained
thread/parent identity, creation time and author identity. Existing retraction
columns mark operator removal; no reply prose is promoted into a missing root.
Generated labels with proven removed/retracted sources are excluded from label,
score, vote and activity aggregation; surviving independent labels remain active.

The `source_removal_generation` metadata key binds rebuilt aggregation to the
accepted source-removal/resolution records. Static/offline publication rejects a
source model with a missing or different generation while such evidence exists.
Evidence lives in `records/post-removals/<source-id>.json`: operation, content hash,
structural post identity, exact label-file hashes, acceptance time and local actor.
It contains no source prose and is effective only with an exact trusted local
`Accept source label cleanup <source-id>` commit. Importing an archive receipt
alone grants no authority. Preserve trusted repository history for restoration.

### Per-contribution like Activity (schema version 24)

No table shape changes. Version 24 rebuilds existing derived rows so each eligible
canonical thread-label record containing `like` contributes one Activity item,
even when the thread already has that effective label. Mixed-label records remain
one item; other labels retain first-appearance semantics. Scoring and vote
eligibility are unchanged. Each item retains its canonical action key, actor,
record timestamp and per-path source commit; unavailable history stays unknown.
Normal startup rebuilds version-23 models before serving Activity. Canonical
records are unchanged; `./v3 rebuild` also recovers previously omitted items.

### Standard Activity page queries (schema version 25)

Table shape is unchanged. Standard Activity gains bounded page queries ordered
by original activity time and canonical action key, independent of disposable
SQLite row IDs. Version 25 rebuilds prior models at normal startup before the
new query contract runs. Existing Forte and RSS queries/return shapes remain
unchanged; canonical records and Activity membership are unaffected.


Schema version 27 also rebuilds existing derived rows using accepted deletion records as structural placeholders. Core columns are unchanged; legacy suppression-only records no longer gate indexing. This version bump also triggers normal startup refresh on code-only deployments.

### Hot-path indexes (schema version 26)

No table shape changes. Version 26 adds three covering-free indexes to speed the
hottest read paths: `posts_thread_sequence_idx (thread_id, sequence_number)` and
`posts_author_identity_idx (author_identity_id)` on `posts`, and
`threads_last_activity_idx (last_activity_at DESC, root_post_id DESC)` on
`threads`. These remove full table scans and in-memory sorts from the single-thread
reply query and the board/tags/RSS listing sort; query results and return shapes
are unchanged. Normal startup rebuilds prior models before the indexed queries
run. `posts_thread_sequence_idx` mirrors the index the public offline snapshot
builder already creates, keeping the two models consistent.

### Viewer-tag projection (schema version 27)

Version 27 adds the `viewer_tags` table and its `viewer_tags_identity_idx` index:

```
viewer_tags (
    record_family TEXT NOT NULL,       -- 'post-reactions' | 'thread-labels'
    target_id TEXT NOT NULL,           -- post_id for post-reactions, thread_id for thread-labels
    author_identity_id TEXT NOT NULL,
    tag TEXT NOT NULL,                 -- reaction tag / thread label, e.g. 'like', 'flag', 'upvote'
    PRIMARY KEY (record_family, target_id, author_identity_id, tag)
)
Index: viewer_tags_identity_idx (author_identity_id, record_family, tag, target_id)
```

This projects per-(identity, target, tag) reaction/label state so viewer-tag
questions (`ViewerTagLookup::postTags`/`threadTags`) are answered by an indexed
`WHERE author_identity_id = ? AND record_family = ? AND tag = ? AND target_id IN (…)`
query instead of globbing and parsing the whole `records/post-reactions/` and
`records/thread-labels/` directories per request. The projection mirrors the
prior file-scan record set exactly — it applies no `inactive`/publication
exclusion — so viewer like/flag/vote/label results are unchanged, including on
retracted or removed-source threads. The index column order matches the lookup
predicate. Null-identity records are not projected (the file scan never matched
them). Normal startup rebuilds prior models before the indexed query runs.

### Schema 30 integration

Schema 30 combines the main-branch registration changes through version 29 with
the viewer-tag projection and hot-path indexes from the image-backup branch
ancestry. The new identity forces existing models from either branch to rebuild.
Startup regression coverage includes a version 29 model without `viewer_tags`,
with the repository revision unchanged.

### Public identity history queries (schema version 31)

`AuthoredContentRepository::visibleIdentityPage` returns at most 100 rows
(default 50), newest creation time first with descending post ID as the stable
tie-breaker. Both threads and posts use these authored-post keys. Position
boundaries contain `created_at` and `post_id`, never transient SQLite row IDs.
Counts and page/existence queries share `identityEligibility`; existing username
aggregate interfaces retain their previous behavior. A deleted boundary remains
usable through key comparisons. An empty positioned list offers first-page
recovery and a return direction when eligible rows remain.
