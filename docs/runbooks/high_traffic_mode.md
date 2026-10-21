# High-traffic mode

High-traffic mode serves shared public HTML directly through Apache, including
requests with initialized identity/session cookies. Routine changes normally
appear after the next refresh, targeting about one to two minutes under supported
load. Account, API, write, query-string and Authorization-header requests remain
live. Shared public pages leave sign-in to live actions and protected pages, avoiding repeated authentication and reloads just to repaint identical HTML. Access-affecting writes retire direct serving before publication.

## Host setup

Use Apache 2.4 with `mod_rewrite`, `mod_headers`, `AllowOverride All` (or equivalent
permissions for rewrite/header/Options directives), `FollowSymLinks`, PHP and a
local filesystem supporting atomic rename and `flock`. Serve this application's
`public/` as the URL root. Configure one stable site profile and public root per
instance; this initial version allows only one active serving root/profile per
canonical repository. Disable before changing the site profile, document root,
application package, environment/private configuration, or canonical records
outside supported application writes. Existing in-flight responses may finish.

The PHP/CLI user needs write access to the document-root directory, its regular
`.htaccess`, the local `state/high_traffic/` directory and the configured read
model. Run web and cron with the same repository, database, site profile,
secrets/configuration and public root. `FORUM_PUBLIC_ARTIFACT_ROOT` selects the
served public root when it differs from the application's `public/`. The renderer
uses `PHP_BINDIR/php`; set `FORUM_PHP_CLI` to the absolute CLI PHP executable if
needed. Never point it at an HTTP handler or untrusted executable.

Back up custom hosting configuration. The writer prepends a managed section and
preserves the normal rules, requiring the existing front-controller layout. It
refuses damaged markers or an unsupported layout. The generated rules have local
Apache test coverage; the operator must verify this host's modules, override
policy and request behavior. An Apache configuration syntax check alone may not
validate `.htaccess`.

## Enable and verify

Use **Tools → Feature Flags → High-traffic mode**, or run commands from the
application checkout with the same environment as PHP:

```bash
./v3 high-traffic status
./v3 high-traffic enable
```

The operator UI uses the existing approved root-operator signing flow. Mode state
is instance-local, not a replicated site feature flag. Status includes the applied
mode, change reason and generation. The public-root `.high-traffic` symlink is
managed; do not replace it with a directory. Cache files live outside the public
root. Their direct storage URLs are blocked.

Initially eligible canonical routes are `/`, `/threads/`, `/about/`, and
`/threads/{visible-thread-id}/`. Existing aliases redirect to canonical paths
while the mode is active. The first miss generates within global/per-page limits.
Open an eligible page twice, including with a session cookie, and verify a warm
response contains `X-V3-High-Traffic: hit` and revalidating Cache-Control. HEAD must
return no body. Confirm the second HTML request avoids PHP in the host's PHP
request logs/instrumentation; access logs alone do not establish that. Query and
Authorization requests should lack the hit header; direct `/.high-traffic/` URLs
must not disclose files. Check existing authenticated actions from the cached
page. If these checks fail, disable and correct host configuration before use.

## Refresh

Configure cron every minute, using the deployment's normal environment:

```cron
* * * * * cd /path/to/application && ./v3 high-traffic run /path/to/access.log >> /path/to/private/high-traffic-cron.log 2>&1
```

The sysop owns log location, permissions, rotation, retention and cron monitoring.
The reader samples the latest X lines within a byte ceiling; it maintains no log
cursor. It understands Common/Combined-style request/status fields and counts
eligible GET paths with 2xx/3xx responses. Use a per-instance/vhost log so another
site's requests do not distort priority. A missing log is reported; queued
low-priority work can still proceed. The worker emits a JSON result and saves the
latest result locally in `refresh-status.json`; partial results must be monitored
along with stderr. Refresh is manual only if cron has not been installed.

Popular stale pages refresh first. Routine writes coalesce a refresh marker so the next run can refresh popular pages even while their files are still fresh; they do not cancel running renders. Ordinary likes preserve the mode, while moderation flags retire direct serving before publication. A few slots are reserved for low-priority
hints from displayed stale pages. HTML embeds UTC generation time, refresh age
and cache generation; timestamp hints do not prove source-data freshness and do
not authorize publication. The server rechecks actual file age and deduplicates
hints. There is no client polling loop. Old eligible HTML remains during refresh
and on refresh failure; there is no deliberate expiration gap. A missed/overloaded
cron can exceed the freshness target, and less popular pages have no hard
freshness guarantee.

```bash
./v3 high-traffic refresh /about/
./v3 high-traffic purge
```

Refresh uses normal generation limits. Purge withdraws all HTML and fences old
renderers; later requests/cron regenerate it. It intentionally causes cold misses.
Retained fingerprinted assets stay available for previously served HTML.

## Limits and overload

All numeric settings use environment variables prefixed `FORUM_HIGH_TRAFFIC_`.
Positive integer overrides are capped at ten times the corresponding default.
Keep web and CLI settings consistent.

| Suffix | Default | Meaning |
| --- | ---: | --- |
| `LINES` | 10000 | Latest log lines sampled |
| `READ_BYTES` | 16777216 | Maximum suffix read |
| `LINE_BYTES` | 16384 | Maximum accepted line |
| `FRESH_SECONDS` | 60 | Skip newer pages unless routine writes requested refresh |
| `RENDERS` | 50 | Render attempts per cron run |
| `RUN_SECONDS` | 45 | Cron render budget |
| `GENERATORS` | 2 | Concurrent renderers across cron/requests |
| `WAIT_MS` | 250 | Maximum page-lock wait |
| `PAGES` | 1000 | Published HTML page limit |
| `BYTES` | 268435456 | Published HTML and retained asset byte limit |
| `PAGE_BYTES` | 1048576 | Individual HTML limit |
| `QUEUE` | 1000 | Pending client hints / ranked route cap |

A renderer is limited to 30 seconds, further constrained by remaining cron
budget. Small bounded metadata/locks and per-generator temporary assets add disk
overhead to the published-byte limit. Abandoned temporary files are cleaned after
one hour by cron. Assets are staged before admission and retained only within the
published budget. When capacity is exhausted, cold requests receive 503 with
`Retry-After: 5`; do not configure an unlimited rendering fallback. Old eligible
pages continue serving when refresh fails. Page locks use bounded hash buckets;
rare collisions may briefly serialize different pages.

Configure host worker/process limits and request-rate controls separately:
application generation limits do not prevent a flood from occupying PHP workers.
Use per-vhost Apache access logs for traffic measurement; PHP visitor statistics
will undercount Apache-served page views. Read the limits and workload results as
local acceptance evidence, not a production throughput guarantee.

## Disable and recovery

```bash
./v3 high-traffic disable
./v3 high-traffic status
# If damaged routing prevents web recovery:
./v3 high-traffic restore
```

Disable removes the serving pointer before restoring normal PHP routing and
fences work already rendering. Restore reinstalls the saved normal `.htaccess`
for its original public root; it can recover damaged mode state or managed rules.
The CLI must use that same repository/public-root configuration. If no saved
routing exists, restore the application's normal `public/.htaccess` from the
matching package while preserving reviewed host-specific rules, and ensure the
`.high-traffic` serving symlink is absent. Never delete the symlink target by
following the link.

On a failed switch, check the reported error and actual status; partial failures
may leave routing in normal mode even if the stored requested state says enabled.
No success is reported before routing/state publication finishes. Preserve prior
working files on write failure; fix filesystem ownership/free space before retry.
A refresh failure retains the old eligible page and logs the error. There is no
automatic repair or load-triggered activation in this release. Future automatic
activation must honor maintenance locks and operator overrides as well as reuse
the switching service.

Feature/access/retraction and classification changes may retire direct serving
conservatively. Review the completed change and re-enable manually if appropriate;
re-enabling clears previous HTML so withdrawn content cannot return. Do not
re-enable during an external import, archive, deployment or configuration change.
For application-native writes, publication/switch locks coordinate the change.
For external maintenance, disable first and keep the mode disabled until the
canonical records/read model/configuration are consistent. Stop or pause cron for
maintenance where needed.

To reclaim retained assets after long deployment history, first disable and stop
refresh work, allow current renderers to finish, and use an operator-chosen asset
retention policy before removing old cache storage. Previously served or offline
HTML may still reference old assets; deleting them can break those pages. Normal
purge deliberately retains assets. Maintain the sysop's disk monitoring and
backup/recovery procedure; broader dashboards, alerts and automated repair remain
separate follow-up work.

## Local acceptance

```bash
php tests/run.php HighTrafficTest FeatureFlagsBehaviorTest WebServerRoutingTest RetractionSweepTest FastmodMetaTest
python tests/high_traffic_apache_test.py
python tests/high_traffic_publication_test.py
node tests/browser/high_traffic_control.mjs
```

These use isolated fixtures and do not enable high-traffic routing on the real
checkout. The Apache harness covers session-bearing warm hits with PHP invocation
counting, HEAD/bypasses, capacity refusal, concurrent cold requests, twenty-page
source refresh, atomic old/new reads, failed-refresh retention, obsolete renderer
rejection, private transition and CLI recovery. Browser checks cover real signed
operator control, live action while a page is cached, unauthorized rejection,
pending/error/retry behavior and phone/desktop shared themes. Cached navigation
does not force sign-in or reload; signed likes preserve the active mode. Routine
concurrent writes do not cancel a background refresh. Missing pages retain 404
responses and renderer jobs are excluded from visitor counts.

The publication recovery harness replays the existing import crash/retry matrix
with real implementation/templates/assets and a two-document inventory. The
full-project repeated documentation-build matrix is intentionally not its claim.

CLI output is readable by default; use `--json` for automation.
`./v3 high-traffic cron /path/to/access.log` prints the installation instructions
and crontab line; `run` executes one refresh. Existing schedules using `cron`
as the worker must change to `run`. Enable/status also report the cron line;
they do not install or verify a schedule. Use the web app's environment and user.
