# zenmemes home-page busy investigation — 2026-10-24

Scope: diagnosis of deployment `527a64c469f027d5df93843c41d431130d919871`; no application changes, production commands, configuration changes, canonical repair, deployment or dependency changes. This is a forensic report, not an implementation plan or Railspike approval. Local starting revision: `bce2c29e3`. Production inspected with two ordinary unauthenticated GET requests only. The user subsequently supplied production error-log excerpts. Runtime configuration, canonical records and last working deployment revision were unavailable.

## Findings

**Confirmed production cause: high-traffic publication rejects unavailable retraction state with `Cannot validate retraction state for publication.` Strongest reproduced underlying data condition: a retained retraction referring to an absent source without accepted local removal evidence. The specific production record still needs confirmation.**

At 2026-10-24 04:39 UTC:

| Request | Result | Evidence |
| --- | --- | --- |
| `https://zenmemes.com/` | HTTP 503, Meme Oven Is Busy | `Retry-After: 5`, `Cache-Control: no-store`; approximately 1.8 seconds including network |
| `https://zenmemes.com/?diagnostic=busy` | HTTP 200, rendered Board | `route-source: php-fallback`, 96,696 response bytes; approximately 1.3 seconds including network |

At the deployed revision, the high-traffic branch in `src/ForumRewrite/Host/FrontController.php:82–103` converts every generation exception (other than not-found), and every capacity-related null, into the same busy page with `Retry-After: 5`. The ordinary application execution-lock timeout branch does not add that header. Query strings are excluded by `HighTrafficRoute::canonical`, so they reach the working ordinary application. This strongly localizes the observed failure and means the message does not establish heavy traffic, an ongoing rebuild or resource exhaustion.

## Production log confirmation

User-supplied Apache/mod_fcgid excerpts from October 23 at 21:39:23, 21:39:38 and 21:39:42 explicitly report `High-traffic mode: Cannot validate retraction state for publication.` This confirms the precise home-page exception and makes renderer resource limits, page-size limits and slot exhaustion unnecessary explanations for those requests. The rejection occurs at `PublicationGuard::generation` before the renderer starts.

At 21:39:19 the same guard also causes an uncaught RuntimeException for `/offline/snapshot.sqlite3`: `FrontController::resolveOfflineSnapshotPath` → `OfflineSnapshotPublisher::publish` → `PublicOfflineSnapshotBuilder::build` → `PublicationGuard::begin` → `generation`. Offline bootstrap runs before the front controller's application try/catch, so it becomes a PHP fatal instead of a handled busy response. The shown `ExecutionLock` stack frames surround snapshot bootstrap; they do not indicate lock contention. This fatal is an application exception, not evidence of an out-of-memory kill.

The guard intentionally collapses missing target, unreadable/malformed evidence and trusted-removal validation failure into unavailable state. Thus the log confirms the failed check, but cannot independently name the offending record. The previously documented absent-target IDs remain the leading concrete lead. No further render attempt is needed to identify the exception.

## Reproduced causal chain

`HighTrafficCache::generate` (`src/ForumRewrite/Host/HighTrafficCache.php:92`) calls `PublicationGuard::generation` **before launching the child PHP renderer**. That calls `ExchangeRetractionState::read`. In deployed source, `ExchangeRetractionState.php:49` returns null if a valid retained retraction has no source post and no accepted delete evidence in `LabelRemoval::all`. `PublicationGuard` then throws `Cannot validate retraction state for publication.` The front controller logs `High-traffic mode: Cannot validate retraction state for publication.` and returns busy.

Consequently one unrelated missing target can prevent every uncached eligible route from being generated. This particular failure needs neither an execution lock holder nor excessive memory nor a slow renderer. Existing cached HTML is returned before validation, so an old warm cache can conceal it until cache clearing, re-enabling mode, moving the canonical repository/cache namespace, or another withdrawal exposes a cold miss. Failed generation leaves the page absent; every subsequent request retries and fails. No automatic fallback to ordinary rendering occurs.

### Exact-revision experiments

Exported project-owned source/assets/templates and the existing minimal canonical fixture into isolated `/tmp` directories with `git archive`; no dependencies were installed. Copied fixture records (not production records), built a disposable read model, enabled a fixture cache through local test state/symlink, and first generated `/` successfully. Removed that cache page, added a valid retraction for absent ID `target`, then repeated cache generation and both front-controller routes. No execution lock was held.

| Code revision | Clean cache | Absent-target cache | `/` with absent target | `/?diagnostic=busy` |
| --- | --- | --- | --- | --- |
| `527a64c46` deployed | success, 33,603 bytes | throws validation error | 503 busy | 200 |
| `527a64c46^1` (`ee0f5b173`) | success, 33,463 bytes | same validation error | 503 busy | 200 |
| `1b0622957^` (before high-traffic publication fencing) | success, 30,442 bytes | succeeds | 200 | 200 |

The third row is causal evidence, **not a recommendation to remove publication protections or deploy that old revision**. The identical absent-target input fails after fencing. The deployed merge's first parent already fails, so reverting just the Markdown/task-list merge would not fix this reproduced cause.

Local reproducer: `/tmp/zenmemes-busy-527a64c-f1ggd5sf/reproduce.php`. Comparison exports: `/tmp/zenmemes-busy-compare-x2bd_uq_` and `/tmp/zenmemes-busy-compare-y9p06qtq`. These paths are disposable, local to this workspace and may disappear. Reproducer outputs are captured in the table above.

## Relevant recent changes

1. `98c3dd47c` (2026-10-19): introduced shared publication guarding and rejection of unavailable retraction evidence.
2. `1b0622957` (2026-10-21), merged with high-traffic mode in `2e392f0af`: wired those guards into high-traffic cache generation. This is the demonstrated behavioral boundary for the fixture.
3. `4e4f3b8e7` (2026-10-22): added source-removal generation validation at publication. A second deterministic failure is `Read model needs source-removal refresh before publication.` if metadata and accepted removal evidence disagree.
4. `864b9a873` (2026-10-22): allows absent retraction targets when locally accepted delete evidence exists; absence without that evidence remains a hard failure. Simply deleting canonical posts in Git is insufficient to satisfy the guard.
5. `98ff5de18` and `06227ab5a`: recent schema-version changes (deployed version 25) can initiate a synchronous rebuild on normal application startup. This can matter on a constrained host, but the live ordinary home page worked during this investigation. It is a lower-ranked explanation for the observed persistent cache-only failure.
6. `527a64c46`: first-parent changes include task-list rendering, reply-continuation assets and other UI/documentation work. `HighTrafficCache`, `FrontController` and the strict retraction guard were already present. The task-list renderer is for documentation, not this ordinary board page. Its numerous checked-in audit artifacts do not by themselves prove per-home-page memory consumption. A clean fixture renders successfully at this exact revision.

The prior deployment is unknown, so this establishes a candidate change boundary, not which commits the production upgrade newly introduced.

## Existing incident evidence and newer code

[Absent-target assessment](absent_retraction_snapshot/absent_retraction_snapshot_step1_solution_assessment.md) records task 952 failing on retained retractions for `reply-20260526032118-654b2b08` and `reply-20260526032816-a1447d49`, whose sources had committed deletions. This is independently documented matching data shape, but those records were not inspected on production in this investigation.

The newer absent-target work on this checkout introduces snapshot-only exclusions. It intentionally retains strict `ExchangeRetractionState::read` and strict `PublicationGuard::generation` for high-traffic/static callers. `HighTrafficCache.php` is byte-identical between the deployed revision and this checkout. **That newer offline-snapshot fix alone does not fix high-traffic cache generation for this condition.** Existing high-traffic tests cover clean fixtures, slots, page/byte limits and ordinary rendering, but do not exercise an absent-target retraction through the busy-page flow.

## Other candidates and discriminating evidence

| Candidate | Expected evidence | Assessment |
| --- | --- | --- |
| Absent/malformed/unreadable retraction or untrusted removal evidence | `High-traffic mode: Cannot validate retraction state for publication.` | Leading candidate; exact symptom reproduced and matching historical incident documented. The message alone does not distinguish all invalid-evidence conditions. |
| Stale removal/retraction read model | `Read model needs source-removal refresh before publication.` or `Read model needs retraction refresh before publication.` | Recent additional publication gates; inspect metadata and canonical evidence before selecting recovery. |
| Child PHP failure, disabled `proc_open`, wrong CLI executable/environment, missing extension, memory limit or host kill | `Cannot start shared renderer.`, `Shared render failed: ...`, PHP fatal or host process logs | Possible on shared hosting; not established by the Codex process being killed. The leading reproduced failure occurs before this subprocess. |
| Slow rebuilding/rendering or excessive output | `Shared render exceeded limits.` | Default child limit 30 seconds, HTML limit 1 MiB, stderr limit 64 KiB. Current live dynamic page is only about 97 KiB; this does not rule out worker-specific failures. |
| Exhausted page/byte capacity or slots | capacity error, or generation returns null without a logged exception | Defaults: 1,000 pages, 256 MiB including accumulated fingerprinted assets, two concurrent generators, 250 ms page-lock wait. Old assets accumulate across deployments; a plausible upgrade-related alternate. Persistent slot exhaustion requires live holders, not merely leftover lock files. |
| Cache filesystem permissions, disk/quota, broken publication state | `Cannot create...`, `Cannot replace cache file.`, `Mode changed during generation.`, state-busy error | Cold generation requires writable state, temporary assets and publication paths, even while ordinary PHP rendering works. |

Two recovery traps: purging a cache cannot repair invalid canonical evidence and can remove the remaining usable pages; restarting PHP or deleting an old unlocked lock file cannot fix a deterministic validation error. Raising execution-lock timeout also does not address it. The refresh worker ranks only 2xx/3xx access-log entries, so a cold route seen exclusively as 503 may not get selected automatically after repair; explicitly refresh it once capacity/evidence is healthy.

## Bounded record confirmation and recovery handoff

No production shell actions were taken. The supplied logs already identify the failed guard; a maintainer can inspect its canonical inputs without running Codex there. The following are diagnostic/recovery options, not actions performed here:

- Inspect only the last approximately 200 lines of the configured PHP/Apache error log and the private high-traffic cron log for `High-traffic mode:`. Do not send secrets or a full log dump. The user has now supplied that exact validation error; further log collection is optional.
- Run `./v3 high-traffic status --json` using the web app's normal user/environment. This inspects routing/cache status; it does not prove rendering works. Inspect that cache root's `refresh-status.json` for failures.
- If logs are unavailable, `./v3 high-traffic refresh / --json` makes one bounded application-level generation attempt and prints its exception. This **writes the cache on success** and may launch a renderer; it is an operator diagnostic option, not a read-only check, and was not executed on production.
- For service restoration, the documented `./v3 high-traffic disable` switches to the ordinary serving path that succeeded in the public probe. This is a production configuration change, was not executed here, and increases dynamic PHP traffic. Use the same deployed repository/database/public-root configuration as the web app; `restore` is the documented fallback for damaged managed routing.
- If the absent-target cause is confirmed, choose a scoped fix for high-traffic publication or the supported trusted-source-removal repair process. Do not delete retraction records, recreate removed prose, or bypass the guard as an ad hoc repair. After correction, explicitly refresh `/` and verify both a successful cold generation and an Apache cache hit, plus surviving-content/retraction/privacy behavior.

No application patch or deployment is included. Dependency delta: zero. `todo.txt` unchanged.

## Production-agent report and immediate recovery

The user supplied [the production agent’s report](https://zenmemes.com/threads/thread-20261024044151-755007ca), retrieved through the working query-string route. It confirms all three retraction records parse, both named reply sources are absent at every supported path, and accepted removals are empty. High-traffic enablement at 2026-10-23T18:08:28Z was followed by the first prefixed validation error two seconds later; the cache contained only `.htaccess`. The report also found no high-traffic refresh cron job. These are supplied production findings, not local shell observations.

The user then confirmed disabling high-traffic mode restored the home page, with slower ordinary rendering. Production remains in that recovery state. The requested lasting fix is tracked in [the follow-up assessment](high_traffic_absent_retractions/high_traffic_absent_retractions_step1_solution_assessment.md); no production re-enable, canonical repair or deployment has been performed here.
