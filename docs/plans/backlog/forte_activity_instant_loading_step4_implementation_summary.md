# Forte Activity Instant Loading — Step 4: Implementation Summary

## Stage 1 - Lightweight activity list contract
- Changes:
  - Added `ActivityService::fetchActivityRows()` for un-enriched list records and `fetchActivityDetail()` for one enriched item.
  - Kept `fetchActivity()` as the classic-compatible enriched wrapper over the lightweight query.
  - Added coverage that confirms rows omit commit-manifest data until detail is requested.
- Verification:
  - `./v3 test LocalAppSmokeTest::testActivityRowsStayLightweightUntilDetailRequested` — passed.
  - `php -l src/ForumRewrite/Activity/ActivityService.php` and `php -l tests/LocalAppSmokeTest.php` — passed.
- Notes:
  - No database changes; filter, sort, cursor, and enriched classic Activity contracts remain intact.
