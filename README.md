# PHP Forum Rewrite

Minimal local test slice for the rewrite spec.

## Production Docs

Production-facing deployment and operations docs now live here:

- [Production Deploy Runbook](/home/wsl/v3/docs/runbooks/production_deploy.md)
- [Operator Recovery Runbook](/home/wsl/v3/docs/runbooks/operator_recovery.md)
- [Apache Vhost Example](/home/wsl/v3/docs/examples/apache_vhost.conf)
- [Production Env Example](/home/wsl/v3/docs/examples/env.production.example)
- [Production Deployment Checklist](/home/wsl/v3/docs/plans/php_production_deployment_checklist_v1.md)

## Local Run

The default local runtime now bootstraps and uses `state/local_repository` automatically. On first run it copies the committed fixture seed into that writable git repo, so thread/reply/bootstrap writes work without setting `FORUM_REPOSITORY_ROOT`.

Rebuild the SQLite read model:

```bash
php scripts/rebuild_read_model.php
```

Build Apache-friendly static HTML artifacts into `public/`:

```bash
php scripts/build_static_artifacts.php
```

Start the local PHP server:

```bash
./v3 start
```

For Apache/shared-host deployment, `public/.htaccess` is now part of the intended runtime model:

- serve existing files and directories directly
- serve queryless cookie-free sibling `*.html` artifacts directly for `/`, `/instance/`, `/activity/`, `/users/`, `/threads/<id>`, `/posts/<id>`, and `/profiles/<slug>`
- fall back to `public/index.php` when no static artifact exists

That matches the planning assumption that Apache should serve static-safe anonymous HTML directly and use PHP only as fallback.

The repo-owned deployment contract is now documented in the production runbook. What remains before a real production launch is mostly host-side validation on the actual Apache target.

Open these routes:

- `http://127.0.0.1:8000/`
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

The default server uses [state/local_repository](/home/wsl/v3/state/local_repository) when `FORUM_REPOSITORY_ROOT` is unset. Override it with `FORUM_REPOSITORY_ROOT=/path/to/repo` if needed.

Compose routes now use submit-time browser identity bootstrap for brand-new users:

- no keypair is generated on page load
- on the first real submit, the browser prompts for username, generates an OpenPGP keypair, publishes the public key in the background, sets the identity-hint cookie, and then continues the original post submit
- existing browser-local keypairs skip regeneration
- if browser generation/bootstrap fails, the draft stays intact and `/account/key/` remains the manual fallback

If you want to initialize that writable repo explicitly ahead of time:

```bash
php scripts/init_local_repository.php
FORUM_REPOSITORY_ROOT=/home/wsl/v3/state/local_repository php scripts/rebuild_read_model.php
FORUM_REPOSITORY_ROOT=/home/wsl/v3/state/local_repository ./v3 start
```

Static HTML artifacts for anonymous queryless route hits default to `state/static_html`. Override that location with `FORUM_STATIC_HTML_ROOT=/path/to/static_html` if you want to test direct artifact serving.

The current PHP front controller supports both layouts:

- Apache/public sibling artifacts such as `public/threads/root-001.html`
- the older separate `FORUM_STATIC_HTML_ROOT` fallback path for local testing or deployments that keep generated artifacts outside `public/`

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
./v3 approval seed openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
./v3 approval approve openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954 openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
```

## Tests

Run the current parser, rebuild, and app smoke tests:

```bash
php tests/run.php
```
