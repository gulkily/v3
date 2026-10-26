# QDB high-traffic profile diagnosis

Investigated 2026-10-25 America/New_York (2026-10-26 UTC).
Scope: diagnose the reported homepage/design switch; no application fix or
production configuration change was performed. This was a narrow diagnostic
reproduction, not a Railspike implementation cycle.

## Finding

`HighTrafficCache::render()` starts `scripts/high_traffic_render.php` with
`proc_open()` without explicitly forwarding the resolved site profile. The
worker uses `SiteProfileRegistry::active()`, which reads `FORUM_SITE_ID` and
falls back to `zenmemes` when absent or invalid. Apache `SetEnv` can make the
value available to the request's PHP `getenv()` without putting it in the
operating-system environment inherited by the CLI subprocess.

The parent can therefore validate/cache under profile `qdb` while the worker
renders the generic Board page and default branding. The current worker
arguments and publication checks do not establish matching render profiles.

## Reproduction completed

Used the existing Apache test harness setup with isolated temporary canonical
records, SQLite, public root and Apache configuration. Added Apache
`SetEnv FORUM_SITE_ID qdb`, removed the variable from the Apache process's
inherited environment, and used `FORUM_SITE_ID=qdb` for operator CLI commands.
The disposable diagnostic script was `/tmp/qdb-profile-repro.py`; no external
components were added. Fixture server and cache were removed afterward.

| Request | HTTP | HTML title | High-traffic hit header |
| --- | --- | --- | --- |
| Normal homepage | 200 | Welcome | absent |
| First homepage after enable | 200 | Board | absent (PHP cold generation) |
| Subsequent homepage | 200 | Board | hit |
| After CLI refresh with exported QDB profile | 200 | Welcome | hit |

This reproduces the reported route change and demonstrates the subprocess
configuration boundary. It was an HTTP/HTML diagnostic, not visual acceptance
of a UI fix. No phone/desktop screenshots or complete regression suite were
needed or claimed for this diagnostic-only work.

## Production evidence and remaining check

A public GET of https://qdb.us/ returned HTTP 200, title `Welcome`, no
`X-V3-High-Traffic` header and no `v3-generated-at` marker. This response does
not reproduce the enabled-mode incident or prove the deployed configuration.
Production was not toggled, refreshed or otherwise modified.

The operator subsequently supplied the production `git diff public/.htaccess`
from `qdb@pdx1-shared-a2-13:~/v3`, showing `SetEnv FORUM_SITE_ID qdb`. This
confirms the production request-scoped configuration matches the local
reproduction. The production worker environment and deployed revision have not
yet been inspected directly. Inspect those and the environment used by CLI/cron
if further host-level confirmation is needed. Do not collect full environment/private-config dumps. A request-only
QDB setting with an absent/different CLI subprocess value matches the local
reproduction. Confirm whether enabling was through the web operator control or
CLI and whether the incorrect cached title is `Board`.

## Recommended follow-up

Explicitly transmit and validate the resolved profile at the renderer boundary;
add Apache regression coverage using a request-scoped QDB profile and no
inherited QDB process setting. Verify cold and warm homepage HTML retains QDB
welcome/navigation/assets, and normal profile rendering remains correct.
Review other renderer configuration that may have the same inheritance issue.

Keep production mode disabled until remediation is ready. A CLI refresh with
the correct exported QDB profile repaired this isolated fixture, but does not
fix later web-triggered cold generation. Production CLI/cron must also use the
site's correct repository, database, public root and configuration.

Dependency delta: zero. Application code and `todo.txt` unchanged.

## Enabled-mode production observation

After the operator explicitly enabled production mode for inspection, public
GET requests reproduced the problem directly:

- `/`: HTTP 200, title `Board`, `data-default-theme="auto"`, no QDB CSS
  references, generated timestamp `2026-10-26T01:04:13Z`.
- `/?profile_diagnostic=1` (query bypass): HTTP 200, title `Welcome`,
  `data-default-theme="qdb"`, fingerprinted `theme-qdb` CSS references, no
  generated timestamp.

The first homepage response lacked the high-traffic hit header, consistent
with cold generation. A subsequent `/` request returned the same `Board` HTML
and timestamp with `X-V3-High-Traffic: hit`, confirming the warm cache also
serves the wrong profile. This comparison confirms production serves the wrong
profile through high-traffic rendering while live rendering retains QDB.
Only public GET requests were made; mode changes were performed by the operator.

## Implemented fix and verification

The user approved the small direct fix after the diagnosis; no Railspike cycle
was opened. `HighTrafficCache::render()` now passes the resolved profile name
as a required argument. The worker validates it against `SiteProfileRegistry`
and sets its own process environment before publication/configuration/rendering
work. Missing or unknown profiles fail rather than silently choosing a default.
The optional publication-identity argument follows the profile and retains its
existing validation.

Verification completed:

- Added the request-scoped QDB scenario to `tests/high_traffic_apache_test.py`.
  Before the fix, it failed on the cold homepage with title `Board` and theme
  `auto`. After the fix, the complete Apache suite passed, including normal,
  cold, warm and explicit CLI refresh QDB pages, unchanged warm HTML, and warm
  requests avoiding PHP. Existing default-profile routing/publication/retention
  checks also passed.
- `php tests/run.php HighTrafficTest`: 6 passed, including missing/unknown
  profile rejection, isolated rendering, renderer limits, and missing-page
  behavior.
- Chromium with existing Playwright dependencies, isolated Apache and the
  request-scoped QDB setting: normal and cached homepages loaded at 375x850 and
  1150x850. Title, QDB theme selection, stylesheet HTTP 200, cache marker and
  absence of horizontal overflow checked. Phone and desktop screenshots were
  inspected; welcome/navigation remained visible. Normal and cached screenshots
  were byte-identical at each size. This fixture has zero QDB quotes and no site
  news; populated production content and other selectable themes were not
  visually retested. No templates, controls, copy or theme CSS changed.
- `git diff --check`: passed.

Temporary verification outputs: `/tmp/qdb-php-tests.log`,
`/tmp/qdb-apache-before.log`, `/tmp/qdb-apache-after.log`, `/tmp/qdb-visual.log`,
`/tmp/qdb-{normal,cached}-{375,1150}.png`. The disposable browser script is
`/tmp/qdb-profile-visual.mjs`; its Apache driver is
`/tmp/qdb-profile-fixed-visual.py`. These are local evidence, not deployed assets.

Production deployment is outstanding. Disable high-traffic mode before deploying
both changed PHP files together; re-enable afterward to clear the old cached
Board HTML. Verify cold and warm `/` return Welcome with the QDB theme, including
`X-V3-High-Traffic: hit` on the warm response. CLI/cron must still receive the
correct site profile and instance paths; this fix carries the selected parent
profile into its worker, not Apache configuration into unrelated cron processes.
No production changes were made by the agent.

Final dependency delta: zero. No read-model schema changes. `todo.txt` unchanged.

### Push-time integration verification

The initial push was rejected because remote `main` had advanced. Rebased the
fix cleanly onto `b6bf1c327` before retrying. On that integrated revision:

- Focused `HighTrafficTest`: 6 passed again.
- Isolated request-scoped QDB reproduction and Chromium phone/desktop checks:
  passed again; normal, cold, warm and CLI-refreshed pages all returned Welcome.
- The complete Apache suite now stops at its existing native-import subprocess
  on line 191 (exit 255), before reaching the added QDB assertions. Running the
  unmodified `origin/main` version of the harness against the integrated code
  stopped at the same import subprocess. This integration limitation is separate
  from the earlier complete-suite pass; the suite is not claimed green after
  rebase. Import diagnosis remains follow-up outside this profile fix.

Logs: `/tmp/qdb-push-php.log`, `/tmp/qdb-push-profile.log`,
`/tmp/qdb-push-apache.log`, `/tmp/qdb-upstream-apache.log`.
