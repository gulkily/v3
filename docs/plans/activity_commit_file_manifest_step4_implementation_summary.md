# Activity Commit File Manifest — Step 4: Implementation Summary

## Stage 1 - Cached changed-file manifest
- Changes:
  - Added a render-local, Git-authoritative source-commit manifest with add, modify, delete, and rename statuses.
  - Exposed the manifest on activity items for both Activity renderers and updated the source-commit detail route to use it.
  - Added Git-backed coverage for a commit containing every supported change status.
- Verification:
  - `php -l src/ForumRewrite/Application.php` and `php -l tests/LocalAppSmokeTest.php` passed.
  - `php tests/run.php LocalAppSmokeTest::testSourceCommitRouteShowsCommitDetails LocalAppSmokeTest::testSourceCommitRouteListsChangedFileStatuses LocalAppSmokeTest::testActivityShowsGitSourceCommitForCanonicalRecords` passed.
  - Full `php tests/run.php` reached five unrelated existing browser-reaction failures: `BrowserSigningNormalizationTest` reports `clickHandler is not a function`.
- Notes:
  - Stage 2 will add canonical roles and safe per-file links; this stage deliberately adds no feed markup.
