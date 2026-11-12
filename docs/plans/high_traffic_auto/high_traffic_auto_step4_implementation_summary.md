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
