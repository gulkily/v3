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

## Stage 2 - Canonical roles and safe links
- Changes:
  - Expanded activity manifest entries with observable canonical roles and historic source links.
  - Kept deleted and non-canonical paths enumerated but unlinked, preventing broken or unauthorized source navigation.
  - Added coverage for a canonical post record's role and commit-scoped source link.
- Verification:
  - `php -l src/ForumRewrite/Application.php` and `php -l tests/LocalAppSmokeTest.php` passed.
  - `php tests/run.php LocalAppSmokeTest` passed.
- Notes:
  - Stage 3 will add signer/public-key associations only for detached-signature entries.

## Stage 3 - Signature signer keys
- Changes:
  - Added detached-signature signer identity and canonical public-key metadata to manifest entries.
  - Resolves signer identity from the signed canonical record and finds its current canonical public key independently of the commit's file set.
  - Added explicit unavailable states for missing signer identities or keys.
- Verification:
  - `php -l src/ForumRewrite/Application.php` and `php -l tests/LocalAppSmokeTest.php` passed.
  - `php tests/run.php LocalAppSmokeTest` passed, including a signature whose signer key predates its commit.
- Notes:
  - Stage 4 will render this metadata in the Activity-only shared component.

## Stage 4 - Shared Activity rendering
- Changes:
  - Added one Activity-only commit-manifest partial listing each changed file's status, role, path, and allowed source link.
  - Detached-signature entries show signer identity and its public-key link or an explicit unavailable state.
  - Rendered the same partial in Classic Activity cards and Forte Activity detail panes; added compact shared styling.
- Verification:
  - PHP syntax checks passed for the new partial and both consumers.
  - `php tests/run.php LocalAppSmokeTest` passed, including the Classic/Forte manifest parity check.
- Notes:
  - Individual post pages retain their existing source metadata unchanged.
