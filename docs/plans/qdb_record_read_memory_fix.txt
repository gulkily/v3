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

Follow-up: Activity evidence memory peak
Report: Production build-static progressed to index activity evidence, then exhausted 128 MiB at ActivityEvidenceIndex.php line 28 while fetching every activity row.
Cause: The evidence index loaded the entire pending activity history, signature map and grouped row arrays before its existing commit batching.
Change: Select at most 200 pending rows per iteration using the existing pending-evidence index, close the select cursor before writes, and scope signatures, groups and commit manifests to that batch. Processed rows leave the pending set. Release completed source projections in ReadModelBuilder after activity insertion, before commit/signature/evidence indexing.
Compatibility: No new tables, columns or indexes; the existing query uses the same columns and ordering with a bounded LIMIT. Evidence encoding and transaction ownership are unchanged.
Verification: 17/17 checks passed at 128 MiB across ActivityProjectionTest, ImportAdmissionTest and ReactionRecordFormatParityTest. New subprocess regression indexes 1001 activity rows with 32 KiB labels and 104 MiB retained memory, verifies every evidence value against direct computation, repeats refresh and rolls back. Existing signature refresh, incremental/rebuild parity and rollback checks pass.
Integration: QDB fixture build-static completed all four phases at 128 MiB after the follow-up, producing 26 HTML files and 55 assets.
Remaining limitation: Full production data remains unavailable locally; large individual commit manifests and other phases may require separate memory reductions if the host reports another peak.
