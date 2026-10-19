> **Feature plan:** [Step 1](./retracted_posts_hidden_from_listings_step1_solution_assessment.md) · [Step 2](./retracted_posts_hidden_from_listings_step2_feature_description.md) · [Step 3](./retracted_posts_hidden_from_listings_step3_development_plan.md) · [Step 4](./retracted_posts_hidden_from_listings_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feat/retracted-posts-hidden-from-listings`. Planning commit: `52e5d3c1`.

## Stage 1 - Board-style thread listings
- Changes:
  - `ThreadRepository::boardRowsSql()` now excludes threads with `retracted_at` set. This covers `fetchThreads` and every caller (board, tags, Forte board, API text, Qdb) and the editable `BoardListingQuery`.
  - `ThreadRepository::byId()` is unchanged, so permalinks still resolve.
  - Added `testRetractedRootIsOmittedFromThreadListingsButStillResolvable` to `tests/ReadModelPostRetractionsTest.php`.
  - Updated `WriteApiSmokeTest` (renamed to `testRetractedRootShowsPlaceholderOnPermalinkKeepsRepliesAndLeavesBoard`): the board listing no longer shows the "Deleted post" row; the permalink assertions are unchanged.
- Verification:
  - `php tests/run.php` (full suite): one new failure, the old board assertion above, since fixed. Six other failures were already failing in earlier recorded runs (ApprovalSeedCommandTest, AgentIdentityServiceTest, LocalAppSmokeTest sqlite viewer, ReadModelBuilderTimingTest, two WriteApiSmokeTest cases); not caused by this change.
  - Focused re-run of `ReadModelPostRetractionsTest`, `BoardListingQueryTest` and the updated `WriteApiSmokeTest` case: 7 passed, 0 failed. The full suite was not re-run after the test edit.
  - Read-model schema check: not applicable, no schema change.
- Notes:
  - Tags index, per-tag, Forte board, API text and Qdb share the same query; only the board page and the editable listing query are asserted directly. Tag and API text pages are not asserted separately.
  - Activity lists the retracted thread as a "Deleted post" row; that is Stage 3.

## Stage 2 - Author profile lists
- Changes:
  - `AuthoredContentRepository::visibleThreads` and `visiblePosts` exclude retracted rows; `countVisible` follows because it counts their results.
  - Added `testRetractedPostsLeaveAuthoredContentListsAndCounts` to `tests/ReadModelPostRetractionsTest.php`.
  - `WriteApiSmokeTest::testSameAccountQuoteAndReplyNoticesShareAcceptedAttribution` used these lists to check retraction attribution on retracted rows. It now asserts the lists are empty and reads the attribution from the read model tables instead.
- Verification:
  - Full `php tests/run.php`: 1338 run, 1331 passed, 7 failed. One was new (the attribution test above, since fixed); the other six were failing in earlier recorded runs.
  - Focused re-run of `ReadModelPostRetractionsTest` (5 passed) and the updated attribution test (passed). The full suite was not re-run after that test edit.
  - Read-model schema check: not applicable.
- Notes:
  - Profile pages and Forte profile / user-detail API consume these lists, so they are covered through the repository; no page-level assertions were added.
