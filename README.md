# PHP Forum Rewrite

Minimal local test slice for the rewrite spec.

## Documentation

An index of everything under `docs/` plus the project-level notes at the
repo root.

### Architecture (`docs/architecture/`)

- [Public Architecture and Trust Model](docs/architecture/public_architecture_and_trust.md) — public records, browser identities, and operator trust
- [Private Messaging Architecture and Features](docs/architecture/private_messaging.md) — consolidated encryption, storage, conversation features, unread semantics, recovery, and operational limits

### Runbooks (`docs/runbooks/`)

Operational how-tos for running and maintaining a deployment:

- [Production Deploy Runbook](docs/runbooks/production_deploy.md) — first-time setup, directory layout, environment variables
- [Operator Recovery Runbook](docs/runbooks/operator_recovery.md) — read-model status, stale-marker recovery, lock contention, write failures
- [Offline Reading Runbook](docs/runbooks/offline_reading.md) — public snapshot limits, refresh, and browser-cache recovery
- [Theme Development Guide](docs/runbooks/theme_development_guide.md) — how to add or modify a theme
- [Contributor Guide](CONTRIBUTING.md) — project-wide development requirements
- [Instance Content Import](docs/runbooks/instance_content_import.md) — on-demand remote content import, names/URLs, preview, and recovery
- [QDB Archive Import Runbook](docs/runbooks/qdb_archive_import.md) — running the one-shot qdb.us quote backfill against a production vhost

### Specs (`docs/specs/`)

Durable canonical-record formats and infrastructure contracts — the layer
that stays accurate even as implementation details move around:

- [Master Spec](docs/specs/php_forum_rewrite_spec_v1.md) — the original PHP-rewrite product/architecture spec (pre-implementation baseline; some sections are annotated as superseded)
- [Canonical Post Record](docs/specs/canonical_post_record_v1.md)
- [Identity Bootstrap Record](docs/specs/identity_bootstrap_record_v1.md)
- [Public Key Storage](docs/specs/public_key_storage_v1.md)
- [Profile Read Contract](docs/specs/profile_read_contract_v1.md)
- [Thread Label Record](docs/specs/thread_label_record_v1.md)
- [Post Reaction Record](docs/specs/post_reaction_record_v1.md)
- [Site Feature Flags Record](docs/specs/site_feature_flags_record_v1.md)
- [Feature Flag Change Record](docs/specs/feature_flag_change_record_v1.md)
- [User Approval Seed Record](docs/specs/user_approval_seed_record_v1.md)
- [Agent Reply One-Step Analyze/Publish Contract](docs/specs/agent_reply_one_step_analyze_publish_contract_v1.md)
- [Asset Fingerprinting and CSS Splitting](docs/specs/asset_fingerprinting_and_css_split_v1.md)
- [Read-Model SQLite Schema Reference](docs/specs/read_model_schema_v1.md)
- [Theme-Menu Representative Options Spec](docs/specs/theme_menu_representative_options_spec_v1.md)

### Reference

- [Authentication Modes and Access](docs/reference/authentication_modes.md) — public and members-only access, browser identities, approval, and session recovery
- [`v3` CLI Reference](docs/reference/v3_cli.md) — every `./v3` subcommand, including ones not covered below (`rebuild`, `build-static`, `import-repository`, `thread-attributes`, `delete-record`, `unicode-risk-backfill`, `archive-thread`, `agent-reply status`/`test-local`, `codex-handoff run`/`test-local`, `task-queue`), plus the 3 standalone operator scripts not wired into `./v3`
- [Fastmod](docs/reference/fast_post_scoring.md) — configuration and approved-operator workflow for focused 0–1 LLM moderation scores

### Examples (`docs/examples/`)

- [Extension and Improvement Cookbook](docs/examples/extension_improvement_cookbook.md) — patterns and examples for safely extending the site, including agent-assisted workflows
- [Apache Vhost Example](docs/examples/apache_vhost.conf)
- [Production Env Example](docs/examples/env.production.example)
- [Private Config Example](docs/examples/secrets.php.example) — template for the LLM private config `./v3 private-config` writes

### Production

- [Production Deployment Checklist](docs/plans/php_production_deployment_checklist_v1.md) — tracks remaining work between current state and a production launch; periodically re-reviewed rather than treated as done

### Development workflow (Railspike)

- [Railspike](docs/railspike/RAILSPIKE.md) — the workflow for new work, vendored as a Git subtree.
- [Project workflow and updates](docs/reference/railspike_workflow.md) — subtree operations, recovery, and the transition from FDP.

### Planning Docs (`docs/plans/`)

The [planning index](docs/plans/README.md) lists feature folders for in-flight
and historical planning documents. New Railspike cycles move into feature folders after Step 3 approval and
branching, before their planning commit. Existing FDP plans retain their original
organization rules.
Two cross-cutting plans are worth knowing about:

- [Codebase Cleanup Audit Plan](docs/plans/codebase_cleanup_audit_plan_v1.md) — the plan behind the `Application.php` decomposition into per-domain namespaces
- [Documentation Audit Findings & Checklist](docs/plans/docs_audit_findings_and_checklist_v1.md) — the review that produced this Documentation section

Fully completed feature cycles are moved into
[`docs/plans/archive/`](docs/plans/archive/) once done.

### Research (`docs/research/`)

An academic paper (plus slides) on Byzantine fault-tolerant governance for
internet message boards, unrelated to this codebase's implementation —
[`docs/research/`](docs/research/) for the full set.

### Project Notes (repo root)

- [BLESSING.md](BLESSING.md)
- [sfenc.md](sfenc.md) — Starfield Encoding Specification (SFENC), a self-contained text-encoding spec
- [zenmemes_rules.txt](zenmemes_rules.txt) — the site's tone-setting posting rules (not currently rendered anywhere in-app)

## Local Run

### PHP setup

Local startup requires PHP CLI **8.1+**, Git, PDO/PDO SQLite, mbstring, and PHP
process execution for the server and local Git operations. The selected `php`
executable must be on your `PATH`; extensions must be enabled for that same CLI.
If it is missing, install PHP CLI using your system's package manager or PHP
installation instructions. If PHP is already installed, add the directory
containing its executable to `PATH` in the terminal where you run v3.

Check availability with `php --version` and `./v3 check-dependencies`, then retry
`./v3 start` with your original options. Both commands explain missing or disabled
capabilities and manual recovery. This guide is readable directly from the
checkout without running PHP. Ordinary checks and redirected startup are read-only.
Terminal startup can offer [reviewed recovery](#install-missing-local-server-dependencies)
on Ubuntu 22.04/distribution PHP 8.1 and Ubuntu 24.04/distribution PHP 8.3.

For missing distribution packages on Ubuntu 22.04, the manual recipe is:

```bash
sudo apt-get update
sudo apt-get install git php8.1-cli php8.1-sqlite3 php8.1-mbstring
```

For Ubuntu 24.04, use the matching PHP 8.3 packages:

```bash
sudo apt-get update
sudo apt-get install git php8.3-cli php8.3-sqlite3 php8.3-mbstring
```

These commands change system packages and require administrator privileges;
omit `sudo` in an existing root shell. If a command fails, resolve the reported
package-manager or permission issue before retrying. Then check `php --version`
and `php -m` for `PDO`, `pdo_sqlite`, and `mbstring` in your launching terminal.
Run v3 as your usual user after setup.

If PHP is already installed, the report identifies its executable and loaded
configuration. Use `php --ini` to find CLI settings and `php -m` to check enabled
extensions; changing another PHP installation or a web-server-only configuration
will not repair this CLI. A standalone `sqlite3` executable does not replace PDO
SQLite. If PHP process functions are disabled or execution is restricted, review
the CLI's `disable_functions` and host policy, or use another supported
environment. These checks do not require remote Git credentials.

Reports identify OS/release and selected CLI. Package advice requires matching
Ubuntu distribution ownership and verified APT metadata; a custom CLI is never
replaced automatically. If an extension package is installed but unavailable,
inspect `php --ini`, `php -m`, `PHPRC` and `PHP_INI_SCAN_DIR`. For example, a disabled
mbstring link in standard Ubuntu 22.04 CLI configuration can be enabled manually:

```bash
sudo phpenmod -v 8.1 -s cli mbstring
./v3 check-dependencies
```

Use `8.3` for the matching Ubuntu 24.04 CLI. This does not repair an alternate
configuration or disabled PHP functions. Recovery never edits PATH, shell profiles
or PHP configuration, and unsupported environments retain manual setup guidance.

### Launcher and optional support

The launcher requires Bash **4.1+** and standard `dirname` and `cat` utilities.
Its `#!/usr/bin/env bash` interpreter must be available on `PATH`. If Bash is
missing, the launcher cannot print its own guidance: install/select Bash using
your platform's setup instructions, verify `bash --version`, and retry. With a
newer Bash outside `PATH`, use `/path/to/bash ./v3 check-dependencies` and
`/path/to/bash ./v3 start` with your original options. Missing `dirname` or `cat`
must likewise be repaired through your platform's standard utilities setup;
the launcher uses them before it can provide all diagnostics.

`stty`, PCNTL signal support, and `shell_exec` for terminal cosmetics are optional.
Without them, output uses plain logging and launcher-managed shutdown remains
available; stop the launcher with Ctrl+C or SIGTERM. Do not relax host execution
policy solely to enable terminal cosmetics. PHP intl is also optional: existing
Unicode behavior rejects combining marks when normalization is unavailable.
For missing Ubuntu 22.04/PHP 8.1 packages, use `sudo apt-get install php8.1-intl`;
for Ubuntu 24.04/PHP 8.3, use `sudo apt-get install php8.3-intl`. For another runtime, enable its matching intl
extension and recheck the selected CLI.

### Check local-server dependencies

```bash
./v3 check-dependencies
```

Reports required and optional local-server capabilities without starting the
server, bootstrapping a repository, rebuilding a read model, or writing application
state. Exit status is **0** when required capabilities are available, including
when optional warnings remain; **1** means setup needs repair. It also works
without PHP and never consumes interactive setup input. Use
`./v3 check-dependencies --help` for usage without runtime checks.
`./v3 dependency-check` is an alias with the same options and exit status.

The report lists **✓ available**, **✗ unavailable**, **! optional limitations**,
and **? not checked**, followed by a readiness result and separate recovery
instructions. PHP-dependent checks stay unknown when PHP cannot run. Terminal
output colors the statuses and highlights setup commands; redirected output
keeps the symbols and plain text. Set `NO_COLOR=1` to disable color.

`./v3 start` uses the same assessment before loading application code or creating
logs/state. A passing dependency report does not validate checkout/database
health, application permissions, configuration, or whether the listening port is
available; those failures remain separate operational errors. Checks cover only
the local server. Other commands and production deployment have their own
prerequisites. Verified Ubuntu 22.04/24.04 packages receive specific setup
guidance; unsupported or ambiguous setups receive inspection/manual steps.

### Install missing local-server dependencies

```bash
./v3 check-dependencies --install
# Equivalent: ./v3 dependency-check --install
```

On **Ubuntu 22.04/PHP 8.1 or Ubuntu 24.04/PHP 8.3 distribution packages**, this reviews missing Git,
PHP CLI, SQLite and mbstring packages, asks separately whether to include optional
intl, and shows APT's complete resolved transaction. Type `yes` at the final
confirmation to authorize metadata refresh and installation. Both input and output
must be terminals; there is no unattended bypass. Declining before execution or
sending EOF makes no package changes. Ordinary checks remain read-only.

When `./v3 start` encounters installable required failures and both input and
output are terminals, it offers the same review if root or sudo is available:

```text
Recover missing dependencies and continue startup? [Y/n]:
```

Enter, `Y` or `y` accepts; `N`, `n`, invalid input, EOF or pre-execution cancellation
stops without changes. Optional intl has a separate `[y/N]` choice and defaults
off. Optional-only limitations never trigger startup recovery. Sudo authenticates
**after consent**; a password is supported, and denied/cancelled authentication
stops without installation. Successful installation and fresh required readiness
resume the original startup once, retaining its port/logging options and caller
identity. Only package operations are elevated. Redirecting either input or
output disables startup recovery prompts.

Installation needs administrator privileges: use your normal account with `sudo`,
or an existing root shell. The reviewed dependencies may be upgraded and package
maintainer scripts run; existing configuration files are preserved. If refreshed
metadata changes the transaction, a second confirmation is required. Refusal at
that point leaves refreshed metadata but does not install packages. Removals,
other PHP runtime families, unfinished dpkg transactions and unverified package
sources are refused.

Automatic mapping requires verified package versions from `archive.ubuntu.com`,
`security.ubuntu.com`, or `ports.ubuntu.com` archives for the matching Jammy or Noble release. Custom PHP builds,
other mirrors/releases, PATH errors, installed-but-disabled extensions and host
restrictions require manual setup. If no candidate metadata is available, inspect
APT sources and run `sudo apt-get update` manually before retrying. Bash and
launcher utilities must already work; [external launcher setup](#launcher-and-optional-support)
also applies. The installer needs standard Ubuntu APT/dpkg, coreutils, util-linux,
and procps tools; it does not install or repair its own launcher/package tools.

After an attempt, package outcome and fresh dependency readiness are reported
separately. Exit **0** means the requested installation succeeded and required
checks pass, or no packages were selected and required checks already pass.
Declined optional-only setup can therefore return 0. Exit **1** means refusal,
cancelled installation, a package failure, or remaining required problems; even a
successful package transaction may leave configuration restrictions. Failures and
interruptions can leave partial system changes. Inspect APT output and
`/var/log/apt/history.log`; there is no automatic rollback, repair or retry. If
package processes remain active, wait for them to exit before manual recovery;
readiness is not reassessed while those processes remain active.

Explicit `--install` never starts the server or changes application state; retry
your original startup command afterward. Recovery accepted inside `start` continues
automatically after success. Neither route resumes startup after a failed package
operation, even when its partial changes made dependencies ready.

Real startup-to-read/write/shutdown tests cover Ubuntu Base **22.04.5/PHP 8.1.2**
and **24.04.3/PHP 8.3.6**, amd64 chroots. This is not separate WSL terminal/PATH or
other architecture certification. See [acceptance evidence and reproduction](docs/plans/startup_dependency_recovery/acceptance_evidence.md).

### Start locally

The default local runtime now bootstraps and uses `state/local_repository` automatically. On first run it copies the committed fixture seed into that writable git repo, so thread/reply/bootstrap writes work without setting `FORUM_REPOSITORY_ROOT`.

Rebuild the SQLite read model:

```bash
php scripts/rebuild_read_model.php
```

Build and activate an Apache-friendly static HTML release under `state/static_html/`:

```bash
php scripts/build_static_artifacts.php
```

By default this skips bulk thread/post HTML generation and serves detail pages
through PHP at runtime. Static-only hosts can opt in with
`FORUM_STATIC_DETAIL_PAGES_ENABLED=true`. Existing instance/environment overrides
still apply; run a full build after changing the flag (a shared-only refresh
retains old detail files). See the [build reference](docs/reference/v3_cli.md#build-static-html-artifacts).

Start the local PHP server:

```bash
./v3 start
```

By default this binds to `127.0.0.1:8000`. To make it reachable from other devices on allowed networks such as Tailscale, bind to all interfaces explicitly:

```bash
./v3 start --listen-all
```

You can also pass any explicit `host:port`, such as `./v3 start 0.0.0.0:8000`.

Authored post subject/body text remains ASCII-only by default. To test visible UTF-8 prose, such as Cyrillic, enable the rollout flag before starting the app:

```bash
FORUM_UNICODE_AUTHORED_TEXT=true ./v3 start
```

The flag affects only human-authored post prose. Machine fields such as post IDs, thread IDs, board tags, profile slugs, routes, and identity IDs remain ASCII-oriented.

The browser-side app version polling and reload banner are enabled by default. To disable the "A new version is available." notification before starting the app:

```bash
FORUM_APP_VERSION_NOTIFICATION=false ./v3 start
```

Registered site feature flags are visible at `/tools/feature-flags/`. A root-approved operator changes a mutable flag by signing the server-prepared action with their browser key. A successful action writes the current snapshot to `records/instance/feature-flags.txt` and commits an immutable signed record under `records/feature-flag-changes/`.

**Private messages** is enabled by default. A root-approved operator can disable it at `/tools/feature-flags/`, or a deployment can set `FORUM_PRIVATE_MESSAGES_ENABLED=false` in the PHP server environment. A valid environment value overrides the saved site choice and locks the site toggle; restart the server after changing its environment. Disable blocks messaging pages/APIs, unread updates, and history sync and hides messaging controls on fresh pages. Stored messages and progress remain intact. Requests already admitted may finish; open pages stop messaging when they receive a disabled response. Re-enable and reload to resume, retaining existing recoverable drafts and checking unconfirmed sends before sending again. See [messaging availability and recovery](docs/architecture/private_messaging.md#server-availability).

`FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED` is off by default. Enable it from that Feature Flags page (or set it to `true` in the deployment environment) to prepare a browser-local `guest` keypair for new visitors before their first signed action. The private key stays in the browser. Automatic preparation defers public-key publication by default; Account Key offers a per-browser immediate-publication preference. Later authentication or identity-readiness flows can also publish the saved key. Disabling the flag stops future automatic preparation but does not remove a keypair already saved in a browser. Preparing a keypair does not grant membership approval.

To restrict member content to authenticated approved identities, enable `FORUM_APPROVED_MEMBERS_ONLY=true` in its feature-flags record or deployment environment (default: `false`). Entry/recovery exceptions include Lobby, Account Key, documentation GETs, identity/authentication and invitation-redemption endpoints, and the session-bound own profile. Public assets and the version endpoint remain available. Other protected content, feeds, APIs, and downloads require member access; each action can impose additional permissions. Authentication publishes a saved browser identity when needed before verifying its challenge signature. The flag is independent of `FORUM_SITE_ID` and theme selection. See [Authentication Modes and Access](docs/reference/authentication_modes.md) for the access matrix, exact exceptions, and recovery paths.

Local site profiles share the instance's repository and read-model database. The site selector changes presentation but not approval-command state:

```bash
FORUM_SITE_ID=chouse ./v3 approval seed openpgp-<fingerprint>
```

This targets the same default instance state as the unprefixed command. Set `FORUM_REPOSITORY_ROOT` and `FORUM_DATABASE_PATH` explicitly only when operating on a genuinely separate instance.

Runtime precedence is:

1. `FORUM_*` environment override
2. `records/instance/feature-flags.txt`
3. code default

Use environment variables as operator overrides when a flag must be pinned outside site content. Use the Tools page for normal auditable site-level changes. Inspect history with:

```bash
git log -- records/instance/feature-flags.txt
git show <commit>:records/instance/feature-flags.txt
git log -- records/feature-flag-changes
```

Create or update the local private config for LLM post analysis:

```bash
./v3 private-config
```

View a redacted summary of the current private config and update reminders:

```bash
./v3 private-config view
```

Open the private config with `$VISUAL`, `$EDITOR`, or `vi`:

```bash
./v3 private-config edit
```

Install and run the unified background worker for queued agent replies and maintenance work:

```bash
./v3 task-queue cron
./v3 task-queue run --limit=1
```

Validate the configured agent reply LLM provider/API key with one live plain-text task prompt:

```bash
./v3 agent-reply test
```

The default local file resolves to `../forum-private/secrets.php` relative to this checkout. To update only the LLM API key without putting it in shell history:

```bash
printf '%s\n' "$LLM_API_KEY" | ./v3 private-config --api-key-stdin
```

The default post-analysis prompt is stored in `prompts/dedalus_post_analysis_system.txt`. Set `LLM_POST_ANALYSIS_PROMPT_PATH` in the private config to use a different text file; relative paths are resolved from the project root.

For Apache/shared-host deployment, `public/.htaccess` is now part of the intended runtime model:

- serve existing `/assets/*` files and `favicon.ico` directly
- route every content, API, download, and generated-artifact request through `public/index.php`
- let the front controller serve eligible queryless cookie-free static HTML on public instances and enforce the private-site gate before any content artifact is read

This keeps immutable presentation assets inexpensive while ensuring a site-level `FORUM_APPROVED_MEMBERS_ONLY` flag cannot be bypassed by an old sibling HTML artifact.

The repo-owned deployment contract is now documented in the production runbook. What remains before a real production launch is mostly host-side validation on the actual Apache target.

Open these routes:

- `http://127.0.0.1:8000/`
- `http://127.0.0.1:8000/about/`
- `http://127.0.0.1:8000/threads/root-001`
- `http://127.0.0.1:8000/posts/root-001`
- `http://127.0.0.1:8000/activity/`
- `http://127.0.0.1:8000/users/`
- `http://127.0.0.1:8000/activity/?view=all&format=rss`
- `http://127.0.0.1:8000/instance/`
- `http://127.0.0.1:8000/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954`
- `http://127.0.0.1:8000/user/guest`
- `http://127.0.0.1:8000/compose/thread`
- `http://127.0.0.1:8000/compose/reply?thread_id=root-001&parent_id=root-001`
- `http://127.0.0.1:8000/account/key/`
- `http://127.0.0.1:8000/api/`
- `http://127.0.0.1:8000/api/list_index`
- `http://127.0.0.1:8000/api/get_thread?thread_id=root-001`
- `http://127.0.0.1:8000/api/get_post?post_id=root-001`
- `http://127.0.0.1:8000/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954`
- `http://127.0.0.1:8000/llms.txt`

The default server uses [state/local_repository](state/local_repository) when `FORUM_REPOSITORY_ROOT` is unset. Override it with `FORUM_REPOSITORY_ROOT=/path/to/repo` if needed.

Compose routes now use submit-time browser identity bootstrap for brand-new users:

- no keypair is generated on page load
- on the first real submit, the browser prompts for username, generates an OpenPGP keypair, publishes the public key in the background, sets the identity-hint cookie, and then continues the original post submit
- browser OpenPGP is loaded through `/assets/openpgp_loader.js`; it prefers OpenPGP.js v6 on secure origins and uses a pinned OpenPGP.js v5 fallback on public HTTP
- compose forms include an explicit anonymous submit button that posts without `Author-Identity-ID` when the user chooses not to use browser identity or browser OpenPGP is unavailable
- existing browser-local keypairs skip regeneration
- if browser generation/bootstrap fails, the draft stays intact and `/account/key/` remains the manual fallback

If you want to initialize that writable repo explicitly ahead of time:

```bash
php scripts/init_local_repository.php
FORUM_REPOSITORY_ROOT=state/local_repository php scripts/rebuild_read_model.php
FORUM_REPOSITORY_ROOT=state/local_repository ./v3 start
```

Static HTML for anonymous queryless route hits defaults to `state/static_html`.
Each build creates a complete release and atomically selects it through
`state/static_html/current`; old `public/*.html` files are ignored. Override
the root with `FORUM_STATIC_HTML_ROOT=/path/to/static_html`.

Set the local identity-hint cookie:

```bash
curl -X POST "http://127.0.0.1:8000/api/set_identity_hint?identity_hint=openpgp-demo"
```

Write API examples:

```bash
curl -X POST "http://127.0.0.1:8000/api/create_thread?board_tags=general&subject=Hello&body=Thread%20body"
curl -X POST "http://127.0.0.1:8000/api/create_reply?thread_id=root-001&parent_id=root-001&body=Reply%20body"
curl -X POST --data-urlencode "public_key@tests/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc" "http://127.0.0.1:8000/api/link_identity"

# low-level/manual fallback:
curl -X POST --data-urlencode "public_key@tests/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc" "http://127.0.0.1:8000/api/link_identity?bootstrap_post_id=root-001"
```

Approval helper examples:

```bash
./v3 start
./v3 approval seed openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
./v3 approval approve openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954 openpgp-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
./v3 approval audit
./v3 approval audit --summary
./v3 approval audit --review-only
./v3 approval audit --repository-root=/path/repository --database-path=/path/read-model.sqlite3 --json
```

`./v3 approve` is an alias for `./v3 approval seed`. On a current read model,
both apply the seed synchronously without a full rebuild: success means the next
request sees updated approval, attribution, access, activity, and scores, including
existing approvals unlocked by the new seed. Generated pages and active static
releases are invalidated; PHP serves current state immediately. Existing offline
snapshot copies are retired and the snapshot endpoint can recreate them from the
current model. Already-open pages and downloaded offline copies are not pushed an
update.

Use the same repository/database and `FORUM_PUBLIC_ARTIFACT_ROOT` /
`FORUM_STATIC_HTML_ROOT` as the web instance (the static root defaults to the active
site profile). Git-backed instances require matching read-model HEAD and clean
canonical records; unrelated staged files are preserved. Non-Git instances use a
recorded canonical-content fingerprint. Older non-Git indexes without that evidence
need an explicit read-model repair once.

Missing, stale, incompatible, or out-of-date read models fail without automatically
rebuilding. Errors state whether the seed was written and committed. Resolve the
reported canonical-write, permissions, or cache problem, then use the instance paths
printed by the error to repair and retry:

```bash
./v3 rebuild /path/repository /path/read-model.sqlite3
./v3 approve openpgp-<fingerprint> "same seed reason" /path/repository /path/read-model.sqlite3
```

Repair is an exceptional recovery operation, not a step after ordinary approval.
Retrying a matching seed refreshes state and caches without creating another seed
or commit; a seed left uncommitted by a failed Git operation is committed on retry.
An existing seed with a different reason is rejected without changing it. Seed
writes preserve root attribution and do not create user approval replies.

The approval audit reads existing state without rebuilding or changing approvals.
It reports currently approved keys and their attributed approver, including grants
derived from invitations. Root-seeded, operator-approved, same-username, and
cross-username grants are separated. Cross-username grants on accounts with multiple
approved keys, plus unknown attribution, are review candidates. They are not proven
violations: the audit does not reconstruct which key was first or operator/flag
state at approval time, enumerate every historical approval, or reverify signatures.
Operator classification uses canonical root seeds, not display labels. Warnings
identify stale or unverifiable read-model metadata. `--review-only` filters rows;
summary counts remain totals for the whole audit. `--summary` shows totals,
warnings, and limitations without individual key rows. It also works with `--json`,
omitting the `rows` field; combining it with `--review-only` leaves totals unchanged.
Exit status is 0 for a completed
audit, including findings, or 1 for an error.

## Tests

Run the current parser, rebuild, and app smoke tests:

```bash
./v3 test
```

Standalone local-server dependency regressions:

```bash
bash tests/missing_php_startup_test.sh
bash tests/local_server_dependency_checks_test.sh
python3 tests/local_server_dependency_runtime_test.py
```

The real-runtime test uses Python 3's standard library, PHP CLI, temporary
projects/configurations, and HTTP/terminal checks. It changes no system packages
or real application state. Missing shared-extension cases are skipped when the
runtime compiled that extension statically.

The custom test runner reports tests that take at least 5 seconds by default.
Override the cutoff with `FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS`, for example
`FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS=1 ./v3 test`.
