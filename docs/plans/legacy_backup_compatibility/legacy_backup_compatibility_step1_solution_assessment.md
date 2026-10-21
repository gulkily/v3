> **Feature plan:** [Step 1](./legacy_backup_compatibility_step1_solution_assessment.md) · [Step 2](./legacy_backup_compatibility_step2_feature_description.md) · [Step 3](./legacy_backup_compatibility_step3_development_plan.md) · [Step 4](./legacy_backup_compatibility_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Can you please help me figure out how to debug this and make sure it does not happen again?

## Understood Intent

Diagnose zenmemes.com's repository-download HTTP 409 and prevent recurrence when importing into the user's WSL checkout at `~/v3-test-kris`. Deliver a source-side backup compatibility fix, useful failure diagnostics, regression coverage, and deployment verification without rewriting canonical posts.

## Problem

Backup image-reference scanning uses the strict post parser instead of the repository's legacy-compatible loader, so a post missing `Created-At` blocks archive generation before import can recover legacy timestamps.

## Evidence

- Supporting investigation: [zenmemes.com HTTP 409 findings](../zenmemes_import_http409_findings.txt), downloaded from [the source report](https://zenmemes.com/assets/zenmemes_import_http409_findings.txt). Its relative source and runbook links resolve from this directory.
- That report clarifies the route trigger: an existing image-store directory selects the image-aware exporter even for a repository download; plain-text legacy posts can then block export. Regression coverage must exercise this actual HTTP route.
- The source endpoint returned `409` with `Backup failed: Missing required post header: Created-At` during the preceding investigation; exact affected production paths remain unknown.
- A temporary local fixture reproduces that exact exception through `ImageBackup::references`; adding a valid timestamp to the fixture makes the scan succeed. Temporary files were removed.
- `CanonicalRecordRepository::loadPost` already supports legacy timestamps from metadata or Git history; the backup scanner bypasses it.
- The existing backup test's legacy image fixture includes `Created-At`; it does not cover a legacy post missing that header.
- The downloader discards the remote error body and emits generic guidance for every non-200 response. The controller maps backup runtime failures to 409.

## Option A

Use the established legacy-compatible repository loader during backup reference scanning, retaining existing record and image validation.

- Pros: Consistent post interpretation across normal reads and backup; preserves original bytes and signatures.
- Cons: Timestamp provenance must be available; history lookup cost and malformed-record diagnostics need verification.

## Option B

Read canonical headers and body without requiring post timestamps when collecting image references.

- Pros: Backup scanning avoids date recovery for data it does not need.
- Cons: Changes the backup validation boundary; must explicitly preserve image checks and decide how malformed post semantics are handled.

## Recommendation

Prefer Option A, subject to validating history performance and supported legacy provenance. Do not broadly skip parse errors, weaken the strict parser, or invent timestamps. Include bounded plain-text source-error reporting in the CLI and record-path context for scan failures. Retain existing HTTP status behavior unless a separate need emerges.

The releasable slice runs from a legacy-containing source repository through public archive generation to the user's normal WSL import preview/import. Cover legacy posts with and without image references, metadata and Git timestamp recovery, unchanged source bytes, and continued rejection of malformed records and missing/corrupt images. Exercise the actual HTTP endpoint and CLI failure diagnostics; require production archive and WSL preview verification after deployment. A passing local fixture alone does not establish production recovery.

## Requests and status

- Debug and prevent recurrence / assessed above / Step 1 explicitly approved; Step 2 drafted / no implementation or deployment performed.
- Include the remote findings in this repository / saved unchanged in `zenmemes_import_http409_findings.txt` and linked above / explicitly requested / documentation only; no implementation approval inferred.
- No database schema change, original-post migration, or `todo.txt` edit is proposed. Production repository and deployed revision have not been inspected.
