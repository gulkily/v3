# PHP Production Deployment Checklist V1

This document tracks the remaining work between the current implementation state and a responsible production deployment.

It is intended to be updated as work completes.

## Progress

- Status: in progress
- Runtime/product slices: mostly complete
- Repo-owned production docs/config: completed
- External deployment validation: not started
- Last reviewed: 2026-04-10

## Current Findings

The application is no longer blocked on major runtime correctness slices.

Already present in the repo:

- PHP-owned reads and writes
- git-backed canonical write flow
- synchronous read-model refresh on writes
- stale derived-state marker and next-read recovery
- single-instance write/rebuild locking
- Apache `.htaccess` runtime model for static-first HTML serving
- static artifact generation into `public/`
- browser identity/bootstrap flow
- HTML template extraction into separate template files

What remains is primarily deployment and operations work rather than core feature construction.

## Production Readiness Checklist

### 1. Deployment Contract

- [x] Document the exact production directory layout
- [x] Document which directories must be writable by the web user
- [x] Document required PHP extensions and server assumptions
- [x] Document the environment variables used in production
- [x] Decide and document where the writable canonical repository lives in production

Status:
- completed in `docs/runbooks/production_deploy.md`

### 2. Apache Production Setup

- [x] Add a concrete Apache vhost example for production
- [x] Document `DocumentRoot` expectations
- [x] Document how `.htaccess` interacts with `AllowOverride`
- [x] Document required Apache modules such as `mod_rewrite`
- [ ] Manually verify the full route set on a real Apache host

Status:
- repo-owned documentation completed
- real-host validation still missing

### 3. Writable State and Permissions

- [x] Document writable `state/` expectations
- [x] Document writable repository expectations
- [x] Document ownership/permission expectations for `public/` artifact writes if used
- [ ] Verify behavior when writable paths are missing or misconfigured in production-like conditions

Status:
- documentation completed
- real host validation still useful

### 4. Operator Runbook

- [x] Add a production runbook for first-time setup
- [x] Add a runbook for manual read-model rebuild
- [x] Add a runbook for stale-marker recovery
- [x] Add a runbook for lock contention troubleshooting
- [x] Add a runbook for write failure troubleshooting

Status:
- completed in `docs/runbooks/production_deploy.md` and `docs/runbooks/operator_recovery.md`

### 5. Static Artifact Operations

- [x] Document when to build artifacts
- [x] Document when artifacts are invalidated automatically
- [x] Document how to rebuild artifacts manually
- [x] Document the expected `public/*.html` route layout
- [x] Decide whether production relies on sibling `public/*.html` artifacts, separate static roots, or both

Status:
- documentation completed

### 6. Backups and Recovery

- [x] Document what is canonical and must be backed up
- [x] Document what is derived and can be rebuilt
- [x] Document backup expectations for the writable repository
- [x] Document recovery expectations after host failure or bad deploy

Status:
- completed in `docs/runbooks/operator_recovery.md`

### 7. Production Smoke Checklist

- [ ] Validate anonymous board/thread/profile reads through Apache
- [ ] Validate static artifact serving for anonymous queryless routes
- [ ] Validate PHP fallback behavior when artifacts are absent
- [ ] Validate write flows on the production domain
- [ ] Validate browser identity/bootstrap under production cookies/HTTPS
- [ ] Validate read-model status visibility in production

Status:
- local automated coverage exists
- real production-like smoke pass is still outstanding

## Repo-Owned Deliverables Still Worth Adding

These items belong in the repo and can be completed before deploy:

- [x] `docs/runbooks/production_deploy.md`
- [x] `docs/runbooks/operator_recovery.md`
- [x] `docs/examples/apache_vhost.conf`
- [x] `docs/examples/env.production.example`
- [x] README links to the deploy/runbook docs

Status:
- completed

## External Work That Cannot Be Finished Only In-Repo

These items require the actual host or deployment target:

- [ ] create/configure the real Apache vhost
- [ ] configure filesystem ownership and permissions
- [ ] provision the writable canonical repository location
- [ ] test HTTPS/domain/cookie behavior on the real site
- [ ] run the final production smoke checklist

## Recommendation

The next highest-value repo work is:

1. add the production deploy document
2. add the operator recovery/runbook document
3. add a concrete Apache vhost example
4. add an env/config example

After that, move to a real Apache host and execute the production smoke checklist there.

## Progress Log

- 2026-04-10: created this consolidated production deployment checklist document
- 2026-04-10: added production deploy runbook, operator recovery runbook, Apache vhost example, env example, and README links
