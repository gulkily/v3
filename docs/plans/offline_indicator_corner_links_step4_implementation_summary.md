> **Feature plan:** [Step 1](./offline_indicator_corner_links_step1_solution_assessment.md) · [Step 2](./offline_indicator_corner_links_step2_feature_description.md) · [Step 3](./offline_indicator_corner_links_step3_development_plan.md) · [Step 4](./offline_indicator_corner_links_step4_implementation_summary.md)

# Offline Indicator Corner Links Step 4 Implementation Summary

## Stage 1 - Links and freshness titles
- Changes:
  - Added an `/offline/` link beside Outbox in the offline-mode bar.
  - Kept the archive/reader indicator spans as hidden hooks; the script now also sets the bar's hover title to `archive … · reader …`.
  - Added a smoke-test assertion for the new link.
- Verification:
  - `node --check public/assets/offline_reader.js` and `php -l` on the template passed.
  - `php tests/run.php OfflineSnapshotPresentationTest LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell` — 8 run, 8 passed.
- Notes:
  - Visual layout change is Stage 2; until then the bar still renders full width.

## Stage 2 - Fixed corner badge
- Changes:
  - Replaced the full-width bar styles in `public/assets/site.css` with a compact fixed top-right badge (z-index below the sticky app-version banner); removed the now-unused indicator and narrow-screen rules.
- Verification:
  - `php tests/run.php OfflineSnapshotPresentationTest LocalAppSmokeTest` — only `testApplicationRendersCoreRoutes`, `testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags` (and a rebuild-recovery test) fail; all three also fail on the pre-feature commit `b8b36b53`, so they are unrelated.
  - Browser check at 360px / desktop: not performed in this environment; still needs a manual look (see Notes).
- Notes:
  - Fixed positioning removes the bar from page flow, so no vertical space is used; header overlap at phone width is unverified.
