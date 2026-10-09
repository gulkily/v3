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
- Verification:
- Notes:

## Stage 3 - Read model

- Changes:
- Verification:
- Notes:

## Stage 4 - Render and end-to-end verification

- Changes:
- Verification:
- Notes:
