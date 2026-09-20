# `v3` CLI Reference

`./v3` is a thin dispatcher over the PHP scripts in `scripts/`. Every subcommand
below can also be invoked directly as `php scripts/<script>.php ...`; the `./v3`
form is the shorthand used elsewhere in this repo's docs.

Run `./v3` with no arguments to print the same command list from the script
itself (useful if this document drifts from `v3`).

Most data-touching commands accept optional positional `repository_root` and
`database_path` arguments. When omitted they fall back to, in order:

1. the relevant `FORUM_*` environment variable (`FORUM_REPOSITORY_ROOT`,
   `FORUM_DATABASE_PATH`, `FORUM_PUBLIC_ARTIFACT_ROOT`)
2. the default local repository/database bootstrapped under `state/`

See [Local Run](../../README.md#local-run) in the README for the defaults
and the bootstrap flow.

## `./v3 start [--listen-all|host:port]`

Starts the PHP built-in dev server on `127.0.0.1:8000` by default. Pass
`--listen-all` to bind `0.0.0.0:8000`, or an explicit `host:port` / bare port
number.

## `./v3 test`

Runs the custom test runner (`tests/run.php`) covering parser, rebuild, and
app smoke tests. Accepts the runner's own arguments, e.g. a specific test
class name.

## `./v3 rebuild [repository_root] [database_path]`

Rebuilds the SQLite read model from canonical records in the repository root.
Prints source record counts (posts, identities, approval seeds) and resulting
read-model table counts (posts, threads, profiles, activity).

## `./v3 import-repository <archive.tar.gz> [repository_root] [database_path] [artifact_root] [--dry-run] [--no-commit]`

Imports a `.tar.gz` repository archive into the target repository root,
rebuilds the read model, and rebuilds static artifacts (when an artifact root
is resolved). `--dry-run` validates without writing; `--no-commit` skips the
git commit step.

## `./v3 thread-attributes <thread_id_or_record> [repository_root] [database_path]`

Diagnostic/read-only. Accepts a thread ID, post ID, canonical record path, or
an unambiguous canonical record filename stem. Prints the target item,
canonical root post attributes, derived read-model thread attributes,
effective labels, and each thread-label record that contributes a label.

## `./v3 delete-record <record_path_or_id> [repository_root] [database_path] [artifact_root]`

Deletes a canonical file under `records/` with `git rm`, commits the removal,
rebuilds the read model, and rebuilds static artifacts when an artifact root
is resolved (via argument or `FORUM_PUBLIC_ARTIFACT_ROOT`).

`record_path_or_id` accepts either a relative path
(`records/thread-labels/thread-label-20260530000001-zenrules.txt`) or an
unambiguous filename stem (`thread-label-20260530000001-zenrules`).

## `./v3 unicode-risk-backfill [repository_root] [database_path] [--with-llm]`

Backfills Unicode-risk analysis for existing posts. By default uses
deterministic detection only; `--with-llm` also runs the configured LLM
provider. `--deterministic-only` explicitly disables LLM use if set after
`--with-llm`.

## `./v3 build-static [repository_root] [database_path] [artifact_root]`

Builds Apache-friendly static HTML artifacts. Defaults the artifact root to
`public/` when not given via argument or `FORUM_PUBLIC_ARTIFACT_ROOT`.

## `./v3 archive-thread <thread_id> [repository_root] [database_path] [artifact_root] [archive_path]`

Archives a full thread (root post, replies, supporting public keys) into a
`.tar.gz` archive, removes the component records from the live repository,
and rebuilds affected artifacts. `archive_path` defaults to a generated path
under the project's archive directory when omitted.

## `./v3 private-config [view|refresh-template|--view|--force|--api-key-stdin] [--path=/private/path/secrets.php]`

Creates or updates the private PHP config consumed by
`ForumRewrite\Support\PrivateConfig` (LLM provider, API key, and related
settings for Dedalus/agent-reply features).

- `view` / `--view` — print a redacted summary and update reminders without
  writing the file
- `refresh-template` — rewrite the file with current comments/examples while
  preserving existing values
- `--force` — overwrite without the usual confirmation/skip behavior
- `--api-key-stdin` — read the API key from stdin instead of an argument, so
  it never lands in shell history:
  `printf '%s\n' "$LLM_API_KEY" | ./v3 private-config --api-key-stdin`
- `--path=...` — write to a specific file instead of the default
  `../forum-private/secrets.php` (relative to this checkout)

`LLM_PROVIDER` supports `dedalus`, `openai`, `openrouter`, `anthropic`,
`stub`, and OpenAI-compatible gateways. Legacy `DEDALUS_*` settings are still
read as fallbacks, but new writes use `LLM_*` names.

## `./v3 agent-reply cron [--log=/var/log/forum-agent-replies.log]`

Prints a ready-to-install crontab line (running
`scripts/run_agent_reply_requests.php --quiet --limit=10` once a minute) plus
pre/post-install checks to run and the log file to tail. This is a reference
printer, not the worker itself — see `agent-reply cron run` below for that.

## `./v3 agent-reply cron run [--limit=10] [--dry-run] [--quiet] [--post-id=<id>]`

Runs the queued agent-reply worker directly (what the cron line above
invokes). `--dry-run` reports the queued request count without generating
replies. `--post-id` restricts processing to one post. `--quiet` suppresses
progress output.

## `./v3 agent-reply status [post_id] [--limit=25] [--database-path=/path/post_index.sqlite3]`

Read-only diagnostics for skipped or failed agent-reply generation rows.
Without a `post_id`, shows the most recent skipped rows (limit defaults to
25); with a `post_id`, shows that post's rows (limit defaults to 10, capped
at 100).

## `./v3 agent-reply test [--timeout=30]`

Sends one live structured prompt to the configured LLM provider to validate
the API key and model/service reachability.

## `./v3 agent-reply test-local`

Runs the local (non-live) agent-reply test suite: `AgentReplyGenerationTest`,
`AgentReplyCommandTest`, and targeted `LocalAppSmokeTest` /
`WriteApiSmokeTest` cases covering the cron reference command, status
command, and queued-request processing.

## `./v3 codex-handoff run [--limit=1] [--dry-run] [--database-path=/path/post_index.sqlite3] [--codex-bin=/path/codex]`

Runs approved Codex handoff requests through the local `codex` binary
(override its path with `--codex-bin` or `FORUM_CODEX_EXECUTABLE`).
`--dry-run` reports the approved-handoff count without running anything.

## `./v3 codex-handoff test-local`

Runs the local Codex handoff test suite: `CodexHandoffDraftServiceTest`,
`CodexHandoffStoreTest`, `CodexHandoffRunnerTest`, and the `WriteApiSmokeTest`
cases covering the handoff API, approval/rejection flow, development-tag
requirements, activity lifecycle, and UI bindings.

## `./v3 approve <identity_id> [seed_reason] [repository_root] [database_path]`

Shorthand alias for `./v3 approval seed`.

## `./v3 approval seed <identity_id> [seed_reason] [repository_root] [database_path]`

Seeds an initial approved identity (e.g. the first admin) so
`FORUM_APPROVED_MEMBERS_ONLY` can be enabled without a chicken-and-egg
approval problem. `seed_reason` defaults to `"initial approved user"`.

## `./v3 approval approve <approver_identity_id> <target_identity_id> [repository_root] [database_path] [artifact_root]`

Records an approval of `target_identity_id` by an already-approved
`approver_identity_id`, and rebuilds affected artifacts when an artifact root
is resolved.
