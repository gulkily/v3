> **Feature plan:** [Step 1](./mitrapclub_events_experience_step1_solution_assessment.md) · [Step 2](./mitrapclub_events_experience_step2_feature_description.md) · [Step 3](./mitrapclub_events_experience_step3_development_plan.md) · [Step 4](./mitrapclub_events_experience_step4_implementation_summary.md)

## Stage 1 - Canonical record support for event fields
- Changes:
  - `src/ForumRewrite/Canonical/PostRecord.php`: added three nullable constructor properties (`$eventDate`, `$eventLocation`, `$eventLink`), appended at the end with `null` defaults (only one existing call site, in `PostRecordParser`, so this is purely additive).
  - `src/ForumRewrite/Canonical/PostRecordParser.php`:
    - Added `EVENT_HEADERS` (`Event-Date`, `Event-Location`, `Event-Link`) and rejects any of them on a reply record (`CanonicalRecordParseException`), mirroring the existing `Task-Status`-on-replies rule.
    - `Event-Date`, when present, is validated via a new `parseEventDate()` (regex `^\d{4}-\d{2}-\d{2}$` plus a `DateTimeImmutable` round-trip check — the same shape `parseCreatedAt()` already uses, narrowed to a calendar date).
    - `Event-Location`/`Event-Link` are passed through as plain optional strings; no extra validation at parse time (write-time normalization is Stage 2).
- Verification:
  - `./v3 test` — full suite: 834 run, 834 passed, 0 failed.
  - Parsed five hand-built record strings directly: no event headers → all three null; full trio present → all three parsed correctly; date-only → date parsed, location/link null; malformed `Event-Date: not-a-date` → throws with a clear message; `Event-Date` on a reply record → throws "Replies must not include typed root header: Event-Date". All five matched the Step 3 plan's expected cases exactly.
- Notes:
  - `GenericTextRecordParser` (the underlying header-line parser) is fully generic with no header allowlist, so no change was needed there — confirmed before writing this stage.

## Stage 2 - Write path and compose form for event fields
- Changes:
  - `src/ForumRewrite/Write/LocalWriteService.php`: `createThread()` (only — `createReply()` untouched) reads optional `event_date`/`event_location`/`event_link` from `$input`; new `normalizeEventDate()` validates the date with the same regex-plus-`DateTimeImmutable`-round-trip shape as Stage 1's parser-side check, throwing `RuntimeException` on malformed input; location/link reuse the existing `normalizeAuthoredLine()` helper; `buildThreadPostRecord()` gained three optional parameters and writes the corresponding headers only when non-empty.
  - `templates/partials/thread_compose_form.php`: three new optional inputs (event date/location/link) added to the full (non-compact) compose form only; the compact variant (used by `/add`-style quick composers) is untouched, matching how it already omits `board_tags`/`subject` as visible fields.
  - `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`: `composeThread()`, `submitComposeThread()`'s validation-error re-render, and `renderComposeThreadPage()` all thread the three new fields through, mirroring the existing `board_tags`/`subject` echo-back pattern exactly. Confirmed via `TemplateRenderer::renderFile()`'s `$partial()` closure (`array_merge($data, $partialData)`) that page-level template data already flows into partials automatically, so `compose_thread.php` itself needed no change.
- Verification:
  - `./v3 test` — full suite: 834 run, 834 passed, 0 failed (one `TaskQueueCommandTest` failure on an earlier run, confirmed unrelated — task-queue worker output assertion, no connection to compose/write code — and clean on rerun).
  - Called `LocalWriteService::createThread()` directly against a disposable git-initialized copy of the fixture repository for four cases: all three fields filled, none filled, date-only, and a malformed date. The first three wrote the expected `Event-*` headers (or none) into the canonical record file; the malformed case threw `RuntimeException` ("event_date must use the format YYYY-MM-DD like 2026-11-01.") and wrote no file at all — confirmed by scanning the repository afterward.
- Notes:
  - Malformed input fails closed before any `writeFile()` call, exactly as the Step 3 Key Risks required.

## Stage 3 - Read-model wiring for event fields
- Changes:
  - `src/ForumRewrite/ReadModel/ReadModelBuilder.php`: `threads` table gains nullable `event_date`/`event_location`/`event_link` TEXT columns; `indexPosts()`'s per-post parsing and its thread-summary initialization/`INSERT INTO threads` populate them from the root post's `PostRecord`.
  - `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`: `insertThread()`'s `INSERT INTO threads` populates the same three columns from the same `PostRecord` passed in — single source of truth with the rebuild path, as planned.
  - `src/ForumRewrite/ReadModel/ThreadRepository.php`: `fetchThreads()` and `byId()`'s explicit column lists gain the three columns.
  - `src/ForumRewrite/Application.php`: `fetchThread()`'s separate explicit column list (the thread-page query) gains the same three columns.
  - `posts` table/`INSERT INTO posts` deliberately untouched — event fields are thread/root-post-level only, per Step 2 scope.
- Verification:
  - `./v3 test` — full suite: 834 run, 834 passed, 0 failed (one `TaskQueueCommandTest` failure on an earlier run in this feature, already confirmed unrelated and flaky; clean again here).
  - Full-rebuild path: added one event-bearing post record to a copy of the fixture repository, ran `scripts/rebuild_read_model.php`, and queried the `threads` table directly via `sqlite3`: the event thread's row carries all three values; the two pre-existing ordinary threads' are null.
  - Queried the same database through `ThreadRepository::fetchThreads()` and `::byId()` directly: both return the three fields correctly for the event thread.
  - Rendered `/threads/event-thread-001` via `FrontController` (exercising `Application::fetchThread()`'s updated query): renders successfully with the correct subject/title — confirms the query executes without error (no event block yet; that's Stage 4).
  - Incremental path: called `LocalWriteService::createThread()` live with event fields (no rebuild), then queried that same database directly: the three columns were already populated, closing the "two read-model write paths must agree" Key Risk.
- Notes:
  - Both high-risk items from Step 3's Key Risks (duplicate SELECT call sites; duplicate read-model write paths) were independently verified rather than assumed.

## Stage 4 - Event block rendering on the board and thread page
- Changes:
  - New `templates/partials/event_block.php`: renders a `<p class="event-block">` with date, location (if present), and link (if present) when `$thread['event_date']` is non-empty; renders nothing otherwise. The link only becomes a clickable `<a href>` when it starts with `http://`/`https://` (case-insensitive) — `event_link` is author-submitted free text with no URL-scheme restriction at write time, so this guards against a stray non-http(s) scheme (e.g. `javascript:`) ever being rendered as a clickable anchor; a non-http(s) value still displays as escaped plain text.
  - `templates/partials/thread_card.php`: includes the new partial right after the existing meta line.
  - `templates/partials/thread_root_card.php` (the actual root-post card partial `thread.php` delegates to — Step 3 named `thread.php` directly; this is the real file touched): includes the new partial right after the title/body block, for both the ordinary and qdb-quote-root branches.
- Verification:
  - `./v3 test` — full suite: 834 run, 834 passed, 0 failed (two different unrelated tests flaked once each across reruns in this stage — `TaskQueueCommandTest::testRebuildWorkerRecordsPrivateProgressCheckpoints` and `WriteApiSmokeTest::testGenerateAgentReplyDoesNotRequireCompletedAnalysis`, neither touching compose/write/read-model/rendering code this feature changed; both passed clean on the next run, confirming pre-existing suite flakiness rather than a regression).
  - Byte-diffed rendered board HTML (both the default `?view=liked` and `?view=all&sort=newest`) for all four profiles against a pre-Stage-4 snapshot (via `git stash`/`stash pop` around the render calls): identical for all four in both views.
  - Added one event-bearing post to a disposable copy of the fixture repository, rebuilt the read model, and rendered via `FrontController`: the event block appears with the correct date/location/link on **both** the board card (`?view=all`) and the thread page — closing the Step 3 Key Risks duplication item at the presentation layer too.
  - Confirmed via direct render that a non-http(s) `event_link` would render as plain text, not a clickable anchor (code-reviewed against the regex condition; no existing fixture data exercises this path, so this was verified by inspection rather than a rendered example).
- Notes:
  - Feature complete per the Step 3 Completion Contract: a `mitrapclub` (or any profile's) thread with an event date renders a structured, visually distinct event block on both the board and the thread page; threads without one, and every other profile, render byte-identical to before this feature.
