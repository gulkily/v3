> **Feature plan:** [Step 1](./tour_snapshots_step1_solution_assessment.md) · [Step 2](./tour_snapshots_step2_feature_description.md) · [Step 3](./tour_snapshots_step3_development_plan.md) · [Step 4](./tour_snapshots_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/tour_snapshots`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.6** (Step 3). Retrospective scope assessment: **0.75**.

## Outcome

`/assets/tour/` is a long page, one feature per row: an essay (79–113 words) beside a working, logged-out HTML snapshot of the page. A visit loads only static files: in a dev-server visit the only PHP requests were the snapshot file reads (no render) and the site's offline-bootstrap favicon. Snapshots are refreshed by `./v3 tour refresh`, and start on their own after deploys, rebuilds, retraction sweeps and the high-traffic cron; operators can press **Refresh now** in Tools → Operations. The old `/assets/tour.html` redirects. Still unlinked and `noindex`.

## Stages

| Stage | Commit | Stage work (Step 3) | Notes |
| --- | --- | --- | --- |
| 1 Render routes logged-out | `be94c6fc` | 0.08 | Risk confirmed: the high-traffic renderer only accepts `/`, `/threads/`, `/about/` and threads. Split `renderPublicRoute()` out of `StaticArtifactBuilder::renderHighTrafficRoute()`; added `renderTourRoute()` (own allowlist `TourRoute`, no refresh-hint script) and `SharedRenderWorker` behind both worker scripts. High-traffic output unchanged (`HighTrafficTest` passes). |
| 2 Essays and page | `07add769` | 0.12 | `resources/tour.txt` (`Schema: tour-v1`), `TourFile` parser with line-numbered errors, `templates/tour/page.php`. |
| 3 Command | `5baba959` | 0.12 | `./v3 tour refresh|status`, atomic writes, lock, Key: value status, redirect page, `.gitignore`, front-controller path for `/assets/tour/`. |
| 4 Starts on its own | `0f69782b` | 0.1 | Detached `TourRefreshLauncher`; deploy check, rebuild completion, high-traffic cron; hourly retry of failures. |
| 5 Operations row | `76f7fabf` | 0.08 | Row + Refresh now; narrow-screen layout for button rows. |
| 6 Retraction and docs | `21aca99d` | 0.06 | Tour folder added as a sweep root; sweep starts a refresh; reference and concept docs. |
| Fix | `b2688221` | — | Instance-path flag was not in the task worker closure's scope (PHP warning in five `TaskQueueCommandTest` tests); found by the final run. |

## Deviations from the approved plan

- **Essay file location:** `resources/tour.txt`, not `content/tour.txt`. The repo has no `content/` folder; `resources/tagsets.txt` is the existing precedent for a plain-text source with a `Schema:` line.
- **Feature list:** Unread dropped (it answers 401 to logged-out visitors, so it cannot be a snapshot, as Step 3 Stage 1 anticipated); Recent restored in its place. Final nine: Board, Start here thread, Built with agents (Prompts), Recent, Gallery, Calendar, Tags, Your key, Backup.
- **Background work:** a detached `./v3 tour refresh` process instead of a new task-queue type. The queue only runs where a task worker is running (the emergency launch is off by default); the tour's own lock already gives "one at a time". Step 2's requirement (never blocks a page load, one at a time) is met.
- **Serving:** not in the plan. Production's Apache rule lets only `/assets/` itself bypass PHP, and PHP's raw-asset path serves only files directly in `/assets/`, so `/assets/tour/…` would have 404'd in production. Added `FrontController::resolveTourSnapshotPath()` (file read; off on members-only instances).
- **Additional triggers:** rebuild completion and retraction sweep also start a refresh when due, so a retired or stale tour comes back without a button press.
- **Auto-start only for the instance's own paths:** `task_queue.php` skips the tour when run with `--repository-root`/`--database-path` overrides (a refresh renders from the instance's paths; also keeps tests from starting real refreshes).

## Shared components reused

`HighTrafficCache::render()` (new optional worker argument), `HighTrafficPublication`, `HighTrafficRenderInput`, `StaticArtifactBuilder`, `ArtifactSweep`/`RetractionSweepFactory`, `FrontController::sendAsset()`, `SameOriginRequest` and `RouteServices::securityReviewAllowed()` (as Deploy now), Operations row markup and `tool-details.css`, `$relativeTimestamp`, `DeploymentSettings`, `PhpCli::binary()`.

## Verification

- Tests (new `TourSnapshotsTest`, 10 tests): route allowlist; worker renders `/agent/`, `/calendar`, `/account/key/`, `/backup/` logged-out without the refresh hint, refuses `/unread` and `/tools/`; parser accepts the shipped file and explains eight kinds of mistakes; page has one row per section, no `_blank`/`window.open`/`loading="lazy"`, `noindex`; snapshots get `noindex` and `<base target="_top">`; refresh writes files, keeps the previous snapshot on failure, leaves out a missing page, refuses a concurrent run, and reports status; launcher starts only when due and retries failures after an hour; Operations POST refused for signed-out, non-operator and cross-site, operator sees "already running" while a refresh holds the lock, button shown only to operators; sweep retires tour snapshots older than the cutoff and keeps other assets.
- `LocalAppSmokeTest::testFrontControllerServesTourSnapshotsFromTheirFolder` (PHP path, traversal, missing file, uppercase name).
- Smoke set plus `TourSnapshotsTest HighTrafficTest DeployPageTest RetractionMaintenanceTest TaskQueueCommandTest`: 507 run, 507 passed. Full suite not run.
- Local instance: `./v3 tour refresh` (7 shown, 2 left out: the ZenMemes thread and Gallery are absent from local data); `./v3 task-queue check-read-model` printed "Tour: started" then "Read model: current", and a refresh finished within seconds; a second check printed "Tour: current".
- Headless Chrome: tour page at 1280 px and 390 px (toggles on one line on phones); Operations row in Gloss, Gloss Dark and Chicago at 390 px and Gloss at 1100 px.
- Dev-server request log for one tour visit: snapshot files and a favicon only.

## Not verified / limitations

- Production: Apache path, timings on the real host, and the post-deploy start. First deploy: the deploy check starts the first refresh; until it finishes `/assets/tour/` is 404.
- Gallery and the Start here thread were not rendered locally (absent from local data); they render through the same path as the routes that were.
- Snapshots reference fingerprinted assets; after a deploy that changes them, snapshots can look unstyled until the deploy-started refresh finishes.
- Not tested on a real iPhone; the Look-mode cover is carried over from #256.
- Observation outside scope: the `.htaccess` condition `^/(?:assets(?:/|$)|favicon\.ico)$` only matches `/assets` and `/assets/` themselves, so every asset goes through PHP in production. Not changed here.

## Release

Deploy, then open `/assets/tour/` on the phone after a minute (or press Tools → Operations → Refresh now). Edit essays in `resources/tour.txt` in a PR, or on the server followed by Refresh now.

## Effort study

Step 3 projected 0.6; retrospective scope assessment 0.75: the production serving path, extra triggers and the scope fix were not in the plan. Not measured effort.

Debug log: [tour_snapshots_debug_log.md](./tour_snapshots_debug_log.md)

## Publication

Branch `feat/tour_snapshots` pushed; PR #264 open. Not merged or deployed.
