Task: QDB static-build record reader memory fix
Date: 2026-10-30
Status: Implemented locally; production verification pending
Authorization: User requested fixing the scripts within the existing 128 MiB PHP limit after build-static failed in RecordLimits::read.
Scope: Small direct fix to the shared canonical reader; no Railspike cycle.
Cause: stream_get_contents with the 16 MiB record ceiling requests a ceiling-sized temporary allocation even for tiny records. This can exhaust memory during thread-label indexing when the rebuild already retains substantial data.
Change: Read in at most 8 KiB chunks, accumulating actual bytes and rejecting content beyond the existing ceiling, including growth after the initial file-size check. Preserve bytes, empty records, size limits and handle closure.
Regression: Under memory_limit=128M with 114 MiB retained, the old read call fails allocating 16777248 bytes; the updated reader successfully reads a small label-shaped record.
Verification: ImportAdmissionTest passes 6/6, including empty and binary content across chunk boundaries and existing size-limit tests.
Broader checks: ImportAdmissionTest, CanonicalRecordParsersTest, ReadModelBuilderTimingTest and PlatformDocsStaticTest under 128 MiB: 58/60 passed. The runner records prior failures for PlatformDocsStaticTest::testStaticReleaseIncludesCompleteInventoryAndSafeAssets (since October 25) and ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts (since October 20); both failed equality assertions in this run. No full-suite pass claimed.
Integration: scripts/build_static_artifacts.php completed all four phases under memory_limit=128M against isolated parity_minimal_v1 repositories in standard and QDB profiles; QDB produced 26 HTML files and 55 assets.
Limitations: Local PHP is 8.1; reported host uses PHP 8.3. Full production corpus and host build remain unverified. This removes the reported oversized temporary allocation, not a guarantee that every production build phase fits in 128 MiB.
Deployment: No push or deployment performed. After deployment, rerun build-static with the host's existing configuration and memory limit; retain any subsequent failure and phase for further diagnosis.
Dependencies: No additions or updates.
