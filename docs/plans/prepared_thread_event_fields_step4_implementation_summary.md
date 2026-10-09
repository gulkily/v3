> **Feature plan:** [Step 2](./prepared_thread_event_fields_step2_feature_description.md) · [Step 3](./prepared_thread_event_fields_step3_development_plan.md) · [Step 4](./prepared_thread_event_fields_step4_implementation_summary.md)

## Stage 1 - Server-side fix: prepareThread() validates and forwards event fields

- Changes:
  - `src/ForumRewrite/Write/LocalWriteService.php`, `prepareThread()`:
    - Added `assertEventSupportAllowsInput($input)` at the top, matching `createThread()`.
    - Added normalization of `event_date`/`event_location`/`event_link`/`event_time` from `$input`, using the exact same validators `createThread()` already uses.
    - Forwards all four into `buildThreadPostRecord()`.
  - `tests/WriteApiSmokeTest.php`: added three new tests —
    - `testPrepareThreadIncludesEventFieldsInCanonicalRecord`: `/api/prepare_thread` with all four event fields and event support enabled; asserts the returned `canonical_record` contains all four `Event-*:` headers with the submitted values.
    - `testPrepareThreadRejectsEventFieldsWhenEventSupportDisabled`: direct `LocalWriteService::prepareThread()` call with event support disabled; asserts it throws the identical `RuntimeException('Event support is disabled for this site.')` `createThread()` already throws.
    - `testPrepareThreadRejectsMalformedEventTimeBeforeIssuingToken`: `/api/prepare_thread` with a malformed `event_time` (`99:99`); asserts an error JSON response and that the prepared-posts directory's file count is unchanged (no token issued).
- Verification:
  - Ran the three new tests individually: all pass.
  - `php tests/run.php` full suite: 953 run (3 new), 949 passed, same 4 pre-existing failures (`LocalAppSmokeTest::testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`, `QuoteCardDisplayNumberTest::testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews`, `WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce`, `WriteApiSmokeTest::testQdbPermalinkShowsViewersExistingUpvoteAsPressedAndDisabled`), no new failures.
- Notes:
  - Initial draft of the two negative-case tests asserted the prepared-posts directory was empty via `glob(...) === []`, which failed immediately — that directory isn't scoped per test's temp environment and already held hundreds of files from prior test runs. Fixed by following the existing codebase convention instead: check a specific token file's presence/absence, or a before/after file-count comparison, never an absolute empty-directory assertion.

## Stage 2 - Client-side fix and end-to-end verification

- Changes:
- Verification:
- Notes:
