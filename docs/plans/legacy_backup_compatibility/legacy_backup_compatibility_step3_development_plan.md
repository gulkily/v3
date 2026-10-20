> **Feature plan:** [Step 1](./legacy_backup_compatibility_step1_solution_assessment.md) · [Step 2](./legacy_backup_compatibility_step2_feature_description.md) · [Step 3](./legacy_backup_compatibility_step3_development_plan.md) · [Step 4](./legacy_backup_compatibility_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

The existing public downloads and `./v3 import-instance` must handle committed legacy posts with an image-store directory, preserve bytes and recovered dates, publish accepted content, and add nothing on repeat import. Download failures expose safe source detail without destination changes or inappropriate resume advice; interrupted merges remain recoverable. Scope: canonical loader opt-in, backup scanning, downloader/CLI, related tests and operator docs; no schema or original-record changes. Local acceptance and deployment/WSL acceptance are separate: require a repaired zenmemes.com download and the user's preview/import results before claiming production recovery. No deployment or live destination writes are authorized here.

## Key Risks

- **High risk:** Existing legacy loading permits filesystem-date fallback. Inspect and test this first; add a backward-compatible opt-out for backup use, retaining metadata/Git provenance and existing defaults elsewhere.
- **High risk:** Compatibility could hide malformed posts or broken images. Keep strict parsing and image resolution; prove failures and unchanged bytes before HTTP acceptance.
- Git history may make exports slow. Measure representative multi-post and renamed-post fixtures early; reuse batch timestamp preloading. If acceptable performance cannot be established, stop dependent work and revisit the approach.
- Remote bodies can be hostile; accept only bounded plain text, sanitize terminal controls, and retain transport deadlines. Production/WSL access is unavailable; record outstanding checks with exact retry guidance.

## Stage 1

- Goal: Make normal backup exports legacy-compatible without weakening provenance or image checks.
- Dependencies: Step 3 approval, feature branch, organized planning commit.
- Expected changes: Extend the canonical loader constructor with optional `bool $allowLegacyFilesystemTimestamp = true`; disable that fallback in backup scanning, reuse batch history preloading, and add repository-relative path context to failures. Keep public method signatures otherwise unchanged.
- Verification approach: Add focused backup tests for metadata/Git legacy dates, plain/image bodies, unavailable provenance, malformed posts, missing/corrupt images, tar/ZIP/image exports, unchanged bytes, and multi-post/rename history behavior. Run canonical, image, and legacy tests.
- Risks or open questions: Loader fallback and history cost are stage-entry gates; avoid parser shortcuts and stop on unresolved performance/provenance failures.
- Canonical components/API contracts touched: `CanonicalRecordRepository`, `ImageBackup`, associated parser/backup tests; existing loader callers retain defaults.

## Stage 2

- Goal: Make source failures actionable while preserving merge recovery.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Extend non-200 downloader errors with at most 4 KiB of plain-text detail under existing deadline controls; normalize controls/invalid encoding and truncate displayed detail. Replace unconditional resume advice with journal-aware guidance, preserving independently pending state.
- Verification approach: Test 409, absent/HTML/binary/oversized/control-filled/slow bodies, transfer cleanup, unchanged destination, failed download with an existing journal, and actual interrupted-merge resume.
- Risks or open questions: Never read unbounded bodies or infer resumability solely from reaching the merge phase; preserve fallback status messages.
- Canonical components/API contracts touched: `InstanceArchiveDownloader::download`, `scripts/import_instance.php`, existing CLI/recovery tests; no new public signature needed.

## Stage 3

- Goal: Prevent recurrence through the actual source-download/import route.
- Dependencies: Stages 1–2 verified and committed.
- Expected changes: Extend real HTTP acceptance fixtures with committed timestamp-less text/image posts, metadata and Git provenance, and an image-store directory; reuse existing server/workspace support.
- Verification approach: Request supported source formats, preview, import, inspect dates/bytes and published content, then repeat with zero additions. Replay an invalid-source 409 through CLI and assert safe detail plus no inappropriate resume. Run relevant backup/import/publication/recovery tests and the full existing suite.
- Risks or open questions: Repository imports do not transfer image objects; retain that existing notice and distinguish record acceptance from media restoration.
- Canonical components/API contracts touched: Existing download routes and import CLI acceptance tests; no route or UI redesign.

## Stage 4

- Goal: Provide an auditable incident-recovery handoff for zenmemes.com and WSL.
- Dependencies: Stage 3 acceptance verified and committed.
- Expected changes: Update import/recovery documentation with bounded error inspection, identifying affected records, source-fix deployment checks, preview/retry, and genuine resume conditions. Maintain the implementation summary and verification status.
- Verification approach: Replay the reported source failure against the local fixed route; validate documentation commands/links. After an authorized deployment, verify zenmemes.com and `~/v3-test-kris` preview/import/repeat results; otherwise leave these explicitly pending with actionable operator steps.
- Risks or open questions: Local passes cannot establish remote recovery; source deployment and WSL evidence remain release conditions, not inferred successes.
- Canonical components/API contracts touched: Import runbook, relevant CLI/recovery reference, cycle implementation summary.

## Approval and requests

Steps 1 and 2 explicitly approved; no changed requirements or deferred new requests. The requested source findings remain preserved and linked. Step 3 approval pending; all planning files remain uncommitted. Each stage targets about one hour, including focused checks; split any oversized stage before implementation.
