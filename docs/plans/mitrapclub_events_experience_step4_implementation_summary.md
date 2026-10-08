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
