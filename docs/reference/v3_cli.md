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
./v3 railspike sync [--repository-root=/path/repository] [--remote-url=https://github.com/rlspk/railspike.git] [--ref=v0.4.0]
./v3 fdp sync ... # deprecated alias; updates Railspike, never FDP
```

Updates the vendored `docs/railspike/` subtree to an explicit release tag
(`--ref`, default the pinned `v0.4.0`), creating Git update commits. Branch
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

## Testing account approval

Create an ignored `.test-self-approval` file in the project root once per testing
checkout (`touch .test-self-approval`). Start the server normally, set up or
restore your browser account, and open **Account Key** (`/account/key/`). Click
**Approve current account** to apply the same root approval seed as
`./v3 approve <current_identity_id>`, with immediate member access.

The shortcut requires an authenticated account and a direct loopback connection
using `localhost`, `127.0.0.1`, or `[::1]`; public hostnames and forwarded proxy
requests are ineligible. The file contents do not matter. Remove the file
(`rm .test-self-approval`) to disable further submissions immediately, including
from pages already open. Existing approvals remain valid. Each new checkout
needs its own marker; no extra startup option or restart is required.

If approval reports incomplete refresh, follow the displayed rebuild command,
then retry **Approve current account**. A persisted approval is retained during
repair. The marker file is not created by installation or tracked in Git.

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

## Synchronize code checkouts

Use this from an IntelliJ terminal in any checkout containing the command:

```bash
./v3 checkouts add /home/wsl/v3
./v3 checkouts add /home/wsl/agent/v3-codex
./v3 checkouts list
./v3 checkouts
./v3 checkouts preview
./v3 checkouts apply <preview-id> --sessions-paused
```

`./v3 checkouts` (or `overview`) immediately displays cached observations in
aligned CHECKOUT / BRANCH / ACTION / HINT columns. The default does not inspect
Git in the foreground or wait on a running refresh/apply lock. The footer shows
observation age and refresh state. Branch/action labels describe the last
observation; they do not establish the checkout's live state or permission to
apply. First use, changed registration or invalid cache shows `unchecked` rows
while refresh is scheduled. No apply ID or command comes from cached output.

Background refresh is scheduled when no matching cache exists or its latest
attempt is at least 30 seconds old. Repeated calls coalesce into one worker, with
up to three fetches across independent Git stores. Fetch children never write
history evidence; the existing manager lock protects coordinator classification
and publication. Observations publish atomically only for the same registry and
selection. Fresh `preview` also warms the matching observation cache; an attempted
apply invalidates affected cached rows. Test/server exclusions still apply.

States include `queued`, `running`, `fresh`, `busy`, `deferred` and `failed`.
A manager/apply lock can defer a worker; cached output stays available. A queued
worker that never starts becomes a visible failure after 10 seconds; failed or
deferred launch attempts use a short retry cooldown. To fetch and diagnose now:

```bash
./v3 checkouts refresh
./v3 checkouts --no-refresh --json
```

`refresh` waits for fresh observations but does not create an apply ID. On an
offline/failed observation, retain the previous good row and its original timestamp
with `last_error`/`error_at`; a stale-error hint and failure state distinguish it
from a successful new observation. A cold failure shows `unavailable`. Correct
access/state permissions and retry `refresh`; when another command owns the manager
lock, retry once it finishes. `--no-refresh` deliberately reads cache without
scheduling a worker. Neither this command nor background refresh moves branches.

Detached background refresh needs `nohup` and CLI PHP's POSIX/pcntl support.
If unavailable, cached output reports failure and `refresh` remains the synchronous
recovery route. Worker lifetime is bounded to 120 seconds, each Git child to
30 seconds; handled cancellation terminates its isolated process group. An
uncatchable kill may still require process/Git inspection. Worker status is a
bounded private file, output is detached from the terminal, and only a small
bounded set of observation-cache generations is retained. Do not clear history
evidence to force freshness.

`preview` deliberately waits for fresh source inspection and creates a signed
update plan, so it can still take several seconds. Then pause coding/server
sessions before `apply`. Cached overview's subsecond target is measured locally;
network-complete preview has no subsecond promise. `list` remains registry-only.

Rows use unique short path suffixes; full paths/reasons remain in JSON. `COLUMNS`
controls width (80-column fallback, bounded 20–240); narrow terminals omit HINT
and abbreviate values with an explicit full-details hint. Control characters are
sanitized in human output. For abbreviated apply recovery, the printed result-file
path preserves the complete resolve/abort instructions without replaying an apply.

Register only directories you want to update. `add` validates the existing clone
and records its absolute root, Git storage identity, expected remote source,
remote name (`--remote=origin`), target (`--target=main`) and role. The default role
is `development`. Test/server roles (`--role=test` or `--role=server`) appear as
excluded in batch previews; select one explicitly with
`./v3 checkouts preview --path=/absolute/registered/path`. Shared-storage linked
worktrees and symlink aliases cannot be registered as independent clones.
Nothing scans your home directory or enrolls existing copies automatically.

All projects use one private registry at `~/.config/v3/checkouts.json` and private
history/preview/results at `~/.local/state/v3/checkouts`; `XDG_CONFIG_HOME` and
`XDG_STATE_HOME` override those base directories. `--registry=/path/file.json`
and `--state-dir=/private/path` allow isolation. Pass the same overrides to each
command; the preview's suggested apply command includes them. These files contain
local paths; keep them local. Do not edit saved previews. `remove <registered-path>`
forgets registration and archives its enrollment evidence without deleting files.
For a moved/missing directory, remove its old absolute registered path and add the
new location; generate a new preview after any registry change.

Preview fetches **only the registered source branch**, updates its remote-tracking
ref, and saves the exact fetched revision plus each checkout's state under an
opaque preview ID. It changes no checked-out files or branches. Results distinguish
`fast-forward`, `merge`, `current`, `ahead`, `dirty`, `busy`, `diverged`,
`migration-required`, `unavailable`, `stale` and `excluded`. A missing source or
credential/access failure does not silently select another remote. Correct access
or registration and retry preview. Raw remote/hook errors are suppressed because
they may include credentials; inspect Git locally when diagnosing failures.

To fetch and apply all eligible development checkouts without copying a preview ID,
pause their coding/server sessions first, then run:

```bash
./v3 checkouts apply --sessions-paused
```

Without an ID, apply creates a **fresh** preview under the same command lock and
immediately applies eligible rows. It does not use cached observations or a prior
preview. Dirty/busy/excluded/migration-required folders remain skipped; results can
be partial. To inspect the changes first, keep using `preview` followed by
`apply <preview-id> --sessions-paused`.

Before applying, coordinate agents and servers in the selected folders, then pass
`--sessions-paused`. This is an operator assertion; clean Git status is not proof
that an agent is idle. Tool locks serialize commands using the **same state
directory**, not external Git/editor processes. Apply revalidates registry, source,
branch, HEAD, target and dirty/operation state; changed inputs invalidate that
folder's update. It applies the previewed source commit without fetching again:
a later remote update requires a fresh preview. Main must fast-forward; eligible
feature branches may fast-forward or receive an ordinary merge preserving local
commits. Ignored local files are protected against overwrite; an ignored-file
collision fails for manual recovery. Submodule working trees are not recursively
updated. A locally ahead branch stays untouched. Existing dirty/untracked work and
Git operations are skipped; the tool never auto-stashes, switches branches,
rebases, resets, pushes, clones, deletes or changes IDE trust.

History eligibility is deliberately conservative. Enrollment records a baseline
only when the checked-out branch and fetched target are directly comparable by
ancestry. A first-run divergent branch requires manual reconciliation, even when
it is legitimate work. Subsequent known feature divergence can merge while source
history remains forward and branch continuity is proved. Source rewinds/replacements
block, including an externally fetched rewrite where persisted baseline evidence
shows the break. When no prior evidence exists, an already-fetched divergent branch
also blocks. Common ancestry alone never authorizes a merge. This cannot infer all
past force-pushes from Git alone; uncertain cases need review. Follow the
[history migration runbook](../plans/markdown_dependency_removal/history_rewrite_clone_sync.md)
for separately authorized reconciliation. After verified migration, explicitly
remove/re-add registration to archive the sticky block and establish new evidence;
this does not migrate any Git history itself.

Apply records each attempt before changing a branch. On conflict, it leaves Git's
merge state and prints commands to resolve/continue or abort in that directory;
independent folders may still update. Preserve work, resolve or abort the merge,
then run a fresh preview. A failed hook, missing identity or timed-out operation
requires inspecting Git status, configuration, hooks and any remaining processes
or locks before retrying. Git subprocesses have a 30-second limit; timeout does not
prove child hooks have stopped or undo changes. Interrupted/attempted preview IDs
cannot be replayed. Inspect the private `<id>.result.json` and Git status, then
create a new preview. No automatic rollback or whole-batch atomicity is promised.

`--json` keeps full paths/reasons, per-row observation timestamps and refresh
metadata; human output abbreviates for terminal width.
Exit **0** means completion without attention, **1** means command/configuration
failure, and **2** means partial results or attention required; inspect every row.
These results cover selected **checked-out branches**, not every local branch,
stash or detached worktree, and never establish historical erasure. Preview rows
not applied retain their observed disposition; verify current conditions with a
fresh preview.

Run real-Git acceptance tests with `php tests/run.php CheckoutsCommandTest`
(or `python3 tests/test_checkouts.py` directly); they use
isolated temporary registries and clones and do not alter registered live copies.
For the eight-checkout WSL timing/terminal replay, run
`python3 tests/checkouts_benchmark.py --output-dir=/tmp/checkout-benchmark`.
It retains every cold/warm/slow/offline/busy sample and reports min/median/p95/max;
the recorded target applies to this host, not arbitrary OS scheduling delays.

## Inspect operator status

```
./v3 status [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--queue-database-path=/private/path/tasks.sqlite3] [--url=http://127.0.0.1:8001]
```

Read-only, concise status for the repository, read model, shared execution
lock, and background queue. It reports a read-model rebuild task as `queued`,
`running`, `failed`, or `absent`, then prints the relevant next action.

- `--repository-root=...` — canonical repository to compare with read-model metadata
- `--database-path=...` — read-model SQLite database to inspect
- `--queue-database-path=...` — task-queue SQLite database to inspect
- `--url=...` — frontend URL override for this invocation

Frontend selection uses `--url`, then `FORUM_STATUS_URL` from the existing
private configuration (environment wins), then a live registered development
server for this checkout/instance. Otherwise it checks only local ports 8000
and 80 and requires a unique instance match. The selected URL and source appear
in the Frontend row. Newly started `./v3 start 8001` servers register automatically;
restart an older development server once to enable custom-port discovery.
Linux process identity guards development registrations; other platforms can
use a saved target. Status never edits these registrations.

For production virtual hosts, HTTPS, or an unresolved/ambiguous target, add
`'FORUM_STATUS_URL' => 'https://forum.example/'` to the existing
`../forum-private/secrets.php` (or `FORUM_SECRETS_PATH` file), or set the same
environment variable. Use the URL whose Host/TLS name routes to this instance.
Rerun plain status to verify; `--url` temporarily overrides the saved value.
Malformed targets require correction; credentials and fragments are rejected.
A saved/selected outage stays FAIL without trying another server. Unresolved
or ambiguous discovery is WARN and prints the configuration action.

Discovery and HTTP probing share a three-second network budget. The read-only
`/__v3/status-identity` response distinguishes the checkout, repository and
read-model path without returning paths, cookies or cached data; it bypasses
repository initialization. It is diagnostic identity, not authentication. The
web Codebase State surface continues to omit frontend probing.

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

Opens a `whiptail` dashboard with repository context, health and queue indicators,
observation time, refresh and direct details. Collection is bounded to five seconds;
a failed refresh marks the previous observation stale. `./v3 status --summary`
prints the same compact indicators without opening the TUI.

Search diagnostics by name, description or command. The fixed catalog contains
`./v3 status`, `./v3 task-queue status` and `./v3 fast-score status`.
Output appears while the command runs. Ctrl-C interrupts that diagnostic;
Enter returns and `r` retries. Search and selection survive return. This does
not cancel separately running background jobs or launch repairs.

The LLM editor starts with resolved current values and sources. Environment
fields are locked individually; other fields remain editable. Presets are an
explicit choice. Blank API-key input keeps the current key. Review shows only
actual changes with secrets redacted; validation and save failures retain the
draft for correction. Concurrent changes require a fresh review, preserving
non-secret unlocked draft fields and asking for any replacement key again.
Saved values appear in the editor; `./v3 private-config view` verifies them.
Manage site feature flags through `/tools/feature-flags/`.

The UI supports keyboard navigation at 50 columns by 18 rows or larger. Below
that size it offers resize/retry or return; long reviews use a scrolling view.
`whiptail` and interactive stdin, stdout and stderr are required. Missing
requirements provide these CLI fallbacks:

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
See [coverage, exclusions, progress and recovery](retraction_sweep.md). Previous removal/retraction receipt formats are unsupported; no migration is available.

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
./v3 import-instance <name|hostname|url> [--sources=/private/instances.json] [--dry-run] [--verbose] [--repository-root=/path/repository] [--database-path=/path/index.sqlite3] [--static-html-root=/path/static_html] [--time-limit=300]
./v3 import-instance --resume [--repository-root=/path/repository] [--database-path=/path/index.sqlite3] [--static-html-root=/path/static_html] [--time-limit=300]
```

Downloads the source's public repository archive, merges supported public forum
content while preserving destination settings/approval authority, refreshes the
read model and public offline data, and withdraws stale static HTML. Import and
`--resume` never render pages or fingerprint/copy presentation assets. PHP serves
imported content; run `./v3 build-static` separately when static output is needed.
Hostnames use HTTPS; short names require a JSON object mapping names
to URLs in `--sources`. URLs may include an instance base path. Conflicts retain
local records. Legacy creation dates are recovered from isolated source history
and retained as hash-bound metadata without changing signed post bytes. See the
[import runbook](../runbooks/instance_content_import.md) for limits and recovery.

- `--dry-run` — download and validate the archive, then report proposed imports,
  duplicates, conflicts, and exclusions without changing destination records,
  commits, the read model, or published views. Temporary downloads and lock files
  may be created. Cannot be combined with `--resume`.
- `--time-limit=SECONDS` — positive finite whole-invocation budget (default 300),
  including lock waits/publication, with up to five seconds process cleanup.
  Linux/WSL PCNTL/POSIX supervision is required; file limits remain unchanged.
- `--verbose` — list every rejected/excluded file and dependency root cause.
  The default output groups review causes and summarizes intentional exclusions.

Preview an import:

```bash
./v3 import-instance https://forum.example --dry-run
```

Remove `--dry-run` to perform the import.

Source HTTP failures report bounded, sanitized plain-text error detail when
available. Repair the source download and retry; `--resume` advice appears only
when the selected destination has an interrupted-import journal. Supported legacy
posts do not need their original bytes rewritten for source backups.

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

### Source-post deletion and label repair

Deleting a post also removes its proven generated thread-label records and their
signatures, retains content-free root/parent placeholders for surviving replies,
and invalidates generated presentation. Independent label actions remain active.
Source-owned labels also stop applying after signed browser retraction. When a
prebuilt static release is required, run `./v3 build-static` with the deployment's
normal repository/database/artifact settings after deletion.

Source-post operations accept `--inspect`, `--repair-labels`, repeatable
`--resolve-label=<label-id>`, and `--json`. Inspection is read-only; JSON reports
acceptance separately from `label_cleanup_status` and unresolved legacy records.
Repair does not delete a live source. An already deleted source is represented
by retained structural removal evidence. Example:

```bash
./v3 delete-record <source-post-id> [repository_root] [database_path] --inspect --json
./v3 delete-record <source-post-id> [repository_root] [database_path] --repair-labels --resolve-label=<label-record-id> --json
```

A legacy candidate is **not proof of ownership**. Review the source text, target
thread and trusted history before explicitly resolving a label. Timestamp/author
matches and a shared bulk-import commit alone do not establish ownership. Repair
can use an original source recovered from Git history; if history or necessary
legacy timestamp evidence is unavailable, restore it before repairing.

Repeat the same command after an interrupted commit or failed refresh. The private
Git-directory journal resumes accepted work, and already accepted deletions do
not need another acceptance commit. Changed source/label bytes require inspection
rather than blind retry. Preserve `records/post-removals/` and trusted Git history
in backups: copied receipt text alone does not establish removal authority. Imports reject
foreign removal claims and do not restore locally removed sources or labels.

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

## Publish documentation independently

```sh
./v3 docs publish [--json] [--repository-root=/path/records] [--static-html-root=/path/static]
```

Publishes the complete eligible docs library, browse/search pages, downloads and
assets atomically under `<static-root>-docs/releases/`, selected by its own
`current` pointer. Use the site's normal `FORUM_SITE_ID` profile/environment.
Options default to `FORUM_REPOSITORY_ROOT` (or `state/local_repository`) and
`FORUM_STATIC_HTML_ROOT` (or the active profile's default static root).
Readable output reports progress; `--json` returns status, docs root, release,
version and document count, or an error with a next action and nonzero exit.
`./v3 docs publish --help` describes setup and retry.

Run separately for initial static docs setup or after docs/application/profile/
asset changes. Failure retains the prior docs library; correct the reported cause
and retry with the same destination/profile. Legacy combined-release docs remain
available until successful migration. This command does not replace forum HTML,
the read model or forum offline snapshot. `/api/docs-version` and docs reload
notifications are independent of forum `/api/version`.

Imports/resumes, full forum builds and shared refreshes do no docs rendering or
bulk inventory/validation/copying. Missing/broken docs cannot block those commands.
Without a docs publication, PHP readers remain available; static-only hosting
needs this separate publication and its docs/asset routing.

## Build static HTML artifacts

```
./v3 build-static [repository_root] [database_path] [artifact_root]
```

Builds and atomically activates a complete forum static HTML release. Docs use
`./v3 docs publish` separately. It creates the
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
./v3 private-config [view|edit|update-llm|refresh-template|--view|--edit|--force|--api-key-stdin] [--path=/private/path/secrets.php]
```

Creates or updates the private PHP config consumed by
`ForumRewrite\Support\PrivateConfig` (LLM provider, API key, and related
settings for agent-reply features). `LLM_PROVIDER` is required (no default);
supported values are `openai`, `openrouter`, `anthropic`, `stub`, and
OpenAI-compatible gateways. Legacy `DEDALUS_*` settings are still read as
fallbacks, but new writes use `LLM_*` names.

- `view` / `--view` — print a redacted summary and update reminders without writing the file
- `edit` / `--edit` — open an existing config in `$VISUAL`, `$EDITOR`, or `vi`; it does not print secrets or create a missing file
- `update-llm` — accept the existing machine request on stdin, with a `values` object; blank API key means unchanged. The TUI also sends an internal `expected_revision` token so stale reviews fail without saving. Secrets never belong in command arguments.
- `refresh-template` — rewrite the file with current comments/examples and every schema template setting, including `FORUM_STATUS_URL`, while preserving existing values
- `--force` — overwrite without the usual confirmation/skip behavior
- `--api-key-stdin` — read the API key from stdin instead of an argument, so it never lands in shell history: `printf '%s\n' "$LLM_API_KEY" | ./v3 private-config --api-key-stdin`
- `--path=...` — write to a specific file instead of the default `../forum-private/secrets.php` (relative to this checkout)

Private-config writers and `edit` coordinate through a private `.lock` sidecar;
busy saves fail after three seconds and can be retried. Keep that lock file in
place. Saves preserve unrelated values and use private temporary files with
atomic replacement. An external editor that bypasses this lock can still race
the final check; avoid editing the same file concurrently outside these commands.

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

`./v3 images verify|restore <images.zip> <repository_root> <database_path>
[--image-root=/private/images]` checks a revision-matched image/repository pair.
ZIP and legacy TAR image archives are supported.
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

All fresh agent replies share `prompts/agent_response_personality.txt`, including
ordinary, selected-mode, verified scripture, and expansion replies. It asks for
warm, direct engagement and eager discussion of Bible or Jesus topics while
preserving each task's format and quotation rules. Deploy this prompt with the
application code and restart long-running workers after changing it.

The generation prompt includes application-resolved approval and admin status
for the original human author and a distinct requester. Admin means the existing
root-approved operator role; unknown identity/profile information stays unknown.
These facts provide context only: they do not authorize actions or change access
rules. Expansion uses the original human author's status, not the initial
reply-agent's. Fresh retries resolve current status; completed drafts and
published replies are not regenerated merely because status or prompts changed.

Fresh legacy/automatic replies retain analysis eligibility checks but now use
the common reply writer, adding one provider call instead of publishing the
analysis suggestion. A writer failure uses the existing status and targeted
retry commands above. Deploy/rollback code and prompts together; rolling back
does not remove already published replies. No schema migration is needed.

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

## High-traffic mode

`./v3 high-traffic enable|disable|status|restore|purge|refresh [/route/]`
switches and manages instance-local Apache HTML caching. Run
`./v3 high-traffic run /path/to/access.log` every minute for popularity-driven
atomic refresh. See [host setup, limits, verification and recovery](../runbooks/high_traffic_mode.md).
Automatic traffic/load activation is deferred.

CLI output is readable by default; use `--json` for automation.
`./v3 high-traffic cron /path/to/access.log` prints the installation instructions
and crontab line; `run` executes one refresh. Existing schedules using `cron`
as the worker must change to `run`. Enable/status also report the cron line;
they do not install or verify a schedule. Use the web app's environment and user.

### Shared removal receipt compatibility

Signed retraction and operator deletion/label repair now use the shared
[removal acceptance format](../specs/removal_acceptance_record_v1.md). Local
receipts live at `records/post-removals/<id>.txt`. Previous JSON and retraction
text receipts and old pending journals are unsupported, with no migration or
replay. No automatic deletion of old files is performed. New operations retain
commit/refresh retry recovery: fix the reported failure and repeat the command.
Unsupported journal versions stop before mutation; do not reinterpret them as
new receipts. Rollback requires a matching verified repository/application backup;
a binary-only downgrade cannot read the new format.
