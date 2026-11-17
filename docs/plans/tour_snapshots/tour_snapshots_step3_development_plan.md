> **Feature plan:** [Step 1](./tour_snapshots_step1_solution_assessment.md) · [Step 2](./tour_snapshots_step2_feature_description.md) · [Step 3](./tour_snapshots_step3_development_plan.md) · [Step 4](./tour_snapshots_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/tour_snapshots`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.6**

Step 2 approved 2026-11-15 ("Approved step 2."), including its three decisions to confirm: essays drafted during implementation for edits in the PR; Unread replaces Recent and Users, Compose and API pages drop out; frame sizes unchanged.

## Stages

### Stage 1 — Prove each route renders as a logged-out snapshot

**Stage work: 0.08**

- Goal: settle the main risk before building on it.
- Dependencies: none.
- Changes: a small `TourSnapshots` class that, for a route, calls the isolated renderer behind `HighTrafficCache::render()` (`scripts/high_traffic_render.php`) with no viewer context and returns HTML. No files written yet.
- Verification: a test renders each of the 9 routes (`/`, Start here thread, `/agent/`, `/unread/` or its route, `/gallery/`, `/calendar`, `/tags/`, `/account/key/`, `/backup/`) against the fixture repository; each returns HTML with its page heading and no session username markup. Any route that cannot render through this path is recorded here and dropped from `content/tour.txt` or rendered via the ordinary page renderer, decided before Stage 2.
- Risks: Unread and Your key may be viewer-only pages that render as empty or gated for a logged-out visitor; that is acceptable if the logged-out view still shows the feature (the key page's "Pick a name" panel does).

### Stage 2 — `content/tour.txt` and the generated tour page

**Stage work: 0.12**

- Goal: one plain-text source builds the whole page.
- Dependencies: Stage 1 (final route list).
- Changes: `content/tour.txt` in `Key: value` sections (`Name:`, `Route:`, `Title:`, blank line, essay body, `---` between sections) with drafted essays; a parser with clear errors (line number, missing key); a page template carrying over the #256 page (pinned toggles, Look cover, same-tab links, `noindex`, no intro) and changing the layout to one row per feature: essay beside the frame on desktop, above it on phone; frames `src` to `/assets/tour/<name>.html`; first two frames load immediately, the rest from an IntersectionObserver a screen ahead.
- Verification: parser tests (valid file, missing key, unknown key, duplicate name); page render test (one row per section, no `_blank`/`window.open`, `noindex`); headless Chrome at 390 px and 1280 px.

### Stage 3 — `./v3 tour refresh` writes snapshots atomically

**Stage work: 0.12**

- Goal: the command does the whole job from a terminal.
- Dependencies: Stages 1–2.
- Changes: `./v3 tour refresh` and `./v3 tour status` (readable by default, `--json` for automation, per CLI convention). Refresh renders each route, adds `noindex` and `<base target="_top">`, and writes `public/assets/tour/<name>.html` and `index.html` via `HighTrafficCache::atomic()`-style temp-and-rename; holds a lock so only one refresh runs; a route that fails keeps its previous file and is reported; writes a small `Key: value` status file (last started, last finished, per-route result). `public/assets/tour/` is git-ignored. `public/assets/tour.html` becomes a redirect page to `/assets/tour/`. CLI reference updated in `docs/reference/v3_cli.md`.
- Verification: command test on fixtures (files appear, previous file kept on a forced render failure, second concurrent run reports "already running", status file contents); smoke set.

### Stage 4 — Background task, Deploy now hook, cron check

**Stage work: 0.1**

- Goal: snapshots appear without a terminal.
- Dependencies: Stage 3.
- Changes: new task type `refresh_tour_snapshots` in `SqliteTaskQueueStore` and `TaskQueueWorker` (same pattern as `PUBLISH_OFFLINE_SNAPSHOT`, enqueued like `RouteServices::enqueueOfflineSnapshotPublication()`, deduplicated by key so only one is queued); after a successful deploy (`AppUpdate` deploy path used by Deploy now and the queued deploy), enqueue it when any snapshot is missing or older than the deployed code; `./v3 high-traffic run` makes the same check where its cron is installed.
- Verification: tests that a deploy with missing snapshots enqueues exactly one task, a deploy with fresh snapshots enqueues none, and the worker runs the task; Deploy now's response time unchanged (it only enqueues).

### Stage 5 — Tools → Operations row

**Stage work: 0.08**

- Goal: refresh from the browser.
- Dependencies: Stage 4.
- Changes: a "Tour snapshots" row in `templates/pages/operations.php` reusing its row markup: last refreshed (relative time), state (queued / running / last error), and **Refresh now** (root operators only, POST with the same-origin check Operations already uses) that enqueues the task. Removing text over adding it: no explanation paragraph.
- Verification: controller tests (root sees and can press; non-root sees nothing or a disabled row per existing Operations rules; second press while queued says so); visual check in Gloss, Gloss Dark and one pixel theme at 390 px. No nav destination added, so no nav icon change.

### Stage 6 — Retraction coverage and docs

**Stage work: 0.06**

- Goal: retracted content never lingers in the tour.
- Dependencies: Stage 3.
- Changes: include `public/assets/tour/` in the artifact roots `RetractionSweepFactory` passes to `ArtifactSweep` (and its family filter) so snapshot files older than an accepted retraction are retired; the next refresh regenerates them. Reference page `docs/reference/tour_snapshots.md` (the *tour snapshot* concept, refresh triggers, retraction behaviour); add to the reference index.
- Verification: sweep test retires a tour snapshot older than a retraction record and keeps `index.html` only if it predates nothing; smoke set plus the touched tests. Full suite not run.

## Completion

All Step 2 completion boundary items: zero PHP renders per tour visit (access log check on dev server), snapshots appear after Deploy now with no terminal, Refresh now works and reports status, retraction test passes, phone scroll smooth in Look mode, nothing opens a new tab. Release check (production, by the maintainer): deploy, open `/assets/tour/` on the phone.

## Out of scope

Linking the tour anywhere; logged-in snapshots; per-instance tour lists beyond editing `content/tour.txt`; a schedule beyond deploy/cron/button.

Debug log: [tour_snapshots_debug_log.md](./tour_snapshots_debug_log.md)

## Approval

2026-11-15 15:38 ET, maintainer: "approved step 3".
