# Forte activity read-model compatibility and recovery plan

## Problem

`/forte/activity/` now renders a persisted Commits pane on every request. That
pane queries the `commits` table even when the visitor has selected an ordinary
activity view. Read models created before the Commits feature do not contain
that table, so SQLite raises `no such table: commits`. `FrontController` catches
the exception and presents it as a generic PHP-host configuration error. This
is a stale derived-data problem, not a host-configuration problem, and the raw
database exception is not actionable for visitors.

## Desired behavior

- A visitor never sees a raw `SQLSTATE` message for an expected stale or
  incomplete read-model condition.
- A current read model renders the complete Forte activity page, including
  Commits.
- An older read model either renders the five activity views without Commits or
  presents a clear, safe recovery page; it must not turn the entire activity
  route into a 503 host-configuration error.
- Operators can identify the database path, missing schema capability, and
  exact rebuild command without exposing private filesystem paths to visitors.
- Rebuild is the only way to populate commits; request handling must not query
  git or mutate the database to conceal a stale model.

## Implementation stages

### 1. Define and inspect read-model capabilities

- Add a small read-model schema/capability inspector next to
  `ReadModelConnection` (or a similarly focused service).
- It should safely check `sqlite_master` for required tables and, if practical,
  record a monotonic schema version in the existing `metadata` table during
  every full rebuild.
- Model the Commits feature as an explicit capability (`commits` table and its
  required columns/indexes), rather than treating an arbitrary PDO exception as
  a compatibility signal.
- Keep checks read-only and cache them only for the duration of one request.

### 2. Make Forte activity degrade safely

- Determine Commits availability before calling `fetchCommits()` or
  `countCommitsTotal()`.
- When unavailable, continue rendering the normal five activity panes. Omit
  the Commits filter and its related client-side data/endpoints from the page,
  and show a compact non-technical notice that commit history will return once
  the site finishes updating.
- Make `/api/forte_activity_page?view=commits` return a structured 409/503
  JSON response with a stable error code such as
  `read_model_capability_unavailable`; existing activity-page views continue
  normally.
- Make `/api/forte_commit_detail` return the same safe structured response
  before it accesses `commits` or cached manifests.

### 3. Classify errors at the host boundary

- Add a dedicated read-model-unavailable response in `FrontController`,
  separate from genuine PHP-host setup failures.
- Convert known read-model absence/staleness conditions into a visitor-safe
  page: explain that site data is being updated, offer a retry link and a link
  back to Forte, and avoid PDO/SQL text and absolute paths.
- Retain detailed exception context in server logs. For local/development mode,
  expose the diagnostic only behind an explicit development setting, never by
  default.
- Reserve the existing "PHP host configuration" response for invalid host
  roots, permissions, or other actual boot/configuration failures.

### 4. Give operators a deterministic recovery path

- Extend `/api/read_model_status` (and, if useful, the tools/status surface)
  with schema version/capabilities and a `rebuild_required` reason. Do not
  publish filesystem paths to regular visitors.
- Update the deployment and recovery runbooks: deploy the application code,
  then rebuild the same `FORUM_REPOSITORY_ROOT` and `FORUM_DATABASE_PATH` used
  by the vhost, then validate `/api/read_model_status` and
  `/forte/activity/?view=commits`.
- Document the local recovery command:

  ```bash
  php scripts/rebuild_read_model.php
  ```

  In a configured deployment, pass the vhost's repository and database paths
  explicitly, as the production runbook already prescribes.

### 5. Cover the compatibility contract

- Add a fixture database with normal activity tables but no `commits` table.
- Assert `/forte/activity/` remains successful, shows regular activity, omits
  Commits, and contains no SQL/PDO text.
- Assert the two commit-specific APIs return the stable structured error and
  do not return a PHP error page.
- Assert a rebuilt current database shows Commits and continues to paginate and
  render commit detail.
- Add a host-boundary test proving a known read-model error receives the
  recovery response while a genuine configuration failure remains distinct.

## Rollout and acceptance criteria

1. Deploy the compatibility handling before or with the feature code.
2. Rebuild every configured read model after deployment.
3. Confirm current models report the Commits capability and successfully render
   the Commits view.
4. During a simulated pre-feature database case, confirm ordinary Forte
   activity works, commit-only interfaces degrade safely, and no visitor-facing
   response includes `SQLSTATE`, `SQLite`, table names, exception messages, or
   absolute paths.

The immediate operational recovery for the observed error is to rebuild the
read model that the PHP vhost actually uses. The code change above prevents a
missed rebuild from presenting that internal failure to visitors again.
