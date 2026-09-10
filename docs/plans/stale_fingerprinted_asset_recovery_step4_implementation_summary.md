# Stale Fingerprinted Asset Recovery Step 4 Implementation Summary

## Stage 1 - Establish stale-reference baseline
- Changes:
  - Added focused coverage distinguishing current fingerprinted asset paths, stale hashes for known source assets, and unknown asset paths.
  - Confirmed the current resolver rejects stale and unknown fingerprints before recovery behavior is added.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testAssetFingerprintDistinguishesCurrentAndStaleAssetPaths`
  - `php tests/run.php LocalAppSmokeTest::testAssetFingerprintPathsUseContentHashFilenames`
  - `git diff --check -- tests/LocalAppSmokeTest.php`
- Notes:
  - Runtime behavior is unchanged in this stage.
  - Unrelated worktree changes remain unstaged.

## Stage 2 - Recover stale fingerprinted asset requests
- Changes:
  - Added a canonical resolver for obsolete fingerprinted paths whose known source asset still exists.
  - Updated the front controller to redirect stale asset requests to the current fingerprinted URL.
  - Limited recovery to recognized public asset paths and used non-cacheable redirect semantics.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testAssetFingerprintDistinguishesCurrentAndStaleAssetPaths`
  - `php tests/run.php LocalAppSmokeTest::testFrontControllerRecoversStaleFingerprintedAssetRequests`
  - `git diff --check -- src/ForumRewrite/Host/AssetFingerprint.php src/ForumRewrite/Host/FrontController.php tests/LocalAppSmokeTest.php`
  - Focused resolver, redirect, and diff checks passed.
- Notes:
  - Current fingerprints continue to receive immutable asset responses; only stale references redirect.
  - Unknown assets remain unresolved rather than becoming arbitrary file access.

## Stage 3 - Guard static artifact publication
- Changes:
  - Added a publication guard that refuses to write generated HTML when it references a fingerprinted asset missing from the target artifact root.
  - Extended the static artifact smoke test to verify generated index HTML references files present in the same artifact set.
  - Kept atomic temporary-file-to-final-path replacement for each generated HTML artifact.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout`
  - `git diff --check -- src/ForumRewrite/Host/StaticArtifactBuilder.php tests/LocalAppSmokeTest.php`
  - Static artifact generation and reference checks passed.
- Notes:
  - This prevents new builds from publishing HTML with missing fingerprinted assets; runtime recovery remains the defense for already-published stale HTML.
