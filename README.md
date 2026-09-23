# PHP Forum Rewrite

Minimal local test slice for the rewrite spec.

## Production Docs

Production-facing deployment and operations docs now live here:

- [Production Deploy Runbook](docs/runbooks/production_deploy.md)
- [Operator Recovery Runbook](docs/runbooks/operator_recovery.md)
- [Apache Vhost Example](docs/examples/apache_vhost.conf)
- [Production Env Example](docs/examples/env.production.example)
- [Production Deployment Checklist](docs/plans/php_production_deployment_checklist_v1.md)

See the [`v3` CLI Reference](docs/reference/v3_cli.md) for every `./v3` subcommand, including ones not covered below (`rebuild`, `build-static`, `import-repository`, `thread-attributes`, `delete-record`, `unicode-risk-backfill`, `archive-thread`, `agent-reply status`/`test-local`, `codex-handoff run`/`test-local`).

## Local Run

The default local runtime now bootstraps and uses `state/local_repository` automatically. On first run it copies the committed fixture seed into that writable git repo, so thread/reply/bootstrap writes work without setting `FORUM_REPOSITORY_ROOT`.

Rebuild the SQLite read model:

```bash
php scripts/rebuild_read_model.php
```

Build and activate an Apache-friendly static HTML release under `state/static_html/`:

```bash
php scripts/build_static_artifacts.php
```

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

Registered site feature flags are visible at `/tools/feature-flags/`. Root-approved users can change mutable site flags there; those changes are written to `records/instance/feature-flags.txt` in the content repository and committed to git.

To lock an instance to approved members, enable `FORUM_APPROVED_MEMBERS_ONLY=true` in its feature-flags record or deployment environment. A saved browser keypair is automatically published from the Lobby before authentication; unapproved visitors can access only the Lobby, Account, and their own profile. All other pages, feeds, APIs, downloads, backups, and content artifacts are blocked. The flag is independent of `FORUM_SITE_ID` and theme selection.

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
```

Create or update the local private config for Dedalus post analysis:

```bash
./v3 private-config
```

View a redacted summary of the current private config and update reminders:

```bash
./v3 private-config view
```

Print a concise reference for installing the queued agent reply cron job:

```bash
./v3 agent-reply cron
```

Run the queued agent reply worker directly:

```bash
./v3 agent-reply cron run --limit=10
```

Validate the configured agent reply LLM provider/API key with one live structured prompt:

```bash
./v3 agent-reply test
```

The default local file resolves to `../forum-private/secrets.php` relative to this checkout. To update only the Dedalus API key without putting it in shell history:

```bash
printf '%s\n' "$DEDALUS_API_KEY" | ./v3 private-config --api-key-stdin
```

The default Dedalus post-analysis prompt is stored in `prompts/dedalus_post_analysis_system.txt`. Set `DEDALUS_POST_ANALYSIS_PROMPT_PATH` in the private config to use a different text file; relative paths are resolved from the project root.

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
```

## Tests

Run the current parser, rebuild, and app smoke tests:

```bash
./v3 test
```

The custom test runner reports tests that take at least 5 seconds by default.
Override the cutoff with `FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS`, for example
`FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS=1 ./v3 test`.
