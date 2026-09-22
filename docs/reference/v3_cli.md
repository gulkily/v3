# `v3` CLI Reference

`./v3` is a thin dispatcher over the PHP scripts in `scripts/`. Every subcommand
below can also be invoked directly as `php scripts/<script>.php ...`; the `./v3`
form is the shorthand used elsewhere in this repo's docs.

Run `./v3` with no arguments to print the same command list from the script
itself (useful if this document drifts from `v3`).

Most data-touching commands accept optional positional `repository_root` and
`database_path` arguments. When omitted they fall back to, in order:

1. the relevant `FORUM_*` environment variable (`FORUM_REPOSITORY_ROOT`,
   `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`)
2. the default local repository/database bootstrapped under `state/`

See [Local Run](../../README.md#local-run) in the README for the defaults
and the bootstrap flow.

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

## Rebuild the SQLite read model

```
./v3 rebuild [repository_root] [database_path]
```

Rebuilds the SQLite read model from canonical records in the repository root.
Prints source record counts (posts, identities, approval seeds) and resulting
read-model table counts (posts, threads, profiles, activity).

- `repository_root` — canonical records checkout to rebuild from
- `database_path` — SQLite file to write the read model to

## Import a repository archive

```
./v3 import-repository <archive.tar.gz> [repository_root] [database_path] [artifact_root] [--dry-run] [--no-commit]
```

Imports a `.tar.gz` repository archive into the target repository root,
rebuilds the read model, and rebuilds static artifacts (when an artifact root
is resolved).

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

## Manage the private LLM/Dedalus config

```
./v3 private-config [view|refresh-template|--view|--force|--api-key-stdin] [--path=/private/path/secrets.php]
```

Creates or updates the private PHP config consumed by
`ForumRewrite\Support\PrivateConfig` (LLM provider, API key, and related
settings for Dedalus/agent-reply features). `LLM_PROVIDER` supports
`dedalus`, `openai`, `openrouter`, `anthropic`, `stub`, and OpenAI-compatible
gateways. Legacy `DEDALUS_*` settings are still read as fallbacks, but new
writes use `LLM_*` names.

- `view` / `--view` — print a redacted summary and update reminders without writing the file
- `refresh-template` — rewrite the file with current comments/examples while preserving existing values
- `--force` — overwrite without the usual confirmation/skip behavior
- `--api-key-stdin` — read the API key from stdin instead of an argument, so it never lands in shell history: `printf '%s\n' "$LLM_API_KEY" | ./v3 private-config --api-key-stdin`
- `--path=...` — write to a specific file instead of the default `../forum-private/secrets.php` (relative to this checkout)

## Print the agent-reply cron install reference

```
./v3 agent-reply cron [--log=/var/log/forum-agent-replies.log]
```

Prints a ready-to-install crontab line (running
`scripts/run_agent_reply_requests.php --quiet --limit=10` once a minute) plus
pre/post-install checks to run and the log file to tail. This is a reference
printer, not the worker itself — see "Run the queued agent-reply worker"
below for that.

- `--log=...` — log file path to bake into the printed crontab line

## Run the queued agent-reply worker

```
./v3 agent-reply cron run [--limit=10] [--dry-run] [--quiet] [--post-id=<id>]
```

Runs the queued agent-reply worker directly (what the cron line above
invokes).

- `--limit=...` — maximum number of queued requests to process
- `--dry-run` — report the queued request count without generating replies
- `--quiet` — suppress progress output
- `--post-id=...` — restrict processing to one post

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

Sends one live structured prompt to the configured LLM provider to validate
the API key and model/service reachability.

- `--timeout=...` — seconds to wait for the provider response

## Run the local agent-reply test suite

```
./v3 agent-reply test-local
```

Runs the local (non-live) agent-reply test suite: `AgentReplyGenerationTest`,
`AgentReplyCommandTest`, and targeted `LocalAppSmokeTest` /
`WriteApiSmokeTest` cases covering the cron reference command, status
command, and queued-request processing.

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
