# Production Deploy Runbook

This runbook describes the intended production deployment model for the PHP forum rewrite.

## Deployment Model

The intended production shape is:

- Apache serves `public/` as the `DocumentRoot`
- PHP handles dynamic requests through `public/index.php`
- Apache serves eligible sibling `*.html` artifacts directly when they exist
- the canonical writable repository lives outside `public/`
- derived state under `state/` is writable by the web user

This application assumes a conservative shared-host style environment with:

- PHP 8.1+
- PDO SQLite
- standard filesystem functions
- shell access sufficient for non-interactive `git` commands
- Apache with `mod_rewrite`

## Required Directory Layout

One workable layout:

```text
/srv/forum-rewrite/
  app/                      application checkout
    public/
    src/
    templates/
    scripts/
  repository/               writable canonical content checkout
    records/
    .git/
  state/
    cache/
    forum-rewrite.lock
    read_model_stale.json
```

Recommended mapping:

- application root: `/srv/forum-rewrite/app`
- Apache `DocumentRoot`: `/srv/forum-rewrite/app/public`
- writable repository root: `/srv/forum-rewrite/repository`
- read-model database: `/srv/forum-rewrite/state/cache/post_index.sqlite3`

## Writable Paths

The web user must be able to write:

- the canonical repository checkout at `FORUM_REPOSITORY_ROOT`
- the parent directory of `FORUM_DATABASE_PATH`
- the lock file directory next to `FORUM_DATABASE_PATH`
- sibling static artifacts in `public/` if production uses `public/*.html`

If sibling `public/*.html` artifacts are used and writes are enabled, the application must be able to invalidate affected artifacts after successful writes.

## Environment Variables

Use these in production:

- `FORUM_REPOSITORY_ROOT`
- `FORUM_DATABASE_PATH`
- `FORUM_PUBLIC_ARTIFACT_ROOT`

Suggested values:

```text
FORUM_REPOSITORY_ROOT=/srv/forum-rewrite/repository
FORUM_DATABASE_PATH=/srv/forum-rewrite/state/cache/post_index.sqlite3
FORUM_PUBLIC_ARTIFACT_ROOT=/srv/forum-rewrite/app/public
```

`FORUM_STATIC_HTML_ROOT` remains available for separate static roots, but the primary production model for this repo is sibling artifacts in `public/`.

## First-Time Setup

1. Check out the application code.
2. Create the writable canonical repository checkout.
3. Ensure the repository is a real git checkout with `records/` and `.git/`.
4. Create the writable state directory.
5. Configure Apache to serve `public/`.
6. Set the production environment variables.
7. Run the initial read-model rebuild.
8. Optionally build sibling static HTML artifacts.

Example commands:

```bash
php scripts/rebuild_read_model.php /srv/forum-rewrite/repository /srv/forum-rewrite/state/cache/post_index.sqlite3
php scripts/build_static_artifacts.php /srv/forum-rewrite/repository /srv/forum-rewrite/state/cache/post_index.sqlite3 /srv/forum-rewrite/app/public
```

## Pre-Launch Checklist

- Apache `DocumentRoot` points to `public/`
- `AllowOverride` permits `.htaccess` if using the checked-in rewrite file
- `mod_rewrite` is enabled
- PHP can open PDO SQLite
- the writable repository is a git checkout
- the web user can create commits in the writable repository
- the web user can write the read-model database and lock files
- the web user can invalidate `public/*.html` artifacts if sibling artifacts are enabled
- `/api/read_model_status` returns `status=ready`

## Manual Verification

Before launch, verify:

- board route loads
- thread route loads
- profile route loads
- account route loads
- compose thread/reply routes load
- anonymous queryless board/thread/profile requests can be served from sibling `*.html` artifacts
- cookie-bearing requests bypass static artifacts and fall back to PHP
- thread creation works
- reply creation works
- identity bootstrap works

## Recommended Launch Sequence

1. deploy application code
2. verify Apache config
3. verify writable repository/config paths
4. rebuild read model
5. build static artifacts
6. open `/api/read_model_status`
7. smoke-test core read routes
8. smoke-test one write flow

## Related Docs

- [operator_recovery.md](/home/wsl/v3/docs/runbooks/operator_recovery.md)
- [apache_vhost.conf](/home/wsl/v3/docs/examples/apache_vhost.conf)
- [env.production.example](/home/wsl/v3/docs/examples/env.production.example)
