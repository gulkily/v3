# `v3` CLI Reference

`./v3` is a thin dispatcher over the PHP scripts in `scripts/`. Every subcommand
below can also be invoked directly as `php scripts/<script>.php ...`; the `./v3`
form is the shorthand used elsewhere in this repo's docs.

Run `./v3` with no arguments to print the same command list from the script
itself (useful if this document drifts from `v3`).

## CLI error-handling contract

When adding or changing a `./v3` command or worker command:

- validate the command and its options before filesystem, database, network, or
  other state-changing work begins;
- accept only documented options and reject unknown commands or options with a
  nonzero exit code;
- write a concise error and the relevant usage text to stderr—never expose an
  uncaught PHP exception or stack trace;
- support `-h` and `--help` with exit code 0; and
- add a regression test through the `./v3` dispatcher for unknown-option
  handling.

Most data-touching commands accept optional positional `repository_root` and
`database_path` arguments. When omitted they fall back to, in order:

1. the relevant `FORUM_*` environment variable (`FORUM_REPOSITORY_ROOT`,
   `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`)
2. the default local repository/database bootstrapped under `state/`

See [Local Run](../../README.md#local-run) in the README for the defaults
and the bootstrap flow.

## Synchronize the Feature Development Process documentation

```
./v3 fdp sync [--repository-root=/path/repository] [--remote-url=https://github.com/gulkily/fdp.git]
```

Updates the vendored `docs/fdp/` subtree from `gulkily/fdp`'s `main` branch and
commits the resulting documentation update. Run it from a clean target working
tree. By default it updates this checkout; use `--repository-root` to update
another v3 copy from the same command. The command adds the `fdp` remote when
missing. When it already exists, the command requires its source URL to be
`https://github.com/gulkily/fdp.git`, preventing an accidental sync from a
different local checkout or fork. Pass `--remote-url` only to intentionally
replace that remote, such as when repairing it or testing a fork.

The command also repairs legacy FDP imports whose recorded upstream commit was
rewritten, so later syncs use the ordinary subtree pull workflow.

## Start the local dev server

```
./v3 start [--listen-all|host:port]
```

Starts the PHP built-in dev server on `127.0.0.1:8000` by default.

- `--listen-all` — bind `0.0.0.0:8000` instead of `127.0.0.1:8000`
- `host:port` — bind an explicit address or bare port number instead of the default

## Run the test suite

```
./v3 test
```

Runs the custom test runner (`tests/run.php`) covering parser, rebuild, and
app smoke tests.

- any arguments — passed straight through to the test runner, e.g. a specific test class name

## Check the live OpenPGP asset contract

```
./v3 openpgp smoke [--origin=zenmemes.com]
```

Performs a read-only live check of the HTTP and HTTPS browser runtime paths.
For each scheme it reads `window.__forumAssetPaths` from the public page, selects
the same OpenPGP v5 (HTTP) or v6 (HTTPS) asset the browser would use, then also
checks both legacy raw OpenPGP bundle URLs used by cached clients. It reports
the selected URL, status, content type, redirects, and a failure reason. The
default host is `zenmemes.com`; use `--origin=staging.example` to check a staging
host. No records, browser identities, or posts are created.

## Run the manual OpenPGP production canary

```
npm install
./v3 openpgp canary --confirm-production-write --browser-executable=/path/to/chromium
```

This is deliberately not a CI or deployment command. After the read-only
smoke passes, manually run the canary from a machine with Node 18+ and local
Chromium. It uses `which chromium` automatically; pass
`--browser-executable=/path/to/chromium` only when that lookup is unsuitable.
It opens an isolated **HTTP** browser profile, creates a new identity using the
consistent username `release-check`, publishes “New release just dropped,
making sure it works,” reloads the post, and exercises the existing browser identity path to
confirm no second username prompt appears. It leaves the identity and post in
place intentionally. Do not rerun it after an ambiguous result—first inspect
the reported post URL. `--confirm-production-write` is mandatory.

## Inspect operator status

```
./v3 status [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3]
```

Read-only, concise status for the repository, read model, shared execution
lock, and background queue. It reports a read-model rebuild task as `queued`,
`running`, `failed`, or `absent`, then prints the relevant next action.

- `--repository-root=...` — canonical repository to compare with read-model metadata
- `--database-path=...` — read-model SQLite database to inspect
- `--queue-database-path=...` — task-queue SQLite database to inspect

The command remains useful when optional runtime files are missing or
unreadable: it reports the affected state without creating a queue, lock, or
read-model database. A `Shared lock: locked` result only means protected
activity is in progress; it does not prove a manual rebuild is running.
Use `./v3 task-queue status` for individual task details and
`./v3 fast-score status` for Fastmod work details.

## Open the terminal operator UI

```
./v3 tui
```

Opens a `whiptail` dashboard in an interactive terminal. It displays the
canonical `./v3 status` result and a redacted effective private-config view,
including each value's source. Environment overrides are read-only in the UI;
manage site feature flags through `/tools/feature-flags/`.

The launcher shows the selected command before running one of its fixed
read-only entries: `./v3 status`, `./v3 task-queue status`, or
`./v3 fast-score status`. It accepts no command arguments and does not edit
private config, modify feature flags, enqueue work, or offer destructive
workflows. The private-config editor and guided destructive workflows remain
separate follow-up work.

The LLM connection editor offers OpenAI, OpenRouter, Anthropic, stub, and
custom-provider presets. It masks API-key entry, shows a redacted review before
an atomic save, and refuses partial editing when any LLM connection setting is
set through the environment; change that deployment setting and restart instead.

`whiptail` and interactive stdin, stdout, and stderr are required. If either
is unavailable, `./v3 tui` makes no change and directs the operator to:

```
./v3 status
./v3 private-config view
```

## Rebuild the SQLite read model

```
./v3 rebuild [repository_root] [database_path]
./v3 rebuild diagnose [repository_root] [database_path]
./v3 rebuild recover --confirm [repository_root] [database_path]
```

Rebuilds the SQLite read model from canonical records in the repository root.
Reports source scanning, candidate construction and validation, lock wait, and
promotion phases. During candidate construction, it emits read-model stages and
bounded record-parsing progress checkpoints. It also prints source record
counts (posts, identities, approval seeds) and resulting read-model table counts
(posts, threads, profiles, activity).

`diagnose` is read-only: it reports SQLite sidecars, the application rebuild
lock, and Linux `/proc` file holders. If a stopped process left sidecars behind,
`recover --confirm` snapshots and archives the database and all sidecars together
under `state/cache/read-model-recovery-*`, then rebuilds a fresh derived model.
It refuses recovery while the lock or an open file holder is detected.

- `repository_root` — canonical records checkout to rebuild from
- `database_path` — SQLite file to write the read model to

## Manage the background task queue

```
./v3 task-queue enqueue-rebuild [--queue-database-path=/private/path/tasks.sqlite3]
./v3 task-queue enqueue-fast-score [--queue-database-path=/private/path/tasks.sqlite3]
./v3 task-queue enqueue-offline-snapshot [--queue-database-path=/private/path/tasks.sqlite3]
./v3 task-queue reset-recovery [--queue-database-path=/private/path/tasks.sqlite3]
./v3 task-queue run [--limit=1] [--score-limit=25] [--work-limit=250] [--dry-run] [--quiet] [--verbose] [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3]
./v3 task-queue status [--limit=25] [--queue-database-path=/private/path/tasks.sqlite3]
./v3 task-queue cron [--log=<application-private-log-path>]
```

A small SQLite-backed job queue (`scripts/task_queue.php`) serializes
read-model rebuilds, Fastmod sweeps, offline publication, and requested
agent replies so concurrent triggers coalesce instead of racing. `docs/runbooks/production_deploy.md` and
`docs/runbooks/operator_recovery.md` reference this command and depend on it
being installed via cron.

- `enqueue-rebuild` — enqueues a `read-model` rebuild task (a no-op if one is
  already queued/running); `--queue-database-path=...` overrides the default
  queue database location
- `enqueue-fast-score` — enqueues the coalesced Fastmod sweep (also a
  no-op if one is already queued/running). It evaluates all nonempty posts over
  successive bounded runs when Fastmod is enabled.
- `enqueue-offline-snapshot` — enqueues the coalesced public offline snapshot
  publication (also a no-op if one is already queued/running).
- `reset-recovery` — resets the blocked automatic read-model schema-recovery
  circuit. It is an operator action; it does not enqueue or run a rebuild.
- `run` — claims and runs up to `--limit` queued tasks (default 1), recovering
  any abandoned in-progress tasks first; guarded by an exclusive file lock so
  concurrent invocations don't double-run. `--dry-run` reports the queued
  count without running anything; `--quiet` suppresses progress output;
  `--repository-root=...`/`--database-path=...` override the read model to
  rebuild against. `--score-limit=...` independently sets the maximum provider
  calls a claimed Fastmod sweep may make (default 25); provider failures and
  invalid structured responses count because a request was made.
  `--work-limit=...` bounds all examined Fastmod work rows, including local
  heuristic exclusions (default 250). `--limit=...` is only the maximum queue
  tasks claimed. `--verbose` reports each Fastmod result and provider request
  as it happens. `--quiet` suppresses all worker progress output, including
  verbose output when both options are supplied.
- Agent replies are enqueued automatically when an approved user requests one;
  they are processed by `run` and do not have a separate worker command.
- `status` — prints queued/running/completed/failed counts plus the
  `--limit` (default 25) most recent tasks with attempt counts, failure codes,
  and the latest private rebuild checkpoint when available. Terminal task and
  executor history is bounded; active tasks are retained.
- `cron` — prints one ready-to-install crontab line running the worker once a
  minute. The default log path is
  `<application-root>/state/private/task_queue_cron.log`; `--log=...` changes
  the path baked into the line.

## Audit or backfill Fastmod

```
./v3 fast-score status [--limit=10] [--verbose]
./v3 fast-score audit --include-existing [--database-path=/path/read-model.sqlite3]
./v3 fast-score backfill --include-existing --confirm --max-posts=N --max-cost-usd=N [--database-path=/path/read-model.sqlite3]
./v3 fast-score retry --post-id=... --content-hash=... --rubric-revision=...
./v3 fast-score invalidate --post-id=... --content-hash=... --rubric-revision=...
./v3 fast-score smoke --post-id=... [--database-path=/path/read-model.sqlite3]
./v3 fast-score prune [--before=ISO-8601]
```

`audit --include-existing` is a read-only historical count and configured-model
cost estimate. `backfill` requires separate historical scope, confirmation,
post-count, and spend bounds; it creates one private, bounded batch and queues
the normal worker. `status` provides the next action, distinguishes regular
work from historical backfill, and shows batch progress and reservation state.
Use `--verbose` for recent individual work rows. See
[Fastmod](fast_post_scoring.md) for pricing configuration, retention, and the
controlled operator workflow.

## Import content from a remote instance

```
./v3 import-instance <name|hostname|url> [--sources=/private/instances.json] [--dry-run] [--repository-root=/path/repository] [--database-path=/path/index.sqlite3] [--static-html-root=/path/static_html]
./v3 import-instance --resume [--repository-root=/path/repository] [--database-path=/path/index.sqlite3] [--static-html-root=/path/static_html]
```

Downloads the source's public repository archive, merges supported public forum
content while preserving destination settings/approval authority, and publishes
local views. Hostnames use HTTPS; short names require a JSON object mapping names
to URLs in `--sources`. URLs may include an instance base path. `--dry-run`
previews without changing destination content. Conflicts retain local records.

Exit codes are 0 for complete supported-content results, 2 for partial results
requiring review, and 1 for failures. Reports enumerate exclusions and unsupported
records. `--resume` recovers a saved interrupted run without another download;
use the original destination options and site profile. The destination must be
initialized with a clean Git checkout. Scheduling, authenticated sources, and
web controls are deferred.

See [Instance Content Import](../runbooks/instance_content_import.md) for coverage,
alias examples, fixed transfer/archive limits, publication, and recovery.

## Import a repository archive

```
./v3 import-repository <archive.tar.gz> [repository_root] [database_path] [artifact_root] [--dry-run] [--no-commit]
```

Imports a `.tar.gz` repository archive into the target repository root,
rebuilds the read model, and rebuilds static artifacts (when an artifact root
is resolved). The command reports each phase (validation, extraction, record
indexing, staging, commit, and derived rebuilds) and emits bounded record-file
progress checkpoints while it processes the archive.

- `archive.tar.gz` — required path to the archive to import
- `repository_root` — canonical records checkout to import into
- `database_path` — SQLite file to rebuild after import
- `artifact_root` — static HTML output directory to rebuild after import
- `--dry-run` — validate the archive without writing anything
- `--no-commit` — skip the git commit step after import

## Inspect a thread's attributes and labels

```
./v3 thread-attributes <thread_id_or_record> [repository_root] [database_path]
```

Diagnostic/read-only. Prints the target item, canonical root post
attributes, derived read-model thread attributes, effective labels, and each
thread-label record that contributes a label.

- `thread_id_or_record` — a thread ID, post ID, canonical record path, or an unambiguous canonical record filename stem
- `repository_root` — canonical records checkout to read from
- `database_path` — read-model SQLite file to read from

## Delete a canonical record

```
./v3 delete-record <record_path_or_id> [repository_root] [database_path] [artifact_root]
```

Deletes a canonical file under `records/` with `git rm`, commits the
removal, rebuilds the read model, and rebuilds static artifacts when an
artifact root is resolved (via argument or `FORUM_PUBLIC_ARTIFACT_ROOT`).

- `record_path_or_id` — a relative path (e.g. `records/thread-labels/thread-label-20260530000001-zenrules.txt`) or an unambiguous filename stem (e.g. `thread-label-20260530000001-zenrules`)
- `repository_root` — canonical records checkout to delete from
- `database_path` — SQLite file to rebuild after deletion
- `artifact_root` — static HTML output directory to rebuild after deletion

## Backfill Unicode-risk analysis for posts

```
./v3 unicode-risk-backfill [repository_root] [database_path] [--with-llm]
```

Backfills Unicode-risk analysis for existing posts. By default uses
deterministic detection only.

- `repository_root` — canonical records checkout to analyze
- `database_path` — SQLite file to read posts from and write results to
- `--with-llm` — also run the configured LLM provider, not just deterministic detection
- `--deterministic-only` — explicitly disable LLM use (useful after `--with-llm` earlier in the same invocation)

## Build static HTML artifacts

```
./v3 build-static [repository_root] [database_path] [artifact_root]
```

Builds and atomically activates a complete static HTML release. It creates the
candidate read model and HTML release before replacing either live pointer, so
normal requests continue using the prior complete state while the command runs.

- `repository_root` — canonical records checkout to render from
- `database_path` — read-model SQLite file to render from
- `artifact_root` — static release root; defaults to `state/static_html` when not given via argument or `FORUM_STATIC_HTML_ROOT`. The active release is `artifact_root/current`.

`FORUM_STATIC_DETAIL_PAGES_ENABLED` defaults to `true`. Set it to `false`
through the instance feature-flags page (or as an environment override) to omit
the individual thread and post HTML files from a full release. Shared pages,
including QDB's home, Latest, Top, and Leetness pages, are still generated;
individual quote and thread requests fall back to PHP. Run a full build after
changing this flag so the active release reflects it.

### Refresh only shared static pages

```
./v3 build-static --shared-only [repository_root] [database_path] [artifact_root]
```

Quickly creates and activates a release by copying the active complete release
and rerendering only shared routes (Board, Activity, Users, Tools, and similar
pages), including the service worker and its referenced assets. It does not
rebuild the read model or render tag, thread, post, or profile pages. Use it
after deploying presentation or fingerprinted-asset changes when the active
release already exists. It preserves the existing detail pages, so use the
full `build-static` after content changes or changes to a detail-page template.

## Archive and remove a thread

```
./v3 archive-thread <thread_id> [repository_root] [database_path] [artifact_root] [archive_path]
```

Archives a full thread (root post, replies, supporting public keys) into a
`.tar.gz` archive, removes the component records from the live repository,
and rebuilds affected artifacts.

- `thread_id` — required ID of the thread to archive
- `repository_root` — canonical records checkout to archive from and remove records from
- `database_path` — SQLite file to rebuild after archiving
- `artifact_root` — static HTML output directory to rebuild after archiving
- `archive_path` — output path for the `.tar.gz`; defaults to a generated path under the project's archive directory

## Manage the private LLM config

```
./v3 private-config [view|edit|refresh-template|--view|--edit|--force|--api-key-stdin] [--path=/private/path/secrets.php]
```

Creates or updates the private PHP config consumed by
`ForumRewrite\Support\PrivateConfig` (LLM provider, API key, and related
settings for agent-reply features). `LLM_PROVIDER` is required (no default);
supported values are `openai`, `openrouter`, `anthropic`, `stub`, and
OpenAI-compatible gateways. Legacy `DEDALUS_*` settings are still read as
fallbacks, but new writes use `LLM_*` names.

- `view` / `--view` — print a redacted summary and update reminders without writing the file
- `edit` / `--edit` — open an existing config in `$VISUAL`, `$EDITOR`, or `vi`; it does not print secrets or create a missing file
- `refresh-template` — rewrite the file with current comments/examples while preserving existing values
- `--force` — overwrite without the usual confirmation/skip behavior
- `--api-key-stdin` — read the API key from stdin instead of an argument, so it never lands in shell history: `printf '%s\n' "$LLM_API_KEY" | ./v3 private-config --api-key-stdin`
- `--path=...` — write to a specific file instead of the default `../forum-private/secrets.php` (relative to this checkout)

## Show agent-reply diagnostics

```
./v3 agent-reply status [post_id] [--limit=25] [--database-path=/path/post_index.sqlite3]
```

Read-only diagnostics for skipped or failed agent-reply generation rows.

- `post_id` — when given, shows that post's rows (limit defaults to 10, capped at 100); when omitted, shows the most recent skipped rows (limit defaults to 25)
- `--limit=...` — maximum number of rows to show
- `--database-path=...` — read-model SQLite file to read from

## Test the live LLM provider connection

```
./v3 agent-reply test [--timeout=30]
```

Sends one live plain-text task prompt to the configured LLM provider to validate
the API key and model/service reachability.

- `--timeout=...` — seconds to wait for the provider response

## Run the local agent-reply test suite

```
./v3 agent-reply test-local
```

Runs the local (non-live) agent-reply test suite: `AgentReplyGenerationTest`,
`AgentReplyCommandTest`, and targeted `LocalAppSmokeTest` /
`WriteApiSmokeTest` cases covering status and task-queue fulfillment.

- no parameters

## Run approved Codex handoff requests

```
./v3 codex-handoff run [--limit=1] [--dry-run] [--database-path=/path/post_index.sqlite3] [--codex-bin=/path/codex]
```

Runs approved Codex handoff requests through the local `codex` binary.

- `--limit=...` — maximum number of handoffs to run
- `--dry-run` — report the approved-handoff count without running anything
- `--database-path=...` — read-model SQLite file to read handoffs from
- `--codex-bin=...` — path to the `codex` executable; also overridable via `FORUM_CODEX_EXECUTABLE`

## Run the local Codex handoff test suite

```
./v3 codex-handoff test-local
```

Runs the local Codex handoff test suite: `CodexHandoffDraftServiceTest`,
`CodexHandoffStoreTest`, `CodexHandoffRunnerTest`, and the `WriteApiSmokeTest`
cases covering the handoff API, approval/rejection flow, development-tag
requirements, activity lifecycle, and UI bindings.

- no parameters

## Seed an approved identity (shorthand)

```
./v3 approve <identity_id> [seed_reason] [repository_root] [database_path]
```

Shorthand alias for `./v3 approval seed` (see below for parameter details).

## Seed an approved identity

```
./v3 approval seed <identity_id> [seed_reason] [repository_root] [database_path]
```

Seeds an initial approved identity (e.g. the first admin) so
`FORUM_APPROVED_MEMBERS_ONLY` can be enabled without a chicken-and-egg
approval problem.

- `identity_id` — required identity to seed as approved
- `seed_reason` — free-text reason recorded with the seed; defaults to `"initial approved user"`
- `repository_root` — canonical records checkout to write the approval seed to
- `database_path` — SQLite file to rebuild after seeding

## Approve an identity

```
./v3 approval approve <approver_identity_id> <target_identity_id> [repository_root] [database_path] [artifact_root]
```

Records an approval of `target_identity_id` by an already-approved
`approver_identity_id`, and rebuilds affected artifacts when an artifact root
is resolved.

- `approver_identity_id` — required, must already be an approved identity
- `target_identity_id` — required identity being approved
- `repository_root` — canonical records checkout to write the approval to
- `database_path` — SQLite file to rebuild after approving
- `artifact_root` — static HTML output directory to rebuild after approving

## Standalone scripts (not wired into `./v3`)

These live in `scripts/` but have no `./v3` dispatcher entry — invoke them
directly with `php scripts/<script>.php`.

### Audit post signatures

```
php scripts/audit_post_signatures.php [repository_root]
```

Scans canonical post records and reports counts for `signed_valid`,
`missing_signature`, `invalid_signature`, `unknown_author_key`, and
`anonymous_unsigned`.

- `repository_root` — canonical records checkout to audit; defaults to `FORUM_REPOSITORY_ROOT` or the bootstrapped local repository

### Build the SQLite query catalog

```
php scripts/build_sqlite_query_catalog.php [source_directory] [browser_asset_path] [local_pack_path]
```

Regenerates the SQLite viewer's preset query catalog from `queries/sqlite/`:
rewrites the generated block inside the browser viewer asset
(`public/assets/sqlite_viewer.js`) and writes a local `.sql` query pack.
Run this after adding or editing a query under `queries/sqlite/`.

- `source_directory` — query source directory; defaults to `queries/sqlite`
- `browser_asset_path` — viewer JS asset to rewrite the generated block in; defaults to `public/assets/sqlite_viewer.js`
- `local_pack_path` — output path for the local `.sql` query pack; defaults to `public/assets/sqlite_query_catalog.sql`

### Check static artifacts for missing fingerprinted assets

```
php scripts/check_static_artifacts.php [artifact_root]
```

Scans every `.html` file under `artifact_root` for fingerprinted asset
references (`/assets/name.<12-hex>.ext`) and fails (exit 1, listing each
missing reference) if any referenced asset file doesn't exist. Useful after
a static build or asset-fingerprint change to catch broken references
before they ship.

- `artifact_root` — directory to scan; defaults to `FORUM_PUBLIC_ARTIFACT_ROOT` or `public/`
