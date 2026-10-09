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
  - `public/assets/browser_signing.js`, `collectThreadSubmitFields()`: added `event_date`, `event_time`, `event_location`, `event_link`, using the existing `composeFormFieldValue()` helper exactly as the other fields already do.
  - `tests/BrowserSigningNormalizationTest.php`:
    - Updated `testThreadSubmitTransportHelpersCollectFieldsAndParseResponses`'s expected `fields` object to include the four new keys (each `''`, since that fake form doesn't set them — matching every other optional field's default-empty behavior).
    - Added `testThreadSubmitTransportHelperCollectsEventFieldsWhenPresent`: same Node-VM harness, form now includes all four event fields; asserts `collectThreadSubmitFields()` returns their exact values.
  - `tests/WriteApiSmokeTest.php`: added `testPrepareThreadWithEventFieldsRoundTripsThroughFinalizeAndReadModel`, modeled on `testFinalizePreparedApprovalVerifiesSignatureBeforeCreatingApproval` — generates a real local GPG key (`createSigningKey()`), links it as an identity, calls `/api/prepare_thread` with all four event fields, signs the returned canonical record (`signCanonicalRecord()`), finalizes via `/api/create_prepared_post`, then asserts the rendered thread page's event block shows the correct date, time, location, and link.
- Verification:
  - Ran the new/updated tests individually: all pass, including the full signed round trip.
  - `php tests/run.php` full suite: 955 run (2 more than Stage 1), 951 passed, same 4 pre-existing failures, no new ones.
  - Confirmed live on the user's running dev server (`:8002`, same working tree): `/compose/thread`'s rendered page still has all four event inputs, and the live `/assets/browser_signing.js` response already reflects the fixed `collectThreadSubmitFields()` (opcache validates timestamps, so the fix is live without a server restart).
- Notes:
  - Completion Contract met: a signed-in member's event fields now survive the full prepare → sign → finalize path, rendering identically to the already-correct anonymous path; malformed input still fails closed (Stage 1); the anonymous-submit path and `createThread()`'s own tests are untouched and still pass.
  - Final confirmation is the user retrying their original scenario (the "friday meet" thread, or a fresh one) in their own browser now that the fix is live.
