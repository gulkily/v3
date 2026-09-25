# Multi-Site Config — Step 1: Solution Assessment

## Problem

One codebase must serve zenmemes.com and chouse.club as separately
branded deployments (site name, default theme, composer copy) without
hardcoding either site's identity into the code zenmemes already runs.

## Option A — Site-profile registry + single `FORUM_SITE_ID` env var
A new registry file holds one entry per site (name, default theme,
composer copy); one env var selects which entry is active per vhost.
- **Pros:** one override point per vhost (low drift risk); mirrors the
  `ThemeRegistry` pattern already established in this codebase; absent
  var defaults to zenmemes, so today's deploy is untouched; adding a
  third site later is one new entry, not new plumbing.
- **Cons:** new file/class to introduce; branding fields not overridable
  without a code change + release (acceptable — branding isn't meant to
  be a runtime toggle).

## Option B — Independent `FORUM_*` env vars per branding field
Extend the existing `FORUM_REPOSITORY_ROOT`-style pattern directly:
`FORUM_SITE_NAME`, `FORUM_DEFAULT_THEME`, `FORUM_COMPOSER_HEADING`, etc.
- **Pros:** no new abstraction, fits the existing single-var precedent
  exactly.
- **Cons:** every new branding field is a new env var that both vhosts'
  configs must stay in sync with; nothing enforces "these fields belong
  to the same site"; easy to half-configure a vhost and get a hybrid
  identity by accident.

## Option C — Git-backed site-content flag (reuse `FeatureFlagRegistry`)
Store site identity as a mutable, git-tracked flag like
`UNICODE_AUTHORED_TEXT`, editable from `/tools/feature-flags/`.
- **Pros:** reuses existing auditable/git-committed precedence machinery.
- **Cons:** wrong mutability model — that system is for runtime-toggleable
  operational behavior, not "which deployment is this"; would let a
  root-approved user flip a live site's identity from the UI, which is a
  deploy-time decision, not a content decision; couples branding to a
  specific site's git repo when the whole point is one codebase serving
  two independent repos.

## Option D — Runtime `Host`-header detection, single shared process
One deployed instance inspects `$_SERVER['HTTP_HOST']` per request and
branches on it for both branding *and* content/database paths.
- **Pros:** avoids doubling the deploy footprint.
- **Cons:** recreates, inside PHP, the per-vhost routing Apache already
  does for free via `SetEnv`; every content-path getter needs
  Host-conditional logic sprinkled through the app; one shared process
  means a chouse-specific bug or load spike can affect zenmemes; directly
  reintroduces the static-artifact collision problem already ruled out
  (`public/threads/<id>.html` would need Host-scoping too).

## Recommendation

**Option A.** It has the smallest blast radius on the already-running
zenmemes site (silent default, no behavior change until the env var is
set), reuses an idiom this codebase already trusts (`ThemeRegistry`), and
avoids both B's per-vhost drift risk and D's re-implementation of routing
Apache already provides. C is rejected as a category-mismatch: site
identity is a deploy-time constant, not an operator-mutable content flag.

## How to configure (Option A, once built)

One new environment variable, set the same way as the three existing
`FORUM_*` path vars — per vhost, alongside them:

| Variable | Values | Default if unset |
|---|---|---|
| `FORUM_SITE_ID` | `zenmemes`, `chouse` | `zenmemes` |

**Per-vhost `SetEnv` block** (extends the existing pattern in
`docs/examples/apache_vhost.conf`):

```apache
# zenmemes.com vhost
SetEnv FORUM_SITE_ID zenmemes
SetEnv FORUM_REPOSITORY_ROOT /srv/zenmemes/repository
SetEnv FORUM_DATABASE_PATH /srv/zenmemes/state/cache/post_index.sqlite3
SetEnv FORUM_PUBLIC_ARTIFACT_ROOT /srv/zenmemes/app/public

# chouse.club vhost
SetEnv FORUM_SITE_ID chouse
SetEnv FORUM_REPOSITORY_ROOT /srv/chouse/repository
SetEnv FORUM_DATABASE_PATH /srv/chouse/state/cache/post_index.sqlite3
SetEnv FORUM_PUBLIC_ARTIFACT_ROOT /srv/chouse/app/public
```

**Local/CLI runs** (`./v3 start`, scripts): set the same var before the
command, matching how `FORUM_REPOSITORY_ROOT` is already used locally:

```bash
FORUM_SITE_ID=chouse ./v3 start
```

**Existing single-site deploys need no change.** Omitting `FORUM_SITE_ID`
resolves to the `zenmemes` profile, identical to current behavior — this
var is additive, not a required migration.

**What it controls:** which entry of `SiteProfileRegistry` is active for
the request — site name, default theme, and composer copy. It does
*not* select content/database paths; those stay on the three existing
`FORUM_*` vars, set independently per vhost as shown above. A vhost with
`FORUM_SITE_ID=chouse` but zenmemes' repository/database paths would
show chouse's branding over zenmemes' content — a misconfiguration, not
a supported mode, so the two must be kept paired per vhost by whoever
edits the config.

Precedence and rollback follow the same shape as the existing `FORUM_*`
vars documented in `docs/runbooks/production_deploy.md`: environment
variable wins, code default (`zenmemes`) applies when absent, and
rollback is deleting the `SetEnv` line.
