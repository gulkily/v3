# Offline Mode Outbox Banner Link — Step 4 Implementation Summary

## Stage 1 - Link offline banner to Outbox
- Changes:
  - Added an Outbox anchor to the existing offline-mode banner, using the canonical `/tools/outbox/` route.
  - Added offline-reader shell coverage for the visible link.
- Verification:
  - `php -l templates/pages/offline_reader.php`, `php -l tests/LocalAppSmokeTest.php`, and `php -l tests/OfflineNavigationWorkerTest.php` passed.
  - `php tests/run.php LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell OfflineNavigationWorkerTest` — 4 run, 4 passed.
  - The existing dedicated Outbox cached-shell fallback test passed, confirming the linked route remains available during offline navigation.
- Notes:
  - No queue, storage, signing, indicator, service-worker, or responsive-style behavior changed; the banner's existing flex wrapping accommodates the added inline link.
