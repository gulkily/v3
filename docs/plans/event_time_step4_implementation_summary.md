> **Feature plan:** [Step 1](./event_time_step1_solution_assessment.md) · [Step 2](./event_time_step2_feature_description.md) · [Step 3](./event_time_step3_development_plan.md) · [Step 4](./event_time_step4_implementation_summary.md)

## Stage 1 - Canonical record type and parser accept Event-Time

- Changes:
  - `src/ForumRewrite/Canonical/PostRecord.php`: added one trailing nullable constructor property, `?string $eventTime = null`, after `$eventLink`.
  - `src/ForumRewrite/Canonical/PostRecordParser.php`:
    - Added `Event-Time` to `EVENT_HEADERS` (so it's rejected on replies, same as the other three event headers).
    - Added `parseEventTime()`, mirroring `parseEventDate()`'s shape: strict `^\d{2}:\d{2}$` regex, then a `DateTimeImmutable::createFromFormat('!H:i', ...)` round-trip check.
    - `parse()` extracts `Event-Time` when present and passes it as the new trailing argument to `PostRecord`.
- Verification:
  - `php tests/run.php` full suite: 946/950 passed, same 4 pre-existing failures as before this change (`LocalAppSmokeTest::testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`, `QuoteCardDisplayNumberTest::testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews`, `WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce`, `WriteApiSmokeTest::testQdbPermalinkShowsViewersExistingUpvoteAsPressedAndDisabled`), no new failures.
  - Hand-built record parse cases, run directly against `PostRecordParser`:
    - No event headers at all → `eventDate`/`eventTime` both `null`.
    - `Event-Date: 2026-11-14` + `Event-Time: 19:00` → both parsed correctly (`eventTime === '19:00'`).
    - `Event-Time: 25:99` (malformed) → threw `CanonicalRecordParseException: Event-Time must be a valid 24-hour time.`
    - `Event-Time: 19:00` on a reply record (has `Thread-ID`/`Parent-ID`) → threw `CanonicalRecordParseException: Replies must not include typed root header: Event-Time`.
- Notes:
  - All four cases behaved exactly as planned; no surprises in this stage.

## Stage 2 - Write-path validation and compose form

- Changes:
  - `src/ForumRewrite/Write/LocalWriteService.php`:
    - Added `normalizeEventTime()`, mirroring `normalizeEventDate()` (optional, strict `HH:MM` regex + `DateTimeImmutable::createFromFormat('!H:i', ...)` round-trip).
    - `assertEventSupportAllowsInput()` now also gates `event_time` (not just `event_date`/`event_location`/`event_link`) when event support is disabled.
    - `createThread()` reads, normalizes, and forwards `event_time` to `buildThreadPostRecord()`.
    - `buildThreadPostRecord()` emits `Event-Time:` immediately after `Event-Date:`, only when non-empty.
  - `templates/partials/thread_compose_form.php`: swapped the event-date input from `type="text"` to native `type="date"` (dropped the now-redundant placeholder); added a new native `type="time"` input for `event_time`, both optional.
  - `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`: threaded `event_time` through `composeThread()` (GET-prefill), `submitComposeThread()`'s validation-error re-render, and `renderComposeThreadPage()` (signature + `eventTime` template var) — identical shape to the existing `event_date` handling at each site.
- Verification:
  - `php tests/run.php` full suite: 946/950 passed, same 4 pre-existing failures, no new ones.
  - Direct `LocalWriteService::createThread()` calls against a scratch repository: date+time both filled → wrote a record with both headers; date-only → wrote a record with just `Event-Date`; malformed time (`99:99`) → threw `RuntimeException: event_time must be a valid 24-hour time.` and confirmed (via a repository file scan) zero files were written for that attempt.
  - Started the dev server and fetched `/compose/thread?event_date=2026-11-14&event_time=19:00`: confirmed the rendered page's native `type="date"`/`type="time"` inputs both carry the correct prefilled values.
- Notes:
  - Caught and fixed one gap vs. the Step 3 plan while implementing: `assertEventSupportAllowsInput()` only checked the original three fields, so `event_time` alone would have bypassed the event-support gate when the flag is off. Added it to that check in this stage rather than deferring, since it's part of making Stage 2's gate correct, not separate scope.

## Stage 3 - Read model

- Changes:
- Verification:
- Notes:

## Stage 4 - Render and end-to-end verification

- Changes:
- Verification:
- Notes:
