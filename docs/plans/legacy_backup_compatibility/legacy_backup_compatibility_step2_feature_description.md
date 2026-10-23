> **Feature plan:** [Step 1](./legacy_backup_compatibility_step1_solution_assessment.md) · [Step 2](./legacy_backup_compatibility_step2_feature_description.md) · [Step 3](./legacy_backup_compatibility_step3_development_plan.md) · [Step 4](./legacy_backup_compatibility_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Public repository downloads can fail on supported legacy posts missing `Created-At` when the image-aware exporter is selected. The import command hides the source error behind generic access guidance, delaying diagnosis.

## User stories

- As a source operator, I want backups to support the same legacy posts as ordinary reads so that public repository downloads remain usable.
- As an importing operator, I want actionable source errors and appropriate recovery guidance so that I can resolve a failed download and retry safely.

## Core requirements

- Reuse established legacy-aware loading for backup image-reference discovery, supporting metadata and Git date recovery without rewriting canonical bytes, signatures, or history; retain the strict parser's existing contract.
- Apply compatibility to repository tarballs, ZIPs, and image backups, including legacy posts with and without image references; preserve rejection of malformed records, unsafe files, and missing or corrupt referenced images.
- Include the affected repository-relative record path in scan failures and a bounded, sanitized plain-text source error in CLI download failures; handle other response types safely and retain existing transfer limits and HTTP status behavior.
- Direct download failures toward source repair and retry. Show `--resume` guidance only when import state actually supports recovery; preserve recovery for interrupted merges.
- Add regression coverage through real source HTTP downloads and importer preview, plus focused compatibility and failure checks; document diagnosis, retry, and deployment verification.

## Delivery scope and completion boundary

Application change covering backup compatibility, CLI diagnostics/recovery guidance, regression tests, and relevant operator documentation. Reuse existing public downloads and import commands; no new UI, database schema, canonical-record migration, or changes to `todo.txt`. Local completion requires applicable regression checks and an actual source-download-to-destination-import flow with repeat-import verification. Production recovery requires deployed-source and user WSL evidence; record those as pending if access is unavailable, without claiming the incident resolved. Deployment and destination production writes require their own authorization.

## Known environment and recovery

The user's ordinary entry is `./v3 import-instance zenmemes.com` from `~/v3-test-kris` on WSL. On source export failure, the command must identify the source error without changing destination content or suggesting an unavailable resume. After source repair, the operator runs the same command with `--dry-run`, reviews the preview, then retries the original import. Evidence must include a successful zenmemes.com archive response and that WSL preview/import result; tests on another machine cannot substitute. Preserve any independently pending merge journal.

## Risks

- Legacy date provenance may be unavailable or expensive to recover, keeping downloads blocked or slow. Before dependent implementation, validate metadata and Git recovery behavior and bounded performance; retain actionable failures when provenance is unusable.
- A compatibility shortcut could omit image checks or accept malformed records. Validate the loading boundary early; retain failure fixtures and prove source bytes remain unchanged.
- Remote error bodies may contain HTML, control characters, or excessive data. Define safe plain-text handling and bounds before diagnostic changes; test hostile and absent error details.
- Remote deployed revision and WSL access are unverified. Separate local acceptance from deployment evidence and leave explicit operator verification steps when access is unavailable.

## Shared component inventory

Reuse the canonical repository's legacy-compatible loader; extend the shared backup image-reference scanner used by repository tar/ZIP and image exports. Retain public download routes, Backup page links, and 409 handling. Extend the existing instance archive downloader and import command's failure/recovery output. Reuse legacy timestamp recovery and existing import/backup test support; no new parsing or UI system.

## User flow

1. Source operator serves a backup containing modern and supported legacy posts.
2. Importing operator previews the source with the existing command; failures identify the cause and relevant recovery action.
3. After a successful preview, the operator imports, checks content and original dates, and repeats the import to confirm no additions.

## Success criteria and request status

The actual image-aware HTTP route exports supported legacy content; preview/import succeeds with preserved dates and source bytes; repeat import adds nothing. Invalid records and damaged/missing images still fail with useful diagnostics. Download failure leaves destination content unchanged; interrupted merges remain resumable. Steps 1 and 2 were explicitly approved; Step 3 is drafted. The requested source report is preserved in [the repository](../zenmemes_import_http409_findings.txt); no additional scope or implementation approval is inferred.
