# Cross Thread Reference Search Step 4 Implementation Summary

## Stage 1 - Internal Related-Content Search Boundary
- Changes:
  - Added `ForumRewrite\Analysis\RelatedContentSearchService` as an internal SQLite-backed search contract.
  - Search returns bounded cross-thread matches with scores, stable post/thread URLs, subjects, authors, timestamps, and excerpts.
  - Added focused coverage for ranked matches, self/same-thread exclusion, empty no-match behavior, and test harness registration.
- Verification:
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/RelatedContentSearchServiceTest.php"; $test = new RelatedContentSearchServiceTest(); foreach (get_class_methods($test) as $method) { if (str_starts_with($method, "test")) { $test->{$method}(); echo "PASS RelatedContentSearchServiceTest::{$method}\n"; } }'`
  - Result: all `RelatedContentSearchServiceTest` methods passed.
  - Also ran `php -d zend.assertions=1 -d assert.exception=1 tests/run.php`; it reached the new tests, but the full run failed on existing `LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention` before later completing with the Stage 1 test fixed.
- Notes:
  - Ranking is intentionally simple keyword scoring with no read-model schema migration.
  - Same-thread posts are excluded because existing `thread_comments` already carries same-thread context.

## Stage 2 - Analyzer Context Integration
- Changes:
  - Extended post-analysis context construction to include a bounded `related_content` list when cross-thread matches exist.
  - Bumped the analysis schema version so existing cached analysis rows do not mask the new context shape.
  - Updated the stub analyzer to record received related content in its raw response for regression testing.
  - Added write API smoke coverage proving analysis receives a related cross-thread post and excludes the target post.
- Verification:
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/LocalAppSmokeTest.php"; require "tests/WriteApiSmokeTest.php"; $test = new WriteApiSmokeTest(); $test->testPostAnalysisContextIncludesRelatedCrossThreadContent(); echo "PASS WriteApiSmokeTest::testPostAnalysisContextIncludesRelatedCrossThreadContent\n";'`
  - Result: `PASS WriteApiSmokeTest::testPostAnalysisContextIncludesRelatedCrossThreadContent`.
- Notes:
  - No post creation path changes were made; related-content lookup runs inside the existing analysis path.
  - Empty match sets omit `related_content`, preserving existing analyzer behavior.

## Stage 3 - Related-Content-Aware Analysis Replies
- Changes:
  - Updated the Dedalus post-analysis prompt to tell the analyzer how to use or ignore `related_content` safely.
  - Updated stub analysis behavior so strong related matches can shape `engagement.suggested_response` with a stable post URL.
  - Extended tests to verify prompt guidance and that related-content suggestions can flow into stored engagement output.
- Verification:
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/DedalusPostAnalyzerTest.php"; $test = new DedalusPostAnalyzerTest(); $test->testSystemPromptGuidesUseOfRelatedContent(); echo "PASS DedalusPostAnalyzerTest::testSystemPromptGuidesUseOfRelatedContent\n";'`
  - Result: `PASS DedalusPostAnalyzerTest::testSystemPromptGuidesUseOfRelatedContent`.
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/LocalAppSmokeTest.php"; require "tests/WriteApiSmokeTest.php"; $test = new WriteApiSmokeTest(); $test->testPostAnalysisContextIncludesRelatedCrossThreadContent(); echo "PASS WriteApiSmokeTest::testPostAnalysisContextIncludesRelatedCrossThreadContent\n";'`
  - Result: `PASS WriteApiSmokeTest::testPostAnalysisContextIncludesRelatedCrossThreadContent`.
- Notes:
  - The prompt explicitly warns against citing weak matches.
  - Agent reply publication already uses `engagement.suggested_response`, so no separate reply path was added.

## Stage 4 - Inspectability And Regression Coverage
- Changes:
  - Persisted `related_content` with completed post analyses in the operational SQLite analysis store.
  - Included related content in approved-viewer analysis API details.
  - Rendered related-content links and excerpts inside the existing post-card `Post analysis` details panel.
  - Extended the cross-thread write API smoke test to verify persistence, approved-only display, and API detail exposure.
- Verification:
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/DedalusPostAnalyzerTest.php"; $test = new DedalusPostAnalyzerTest(); $test->testSqliteStorePersistsAndHydratesPostSummary(); echo "PASS DedalusPostAnalyzerTest::testSqliteStorePersistsAndHydratesPostSummary\n";'`
  - Result: `PASS DedalusPostAnalyzerTest::testSqliteStorePersistsAndHydratesPostSummary`.
  - `php -d zend.assertions=1 -d assert.exception=1 -r 'require "tests/ApplicationServerTimingTest.php"; require "tests/LocalAppSmokeTest.php"; require "tests/WriteApiSmokeTest.php"; $test = new WriteApiSmokeTest(); $test->testPostAnalysisContextIncludesRelatedCrossThreadContent(); echo "PASS WriteApiSmokeTest::testPostAnalysisContextIncludesRelatedCrossThreadContent\n";'`
  - Result: `PASS WriteApiSmokeTest::testPostAnalysisContextIncludesRelatedCrossThreadContent`.
  - `php -l src/ForumRewrite/Analysis/SqlitePostAnalysisStore.php && php -l templates/partials/post_card.php`
  - Result: no syntax errors.
  - `php -d zend.assertions=1 -d assert.exception=1 tests/run.php`
  - Result: all new related-content tests passed; full run failed on existing `LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention`.
- Notes:
  - Related-content display is limited to the existing approved-viewer analysis details surface.
  - The analysis store migration is additive and operational-only; no canonical records are written.
