> **Feature plan:** [Step 1](./high_traffic_auto_step1_solution_assessment.md) · [Step 2](./high_traffic_auto_step2_feature_description.md) · [Step 3](./high_traffic_auto_step3_development_plan.md) · [Step 4](./high_traffic_auto_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/high_traffic_auto`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.70**

Step 3 approved on 2026-11-12 ("approved step 3"). Branch `feat/high_traffic_auto` from `c5d8847`; planning commit `fc9a39b`. Main moved from `273bf93` to `c5d8847` between planning and implementation; none of the files this plan touches changed.

## Entry snapshot

2026-11-12T10:40:00Z — Step 4 entry. Projected total **0.70**, Step 3 value reaffirmed unchanged. Stage originals copied from Step 3 (copied, not re-rated): S1 0.10, S2 0.10, S3 0.12, S4 0.08, S5 0.10, S6 0.10, S7 0.08. Same R1/P2, single exposed Claude rater.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 1 - Request counter and cost study

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `src/ForumRewrite/Host/HighTrafficRate.php` (new): `record()` appends one byte to a file named for the current second under the cache's `rate/` directory; the request that creates the bucket (`fopen` exclusive) is the only one that prunes and, from Stage 3, checks the rate. `perSecond($window)` sums complete seconds by file size. No lock.
  - `src/ForumRewrite/Host/HighTrafficAutomation.php` (new, read side only): reads `automation.txt` in `Key: value` form; `turnOnEnabled()` gates counting. Writes and CLI follow in Stage 2.
  - `FrontController`: counts each PHP request while **Turn on automatically** is on, beside the existing high-traffic checks; skips private instances and the refresh-hint endpoint.
  - `tests/HighTrafficAutomationTest.php` (new), registered in `tests/run.php`.
- Verification:
  - `./v3 test --smoke HighTrafficAutomationTest HighTrafficTest`: 457 run, 457 passed. Full suite not run.
  - Microbenchmark (PHP 8.3 CLI, 200,000 calls): settings read plus `record()` 5.8 µs per request; switch off (settings file absent) 1.3 µs.
  - Stress probe against a local Apache 2.4 + mod_php harness (prefork, 8 workers, fixture site with 20 threads; probe on the same 2-CPU machine), normal mode, steps 1,2,4 at 15 s, alternating counter off/on three times. p50 / p95 in ms at 4 readers: off 54/76, 48/66, 49/70; on 46/67, 52/74, 49/70. Throughput at 4 readers: off 62.1, 68.6, 65.7 req/s; on 70.3, 63.3, 66.9. Differences in both directions are within run-to-run variation; the cost requirement holds. The counter's directory held 48 buckets after each run, so counting and pruning worked under load.
  - Limitation: pages render in about 30 ms locally against about 150–200 ms on zenmemes.com, so the counter is a smaller share there; the harness used a fixture, not production data.
- Notes:
  - The settings reader moved from Stage 2 into Stage 1 because the hot-path gate needs it; Stage 2 keeps writes, sources and CLI.
  - Reused: `HighTrafficCache::root` for the directory; `FrontController` high-traffic block. New `HighTrafficRate` because nothing counts PHP requests today.
- Estimate details: original scope as Step 3; uncertainty low; no revisions. Retrospective equals original: the Apache harness needed a non-root user and a local Apache install, offset by a simple implementation.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 2 - Who changed the mode, and the two settings

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `HighTrafficMode::change(bool $enable, string $reason, string $source = 'operator')`: sources `operator`, `automatic`, `withdrawal`; the state records `source`. An operator off sets `Manual-Off-Hold: yes` in `automation.txt`; an operator on clears it.
  - `HighTrafficMode::status()` adds `source`, `turn_on_automatically` and `turn_off_automatically`.
  - `HighTrafficSafety::beforeWrite` withdrawals write `source: withdrawal`.
  - `HighTrafficAutomation`: `set()` (under the publication and cache locks), `write()`, `turnOffEnabled()`, `turnOnBlocked()`, `isWithdrawal()`, `source()`. Settings stay a `Key: value` text file.
  - CLI: `./v3 high-traffic auto-on on|off` and `auto-off on|off`; `status` shows source and both switches.
- Verification:
  - New tests: sources and the operator hold; withdrawals (marked, detached without a state write, and legacy reason-only); text settings and status; CLI. `./v3 test --smoke HighTrafficAutomationTest HighTrafficTest HighTrafficPostRefreshTest RecentPostTest`: 471 run, 471 passed. Full suite not run.
- Notes:
  - Every writer of the mode state, checked by grep: `HighTrafficMode::change`, `restore` and `purge`; `HighTrafficSafety::beforeWrite` (feature flags, approvals, moderation tags; also reached from imports, history purge, `delete-record`, `delete-thread` and `archive-thread`). `StaticArtifactInvalidator` removes the serving link without writing state. Coverage: marked withdrawals and detached-but-enabled states count as withdrawals; `restore`, `purge` and legacy offs count as operator offs.
  - A legacy off has no hold, so automation may turn the mode on once traffic exceeds the threshold, matching an operator off whose hold has been released.
  - Reused: `HighTrafficMode::change` as the only switch; `HighTrafficCache::atomic`, `locked` and `PublicationLock`.
- Estimate details: original scope as Step 3; uncertainty low; no revisions. Retrospective equals original.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 3 - Automatic turn-on

**Original estimate: 0.12**

**Retrospective: 0.08**

- Changes:
  - `HighTrafficAutomation::checkTurnOn(HighTrafficMode, HighTrafficRate)`: above `turnOnRate()` (default 3 PHP requests per second over 15 seconds; `FORUM_HIGH_TRAFFIC_AUTO_ON_RATE` overrides) and with nothing blocking, calls `change(true, 'Turned on automatically, N requests/s', 'automatic')`; below it, releases an operator off's hold.
  - `FrontController`: the request that opens a new second runs the check; errors are logged and the request is served normally.
- Verification:
  - Unit test: below threshold no change; an operator off holds while busy, is released by a quiet reading, then a busy reading turns the mode on with source `automatic` and the measured rate; already on, no second change; a withdrawal keeps automation out; threshold override and invalid value.
  - Local Apache ramp, mode off and **Turn on automatically** on, probe steps 1,2,4 at 10 s (`--max-rps 200`): the mode turned on 2–4 seconds into step 1 ("Turned on automatically, 4.5 requests/s"); cache hits 96%, then 100%, 100%.
  - Concurrent ramp after an operator off and 16 idle seconds, 8 readers: turned on ("7.0 requests/s"); 2 of 1,780 responses were 503 busy pages while popular pages were cold; Apache error log empty, so concurrent checks did not collide.
  - Rendering stayed isolated: cached pages carry the `v3-cache-generation` meta written only by `HighTrafficCache::generate`, whose worker clears cookies and session; this stage did not touch that path.
  - `./v3 test --smoke HighTrafficAutomationTest HighTrafficTest RecentPostTest`: 469 run, 469 passed. Full suite not run.
- Notes:
  - An operator off's hold is released by the first quiet reading. After an idle period, the next spike can turn the mode on again, as Step 2 specified.
  - A failing change (for example a non-regular `.htaccess`) is logged at most once per second while traffic stays above the threshold.
  - Reused: `HighTrafficMode::change` (serializes under the publication lock, idempotent when on); `HighTrafficCache::report`.
- Estimate details: original scope as Step 3; uncertainty was medium; no revisions. Retrospective lower than original: the existing locks made concurrent triggers safe without extra work.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 4 - Keep still-fresh pages on turn-on

**Original estimate: 0.08**

**Revised: 0.10** — 2026-11-12, new understanding, same scope: removals detach serving without writing mode state, so pages had to be tied to the latest removal by time rather than by generation.

**Retrospective: 0.10**

- Changes:
  - `HighTrafficMode::keepFreshPages()` replaces clearing all pages on enable: a page is kept when it is younger than `keep_seconds` (default 3600; `FORUM_HIGH_TRAFFIC_KEEP_SECONDS`) and newer than the cache's `withdrawn` marker. After a withdrawal (marked, legacy, or detached while enabled) every page is cleared. Kept pages set the `dirty` marker, so the next cron run refreshes them.
  - `HighTrafficSafety::withdraw($publicRoot)`: marks each cache whose saved public root matches, then detaches. `StaticArtifactInvalidator`'s two removal paths call it instead of `detach`. `beforeWrite` withdrawals mark the cache too.
  - `HighTrafficCache` limits gain `keep_seconds`.
- Verification:
  - Unit test: a 2-minute page kept and a 62-minute page cleared, with `dirty` set; a removal while the mode was off clears pages rendered before it and keeps one rendered after; a feature-flag withdrawal clears everything; a detach while enabled clears everything.
  - Local Apache: five warm pages; operator off then on kept all five; `./v3 high-traffic run` re-rendered all five (`rendered: 5, failed: 0`).
  - `./v3 test --smoke` plus every test touching removals, the invalidator or the cache (BackupFeatureFlag, DeleteRecordCommand, DeleteThreadCommand, HighTraffic*, LegacyDocsMigration, LocalAppSmoke, ProfilePublicDetails, RecentPost, StandardActivityPagination, ThreadTitleWithdrawalAndConsumers, WebServerRouting, WriteApiSmoke): 736 run, 736 passed. Full suite not run.
  - `tests/high_traffic_apache_test.py` (run as a non-root user, the only way Apache starts here) fails at line 85 on unchanged `main` too: it expects `Retry-After: 5`, but busy pages now send 30. With that assertion relaxed in a temporary copy, branch and `main` both stop at the same later step, a content-import subprocess. One branch run failed earlier at the atomic-refresh check and two reruns did not, so that one was a timing flake. Both pre-existing failures are left for the maintainer; not fixed here (out of scope).
- Notes:
  - First version marked every cache on the host, which let a test mark the harness's cache; narrowed to caches serving the same public root.
  - Known limitation, unchanged from before: a render that starts before a removal and finishes after it gets an mtime after the marker. The existing publication validation in `HighTrafficCache::generate` remains the guard for that case.
- Estimate details: scope as Step 3; uncertainty was medium; revised once (above). Retrospective equals the revision.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 5 - Automatic turn-off from the access log

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `HighTrafficRefresh::recentCounts($log, $windowSeconds, $windows)`: counts page requests per window from combined-format timestamps in the same bounded tail the popularity reader uses; assets and refresh hints are not counted. It returns null when the tail does not reach back far enough (busy, or just rotated) and throws when lines have no recognizable timestamps.
  - `HighTrafficRefresh::automation($log)`: run by the cron job on every pass, whether or not the mode is on. It records `Cron-Checked-At` and `Cron-Log` in `automation.txt`. When **Turn off automatically** is on and the mode is on from an automatic turn-on, it turns the mode off once every five-minute window of the quiet period is below `turnOffRate()`. Defaults: 0.5 requests per second for 1800 seconds; overrides `FORUM_HIGH_TRAFFIC_AUTO_OFF_RATE` and `FORUM_HIGH_TRAFFIC_AUTO_OFF_SECONDS`.
  - `HighTrafficRefresh::run` calls it first; results appear as `Automation` and `Automation log` in every run's report.
  - `HighTrafficAutomation::set` refuses Turn-Off unless cron read a readable log in the last five minutes; `turnOffUnavailable()` gives the one-line reason, also in `status` as `turn_off_unavailable`.
- Verification:
  - Unit test: refused before cron has run; unreadable format reported; a readable log unlocks it; an operator on is never turned off; a busy five-minute window keeps the mode on; asset-only traffic counts as quiet and the mode turns off with source `automatic`; a log not reaching back 30 minutes keeps it on; with Turn-Off unset it stays on; a cron check older than five minutes makes the switch unavailable again.
  - Local Apache: the real combined-format access log was read (`Automation log: readable`) and `./v3 high-traffic auto-off on` then succeeded.
  - `./v3 test --smoke HighTrafficAutomationTest HighTrafficTest HighTrafficPostRefreshTest`: 467 run, 467 passed. Full suite not run.
  - Pending: parse a sample of the zenmemes.com access log; requested from the maintainer.
- Notes:
  - The quiet test is stateless: every five-minute window of the period must be quiet, so a single busy minute in the last 30 restarts the wait without stored timers.
  - Placeholder logs in existing tests (`[now]`) are now reported as unreadable; refresh behaviour is unchanged.
  - Reused: the bounded-tail limits (`read_bytes`, `lines`, `line_bytes`) and the existing cron command; no new cron line.
- Estimate details: original scope as Step 3; uncertainty was medium; no revisions. Retrospective equals original.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
