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

## Retained retractions with absent targets

A valid retained retraction can outlive a physically deleted source. High-traffic
HTML generation now classifies this as a build-local exclusion. For these builds,
the isolated renderer derives a fresh disposable model from canonical records,
excludes the absent targets and explicitly linked generated responses/labels, and
recomputes summaries. It never publishes stale live-model prose or edits canonical
records. A missing root retains a content-free structural row for surviving replies;
existing retracted-thread board-listing policy still applies. Independent replies
and independent label actions remain intact.

The parent and worker check matching evidence/content context before and after
rendering and again at publication. Source reappearance or other canonical changes
during an excluded build reject that attempt; retry validates the current state.
Ordinary builds without absent targets retain stale-tolerant routine-write behavior.
Warm hits do no model preparation. Cold/refresh builds with exclusions do more work
inside the existing worker timeout; an exceeded limit is a reported failure, never
an unbounded fallback. The private disposable model is removed with its workspace
and is never copied into public assets.

Malformed, missing tracked, unreadable or unsafe evidence and untrusted removal
records remain failures. Other strict publication consumers retain their existing
rules; successful HTML generation does not grant permanent deletion authority.
Do not delete retraction evidence, restore removed prose, or purge remaining usable
pages to silence a validation error. Inspect the reported cause and use disable or
restore while correcting invalid evidence through the supported operator process.

### zenmemes production recovery checklist

Keep high-traffic mode **disabled during deployment**. Deploy the tested application
revision, including its completed absent-target offline-snapshot changes, using the
normal release process. Do not reset or modify the separate canonical repository.
Use the deployed checkout, web app's normal user/environment, PHP CLI and public-root
configuration. For the observed host the checkout is `/home/dh_zsagad/v3`.

First verify ordinary `/` and a surviving thread return HTTP 200. Then enable and
explicitly warm the homepage; on generation failure immediately disable again:

```bash
cd /home/dh_zsagad/v3
./v3 high-traffic status
./v3 high-traffic enable
./v3 high-traffic refresh /
```

If either command fails, run `./v3 high-traffic disable`, inspect its error and verify
ordinary `/` still returns 200. If managed routing is damaged, use
`./v3 high-traffic restore`. Keep mode disabled while investigating. Deployment and
production re-enablement are operator actions, not automatically performed by tests.

After successful warming, request `https://zenmemes.com/` twice and a representative
surviving thread twice. Confirm HTTP 200, `X-V3-High-Traffic: hit`, normal controls,
surviving replies and absence of deleted content; a second request must avoid PHP
in host instrumentation. The two incident targets were
`reply-20260526032118-654b2b08` and `reply-20260526032816-a1447d49`. No source restoration
or accepted-removal fabrication is needed for these valid absent-target retractions.

Install refresh automation using the actual per-vhost access log path:

```bash
./v3 high-traffic cron /path/to/vhost-access.log
```

This **prints** installation instructions and the once-per-minute `run` command;
it does not install cron. Add that printed line to the deployment user's crontab,
retaining its normal environment. The production investigation found no such job.
Verify a manual `run` and the subsequent scheduled run report successful refreshes
in `refresh-status.json` and the private cron log. Age a test page only in an isolated
fixture; on production wait for the configured freshness interval, run refresh and
confirm the HTML `v3-generated-at` timestamp advances. A route seen only as 503 may
not be selected by the popularity sampler; explicit `refresh /` ensures recovery.

Confirm direct and queued offline snapshot publication through the existing
snapshot runbook if those artifacts were previously failing. The separate
uncaught-error handling of invalid-evidence offline HTTP bootstrap remains a
follow-up; this change does not weaken invalid-evidence checks. Local Apache and
browser acceptance do not establish DreamHost acceptance: record the deployed
revision, home/thread cache hits, refresh/cron results and rollback outcome on that
host before calling production recovery verified.

### Diagnosing a refused explicit refresh

`./v3 high-traffic refresh /` reports the specific refusal on stderr and exits 1
(including with `--json`), rather than combining inactive mode and capacity:

- **Page generation is already running:** the route's bounded page-lock bucket is
  occupied. Let that render finish, then retry; the default lock wait is 250 ms.
- **All generator slots are occupied:** other cold/refresh renders hold every
  configured slot. Retry after they finish; the default is two slots.
- **Cache page limit reached:** inspect cache usage and `FORUM_HIGH_TRAFFIC_PAGES`;
  the message includes the observed count and limit. Do not purge usable pages just
  to diagnose this condition.
- **Inactive or profile/public-root link mismatch:** inspect `./v3 high-traffic status`
  in the same environment/user and correct the configuration before retrying.
- **Route not eligible:** choose `/`, `/threads/`, `/about/` or a canonical visible
  thread route. Query strings and tool pages are not cache-generation targets.

Generator-slot file-open failures report a cache-permissions error separately.
These diagnostics retain existing bounded admission and HTTP busy-page behavior.

### Renderer resource-limit diagnostics

Renderer failures distinguish the time limit, HTML-output byte limit and
stderr-output byte limit. Each message includes the observed elapsed time and the
last reported stage (evidence validation, isolated-model rebuild phase, candidate
validation, public HTML rendering or asset copying). Read-model signature indexing
is timed separately. Progress contains operation names/counts, not record payloads.
Normal result stdout is unchanged. CLI `refresh` and `run` stream timestamped
progress to stderr as work happens, including with `--json`; redirect stderr to a
log when automating. The parent also retains worker progress for failure diagnosis. If no stage arrived, the error says worker startup.

An approximately 30-second failure is not proof that a rebuild is the bottleneck.
Use the reported stage to select the next check. Do not raise limits or relax
publication guards to hide an unlocalized delay. The production 36.325-second
refresh failure remains unresolved until stage-specific host evidence is available.

CLI progress starts before repository/configuration resolution and identifies the
selected repository, database, public root and cache directory. It then reports
page/publication locks, generator admission, absent-target count, isolated-worker
limits, rebuild stages, HTML rendering, asset copying, validation and publication.
A quiet running renderer emits a heartbeat every five seconds with its current
stage and HTML byte count. Failures report total command elapsed time; renderer
failures additionally report their own elapsed time/limit and last stage. Website
requests do not receive this CLI progress output.

To capture both live diagnostics and the machine-readable result separately:

```bash
./v3 high-traffic refresh / --json > /tmp/high-traffic-result.json 2> /tmp/high-traffic-progress.log
```

For interactive diagnosis, run the command normally to see progress immediately.
