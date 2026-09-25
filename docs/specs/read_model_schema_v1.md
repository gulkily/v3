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

## Core read-model tables

Defined in `src/ForumRewrite/ReadModel/ReadModelBuilder.php`'s
`createSchema()`. Fully rebuilt (not incrementally patched) on every
`ReadModelBuilder::build()` run.

### `metadata`

Single-row-per-key bookkeeping about the last rebuild.

| Column | Type | Notes |
| --- | --- | --- |
| `key` | TEXT PK | e.g. `schema_version`, `repository_root`, `repository_head`, `rebuilt_at`, `rebuild_reason`, `thread_label_invalid_count` |
| `value` | TEXT | |

### `posts`

One row per canonical post record (both thread roots and replies).

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
| `author_identity_id` | TEXT NULL | |
| `author_profile_slug` | TEXT NULL | |
| `author_label` | TEXT | default `'guest'` |
| `post_tags_json` | TEXT | JSON array, default `'[]'` |
| `post_score_total` | INTEGER | default 0 |
| `approved_flag_count` | INTEGER | default 0 |
| `is_hidden` | INTEGER | default 0; moderation flag |
| `hidden_reason` | TEXT NULL | |
| `sequence_number` | INTEGER | |

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

### `profiles`

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
`activity_post_id_idx (post_id)`, `activity_action_key_idx (action_key)`.

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
