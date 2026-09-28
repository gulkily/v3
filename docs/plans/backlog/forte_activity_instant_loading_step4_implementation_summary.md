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

## Stage 2 - Bounded server bootstrap and APIs
- Changes:
  - Forte Activity now loads a 20-row selected-view batch; Commit rows load only when the Commit filter is initially requested.
  - Added row-only paging and `/api/forte_activity_detail`, while preserving the existing enriched paging response by default.
- Verification:
  - Local server smoke: `/forte/activity/` returned 200 with a 128,688-byte initial page; row-only paging and activity-detail APIs returned `status: ok`.
  - PHP lint passed for `ActivityService`, `ForteActivityController`, and `Application`.
- Notes:
  - The existing list/detail templates still render hidden articles; Stage 3 removes that presentation work.

## Stage 3 - Minimal first-paint markup
- Changes:
  - The Activity detail pane now renders only the selected item article instead of a hidden article for every initial row.
- Verification:
  - Local server smoke: `/forte/activity/` returned 20 rows and only three page articles (placeholder, selected detail, and existing dialog content), at 29,509 bytes.
  - `php -l templates/partials/paned_activity_detail_pane.php` and `git diff --check` — passed.
- Notes:
  - Selected detail remains server-rendered; subsequent selections receive their detail in Stage 4.

## Stage 4 - On-selection Activity details
- Changes:
  - Added Activity-detail loading, per-visit caching, loading/error states, and stale-response protection to the Activity reader.
  - Reused the existing selected article when present and retained the separate Commit-detail flow.
- Verification:
  - `node --check public/assets/paned_activity_reader.js`, `php -l src/ForumRewrite/Http/ForteActivityController.php`, and `git diff --check` — passed.
  - Stage 2's local API smoke confirmed the Activity-detail endpoint returns a rendered detail article.
- Notes:
  - A direct user selection updates row state before issuing the detail request, preventing a stale response from replacing a newer selection.
