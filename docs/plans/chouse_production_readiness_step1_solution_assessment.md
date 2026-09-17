# Chouse Production Release — Step 1: Solution Assessment

## Problem

The existing placeholder chouse site must be replaced by this codebase on
the same class of hosting environment, with approved-members-only access and
public backups safely configured for launch.

## Option A — Separate chouse vhost state using the existing deployment contract

Deploy the codebase as its own application/public root, repository, read
model, static-artifact root, and environment profile; set `FORUM_SITE_ID=chouse`
and disable public backups in production.

- Pros:
  - Matches the existing multi-site and production runbook design.
  - Keeps chouse content, cache, artifacts, and rollback independent.
  - Allows a clear DNS/vhost cutover and rollback to the placeholder.
- Cons:
  - Requires host-side configuration, permissions, rebuild, and smoke tests.
  - Requires an explicit backup/download policy while public backups are off.

## Option B — Replace the placeholder in place with shared state

Point the existing site deployment at the new codebase while retaining its
current paths and operational state.

- Pros:
  - Fewer host-side paths to configure.
  - Potentially faster cutover.
- Cons:
  - Risks mixing placeholder content, old artifacts, cache, or configuration.
  - Makes rollback and diagnosis less clear.
  - Increases the chance of serving stale public artifacts after cutover.

## Recommendation

**Option A.** Use an isolated chouse deployment consistent with
`docs/runbooks/production_deploy.md`, then switch the vhost/DNS only after a
production-shaped smoke test. Treat backup disablement as an independent
hardening change and verify every backup route is unavailable, including
legacy aliases and direct generated downloads.

## Missing launch pieces to verify

- Chouse repository seeded with intended content.
- `FORUM_SITE_ID=chouse` paired with chouse repository, database, and public
  artifact paths.
- Public backups denied by the site-wide gate and verified through every
  existing backup route and direct generated download path.
- Read model rebuilt from the production repository; static artifacts rebuilt
  or disabled so they cannot bypass the site-wide access gate.
- Approved and unapproved test identities for positive, lobby-only, and
  unauthorized-route smoke tests.
- HTTPS, writable state directories, cron/worker settings, permissions,
  backup/rollback procedure, and post-cutover health checks.
