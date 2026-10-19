# `v3` CLI Reference

`./v3` dispatches to scripts in `scripts/`. Use the launcher for local startup
and dependency checks: it handles executable/version checks and server shutdown.
Many other PHP commands can also be invoked directly as
`php scripts/<script>.php ...`.

Run `./v3 help`, `./v3 --help`, or `./v3 -h` to print the command list.
Running `./v3` with no arguments also prints it (with exit code 1).
Use `./v3 import-instance --help` for remote-import options and recovery.

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

## Synchronize Railspike workflow documentation

```bash
./v3 railspike sync [--repository-root=/path/repository] [--remote-url=https://github.com/rlspk/railspike.git] [--ref=v0.2.0-beta.1]
./v3 fdp sync ... # deprecated alias; updates Railspike, never FDP
```

Updates the vendored `docs/railspike/` subtree to an explicit release tag
(`--ref`, default the pinned `v0.2.0-beta.1`), creating Git update commits. Branch
names such as `main` are refused, so a plain sync never moves the installed
version. The tag must descend from the commit in `docs/railspike/RAILSPIKE_SOURCE.md`,
which the command updates and commits after a successful merge. The target defaults to this checkout; `--repository-root`
selects another already-migrated repository root. A clean working tree and
complete subtree import history are required. Unmigrated targets receive
migration guidance without modification.

The default source is `https://github.com/rlspk/railspike.git`. An existing
`railspike` remote must match it; `--remote-url` explicitly chooses another
source or fork. The selected source is recorded as the `railspike` remote only
after a successful merge (including a no-op). It must retain the recorded
upstream revision in its tag history. No `fdp` remote is changed.

Review upstream before updating; the command does not pause for diff approval.
For conflicts, resolve and commit or use `git merge --abort` in the target.
See the [project workflow guide](railspike_workflow.md) for review, recovery,
and existing-plan policy.

## Start the local dev server

```
./v3 start [--listen-all|host:port] [--verbose|--quiet] [--log-file=/path/dev.log]
```

Starts the PHP built-in dev server on `127.0.0.1:8000` by default.
Checks local-server prerequisites before application loading, logs, or bootstrap.
Missing required capabilities produce runtime-specific recovery guidance.
On Ubuntu 22.04/PHP 8.1 or 24.04/PHP 8.3 distribution packages, terminal input/output
and root or potentially usable sudo allow a reviewed `Recover missing dependencies
and continue startup? [Y/n]` prompt. Enter/Y/y accepts; n/N/invalid input/EOF or
pre-execution cancellation stops without changes. Optional intl is a separate,
default-off choice; optional-only limitations never trigger recovery.
Authenticate sudo after consent. Success plus fresh readiness resumes startup
once with the original address, logging options and caller identity; failures
return 1 without starting a listener. Redirected startup never prompts or installs.
Options are validated before recovery. Stop the launcher with Ctrl+C or SIGTERM.

- `--listen-all` — bind `0.0.0.0:8000` instead of `127.0.0.1:8000`
- `host:port` — bind an explicit address or bare port number instead of the default
- `--verbose` / `--quiet` — show all requests / server errors only
- `--log-file=...` — append plain request logs to the chosen file

## Check local-server prerequisites

```
./v3 check-dependencies [--install|--help]
./v3 dependency-check [--install|--help]
```

Uses the same checks as startup without starting the server or changing application
state. Without `--install`, it is read-only and noninteractive. Reports Bash 4.1+,
Git, PHP CLI 8.1+, PDO/PDO SQLite, mbstring, and PHP
process execution; intl, PCNTL, terminal shell execution, and `stty` are optional.
Returns 0 when required capabilities pass (even with warnings), or 1 for missing,
disabled, or unusable required capabilities. `-h`/`--help` returns 0 without
checking PHP or Git. `--install` selects the interactive mode below; other
arguments are rejected.

Status rows use ✓/✗/!/? for available, unavailable, optional limitations, and
unchecked capabilities. Runtime details and recovery instructions follow the
readiness result. Terminals show colored statuses and highlighted setup commands;
redirected output and `NO_COLOR` use plain text with the same symbols.

With `--install`, Ubuntu 22.04/PHP 8.1 or 24.04/PHP 8.3 distribution users can review required
packages, explicitly select optional intl, and confirm the full resolved APT
transaction. Confirmation requires terminal input/output and exact `yes`;
no unattended bypass is supported. Root or sudo privileges are needed only after
confirmation. Metadata refresh precedes installation; a changed transaction needs
new confirmation. Installation preserves configuration and rejects removals,
unverified sources and other PHP runtime families. Custom runtimes, PATH or
configuration problems, and unsupported environments retain manual recovery.

Installation returns 0 only when the requested package operation succeeds and
required readiness passes, or a ready no-op (including declined optional-only
setup). It returns 1 for refusal, cancelled installation, package failure, or
remaining required problems. Package outcome is separate from readiness. Failure
or interruption may leave partial changes; no rollback, repair or automatic retry
is performed. If package processes survive interruption, reassessment is deferred
and manual inspection is required. Declining before execution makes no changes;
declining a revised transaction after refresh leaves only refreshed metadata.

After explicit `--install`, retry startup manually with the same options. Recovery
accepted from `start` continues automatically only after success. Neither check mode validates
repository, database, permissions, listening address, other-command, or deployment
readiness. See [installation scope, source restrictions and recovery](../../README.md#install-missing-local-server-dependencies)
and [external launcher prerequisites](../../README.md#launcher-and-optional-support).

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
Reports source scanning, candidate construction and validation, lock wait,
promotion, and offline snapshot readiness. Indexing reports parsing, history
resolution, sorting/derivation, and writes separately, with elapsed milliseconds.
A parsing count such as `210/210` means those files have been examined; it does
not mean indexing or the rebuild is finished. Counters name their work unit
(records, posts, threads, profiles, or history lines), include skipped records,
and emit at most 100 intermediate checkpoints plus start/final boundaries per
loop. Approval replay identifies each pass. Empty loops report `0/0`.

Thread labels, post reactions, and signed feature-flag changes resolve source
commits in a single history walk per nonempty family, rather than one Git
invocation per record. Other long operations identify their start and outcome;
blocking Git, storage, and lock operations do not emit a heartbeat or estimate
remaining time. A failed operation reports `failed`, with existing errors and
recovery guidance preserved. Missing Git history retains the existing nullable
provenance/omitted-flag-activity behavior; a failed Git read may therefore be
followed by successful completion of the containing fallback operation.

Timings cover the named operation and may nest; do not sum them to calculate
total rebuild duration. Repeated timing keys accumulate across approval passes
or repeated history reads. The final `Elapsed` line measures the overall CLI
run. Output also includes source counts (posts, identities, approval seeds) and
resulting model counts (posts, threads, profiles, activity). Phase timings and
query-count comparisons help locate remaining costs; production speed depends
on repository history and storage.

`diagnose` is read-only: it reports SQLite sidecars, the application rebuild
lock, and Linux `/proc` file holders. If a stopped process left sidecars behind,
`recover --confirm` snapshots and archives the database and all sidecars together
under `state/cache/read-model-recovery-*`, then rebuilds a fresh derived model.
It refuses recovery while the lock or an open file holder is detected.

- `repository_root` — canonical records checkout to rebuild from
- `database_path` — SQLite file to write the read model to

## Sweep retracted local content

```
./v3 retraction-sweep preview|run|status [--json] [--limit=250] [--repository-root=/path/repository] [--static-html-root=/path/static] [--exchange-database-path=/private/exchanges.sqlite3] [--state-path=/private/sweep.json]
```

Preview is non-mutating. Run executes one bounded batch immediately; repeat while
`remaining` is true. The normal queue worker also discovers and continues sweeps.
See [coverage, exclusions, progress and recovery](retraction_sweep.md).

## Manage the background task queue

```
./v3 task-queue enqueue-retraction-sweep [--queue-database-path=/private/path/tasks.sqlite3]
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

- `enqueue-retraction-sweep` — coalesces or explicitly retries local retraction
  cleanup. Automatic discovery uses the existing cron worker; completed scans
  are reconciled every five minutes and terminal failures stay visible.
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
./v3 import-instance <name|hostname|url> [--sources=/private/instances.json] [--dry-run] [--verbose] [--repository-root=/path/repository] [--database-path=/path/index.sqlite3] [--static-html-root=/path/static_html]
./v3 import-instance --resume [--repository-root=/path/repository] [--database-path=/path/index.sqlite3] [--static-html-root=/path/static_html]
```

Downloads the source's public repository archive, merges supported public forum
content while preserving destination settings/approval authority, and publishes
local views. Hostnames use HTTPS; short names require a JSON object mapping names
to URLs in `--sources`. URLs may include an instance base path. Conflicts retain
local records. Legacy creation dates are recovered from isolated source history
and retained as hash-bound metadata without changing signed post bytes. See the
[import runbook](../runbooks/instance_content_import.md) for limits and recovery.

- `--dry-run` — download and validate the archive, then report proposed imports,
  duplicates, conflicts, and exclusions without changing destination records,
  commits, the read model, or published views. Temporary downloads and lock files
  may be created. Cannot be combined with `--resume`.
- `--verbose` — list every rejected/excluded file and dependency root cause.
  The default output groups review causes and summarizes intentional exclusions.

Preview an import:

```bash
./v3 import-instance https://forum.example --dry-run
```

Remove `--dry-run` to perform the import.

Exit codes are 0 for complete supported-content results, 2 for partial results
requiring review, and 1 for failures. Reports group exclusions and rejected
records; `--verbose` includes per-file details. `--resume` recovers a saved interrupted run without another download;
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

This is the operator removal mechanism. Authors retract their own posts through
the site instead; see [Author post retraction](author_post_retraction.md).

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

`FORUM_STATIC_DETAIL_PAGES_ENABLED` defaults to `false`. Full builds skip
bulk thread and post rendering; individual thread, post, and numeric quote
requests use PHP at runtime. Shared pages (including QDB's home, Latest, Top,
and Leetness), tag/profile pages, and the offline snapshot are still generated.

To pre-build detail HTML for static-only hosting, enable **Static detail pages**
on the instance feature-flags page or set
`FORUM_STATIC_DETAIL_PAGES_ENABLED=true` in the build environment. An explicit
environment value takes precedence over an instance setting; either explicit
setting takes precedence over the default. Existing explicit enablement remains
in effect after an upgrade, so disable it if these builds are still slow.

Run a full build after changing the effective flag value. A full build activates
a fresh release, removing old detail HTML from the active release when disabled;
`--shared-only` preserves existing detail artifacts and cannot make this
transition. Runtime detail hosting requires PHP routing (as provided by the
checked-in Apache configuration or local PHP router). Static-only hosts should
opt in before building/deploying. To restore static details, enable the flag and
run another full build.

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

Startup dependency failures list missing capabilities, group required Ubuntu extension installs into one command, and show optional recovery separately. Commands are highlighted on terminals unless `NO_COLOR` is set. `dependency-check` is an alias for `check-dependencies`.

## Verify or restore image backups

`./v3 images verify|restore <images.tar> <repository_root> <database_path>
[--image-root=/private/images]` checks a revision-matched image/repository pair.
`verify` stages and validates without activation; `restore` atomically installs
into a fresh private image directory or accepts an identical existing store.
It never overwrites a different store. Exit 0 means complete verification/restore;
exit 1 includes actionable failure text. Rebuild the read model after restoring.
See [Image backup and recovery](../runbooks/image_backup.md) for the full sequence,
limits, deployment requirements, and interrupted-operation recovery.

### Agent reply completion allowance

`AGENT_REPLY_MAX_COMPLETION_TOKENS` in private configuration (or the environment)
controls reply generation: default 8192, integer range 1–32768. Invalid values
fail before a provider call. Larger allowances can increase cost and latency;
they do not guarantee model support or successful completion. Exhausted replies,
including partial text, are rejected rather than published. Correct settings and
explicitly retry affected work; no automatic budget escalation occurs.

### Recover one failed analysis

Inspect the post with `./v3 agent-reply status --post-id=POST` and correct the
provider/model or completion allowance first. Use the full content hash from
that request's private record. Requeue and process only its returned task ID:

```bash
./v3 task-queue retry-agent-reply --post-id=POST --content-hash=HASH
./v3 task-queue run --task-id=TASK_ID
```

Pass the same repository, read-model, and queue path overrides to both commands
when working outside the default instance. Recovery preserves the original mode,
rejects changed/ineligible/published targets, and is safe to repeat if enqueueing
was interrupted. Running a completed task again makes no provider call. A
configuration or exhaustion failure needs another deliberate retry after correction.
`status` exposes outcome details in progress/history; a skipped request is not a
published reply. Targeted runs do not reconcile or process the unrelated backlog.

For one failed moderation work item, use its full content and rubric hashes:

```bash
./v3 fast-score retry --post-id=POST --content-hash=HASH --rubric-revision=RUBRIC --run
```

`--run` validates current content/rubric and processes only that pending/failed
normal moderation item under the worker lock, with at most one provider call.
It does not enqueue a broad sweep. Without `--run`, existing enqueue-for-sweep
behavior remains available. Backfill work retains its separate budget controls.

### Scripture responses and on-demand expansion

Scripture responses follow editorial guidance rather than word counts or verse
quotas. Edit `prompts/agent_response_scripture_oriented_analysis.txt` for initial
answers and `prompts/agent_response_scripture_expansion.txt` for further reflection.
Both ask for appropriately quoted scripture with references/translation labels;
when no biblical translation is supplied, guidance prefers accurately recalled
KJV wording. Uncertain wording should be labeled as paraphrase. The technical
completion allowance remains independent of these writing instructions.

Approved members can select **Expand** beneath a verified scripture response.
One shared expansion is generated by the existing task worker, displayed beneath
the initial answer, and available to authorized readers on thread/post pages.
The requester sees the result open when ready; other readers open it themselves.
No generation happens merely from opening stored supplementary content.

- `POST /api/expand_agent_reply` accepts `post_id` identifying the initial response.
- `GET /api/agent_expansion_status?post_id=...` returns progress, completion, or safe failure feedback.
- The existing `./v3 task-queue run` fulfills expansion work. Use its returned
  task ID with `--task-id` to process only that item.
- For failed expansion recovery, use `./v3 agent-reply status --post-id=INITIAL_REPLY`
  to find the target hash, correct the cause, then use `task-queue retry-agent-reply`
  with that initial reply ID/hash and run only the returned task ID. The original
  response and its sources must still be available and unchanged.

Canonical provenance and a stable expansion ID prevent duplicate publication and
survive read-model rebuilds. Changed/hidden/retracted sources suppress expansion;
requester approval is rechecked before publication. Expansions cannot recursively
request expansion. Existing responses need trusted canonical/request provenance
to qualify; copying the response prefix does not establish eligibility.

Static/offline views retain stored supplementary content without generation
controls. Snapshot schema 4 carries provenance and unavailable-expansion IDs;
updates upgrade older metadata columns and remove unavailable expansion content.

### Scripture quality and latency controls

These settings are opt-in until comparative evaluation passes:

- `SCRIPTURE_RESPONSE_GUIDANCE=current|revised|verified` selects the original prompt pair
  or `prompts/agent_response_scripture_oriented_analysis_revised.txt` and
  `prompts/agent_response_scripture_expansion_revised.txt`. Default: `current`.
  `verified` uses the source-owned quotation contract for initial replies and
  Expand. See [verified scripture operations](../specs/scripture_response_reliability.md).
- `SCRIPTURE_SOURCE_PATH` optionally overrides the local KJV JSON path; the pinned
  digest is always required. Missing/corrupt source permits explicit paraphrase
  only, or terminal failure; it never triggers a network download.
- `SCRIPTURE_RESPONSE_REASONING_EFFORT` defaults to blank (provider default).
  Explicit `minimal|low|medium|high` currently supports direct OpenAI GPT-5,
  GPT-5 mini, and GPT-5 nano, including their 2025-08-07 snapshots. Unsupported
  combinations fail before generation. This setting applies only to scripture
  initial replies and expansions, never moderation or other response modes.
- `SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH=true` wakes the existing queue worker
  for each newly queued scripture request. Default: false. The host needs CLI
  PHP, `proc_open`, and `nohup`; PHP must be on PATH for FPM/CGI hosts. The worker
  waits up to 60 seconds for the queue lock, then leaves work queued for normal
  scheduled recovery. Keep the regular worker schedule: dispatch is an accelerator,
  not a substitute for recovery. It never repeats a failed provider generation.
- `SCRIPTURE_RESPONSE_TIMING_ENABLED=true` appends phase markers (post ID, event,
  millisecond timestamp) to private `state/private/scripture_timing.jsonl`.
  Default: false. Disable after diagnosis and rotate/remove this private log as
  needed; it contains no forum text or credentials. Browser timing supplies the
  submission and visible-completion boundaries; phase durations must not be
  summed twice when work overlaps.

`GET /api/scripture_reply_status?post_id=SOURCE` reads existing initial-response
status without generating work. The browser polls pending scripture requests,
imports the canonical rendered reply card when ready, and preserves drafts.
Status respects discussion access and hidden/retracted sources/results. Existing
expansion status and polling remain available. Technical worker invocations may
use `task-queue run --task-id=N --lock-wait-seconds=0..60`; the default wait is zero.

To roll back candidate behavior, restore `current`, clear explicit reasoning,
and disable immediate dispatch. Existing canonical replies and queued requests
remain intact. Correct failed settings and use explicit targeted retry; changing
configuration does not rewrite old replies or silently regenerate failures.

For verified-mode rollback, disable `agent_response_requests_enabled` before
changing generation settings; follow the verified scripture operations guide.
Do not silently downgrade pending verified requests to unverified generation.

Scripture writer override: `SCRIPTURE_RESPONSE_MODEL` is blank by default (inherits `LLM_MODEL`). OpenAI scripture requests and Expand may select `gpt-5-mini` or `gpt-5.6-sol`; other response modes and structured moderation retain the shared model. Sol explicit reasoning is currently restricted to the evaluated `low` setting. This prepares isolated comparison; it does not activate verified guidance or establish release acceptance.
