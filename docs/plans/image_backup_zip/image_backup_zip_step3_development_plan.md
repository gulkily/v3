> **Feature plan:** [Step 2](./image_backup_zip_step2_feature_description.md) · [Step 3](./image_backup_zip_step3_development_plan.md) · [Step 4](./image_backup_zip_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/image_backup_zip`; [ledger](../effort_estimation_trial.md). Scope v1 / R1 / P2.

Extended estimates: enabled.

**Cycle estimate: 0.40**

## Completion Contract

Backup downloads a correctly named ZIP with the existing manifest and image bytes; ordinary CLI verification/restoration accepts it and legacy TAR. Preserve matching revisions, access rules, capacity, private staging, retry and no-overwrite behavior. Verify real downloads through the application, extraction with a ZIP tool and restored image hashes. Release requires the checks below, documentation and stage commits; deployment is outside scope. No schema changes or new dependencies: reuse PHP ZipArchive (already used by thread archives), Phar/TAR support and existing test tools.

## Key Risks

- **High risk: unsafe ZIP expansion.** Validate names/types, duplicates, encryption, declared and actual expanded sizes and checksums before activation; never use unrestricted extraction. Exercise hostile archives before exposing the download.
- **High risk: broken recovery/compatibility.** Install ZIP reader support first; retain TAR reading and the old TAR endpoint. Verify both against matching content and unchanged destinations after failure.
- Runtime mismatch: PHP 8.1 CLI has ZipArchive and PharData; check the serving runtime and simulate unavailable ZIP support. Fail actionably without a partial download or silently returning TAR bytes. No automatic installation is authorized.

## Stage 1 — Verify and restore ZIP image backups

**Estimate: 0.18**

- Goal: ZIP and TAR both complete the existing recovery flow.
- Dependencies: approved plan and planning commit on a feature branch.
- Expected changes: format dispatch and a bounded ZIP staging reader; retain TAR reader and common manifest/store validation. Planned private contract: `unpackZip(string $archive, string $stage): array`.
- Verification approach: valid populated/empty ZIP, legacy TAR, CLI verify/restore/retry, mismatched revision, duplicate/path/link/encryption/corruption/expansion failures, no activation or leaked staging after failure.
- Estimate details: reader validation is the driver; malformed ZIP fixture behavior is the uncertainty. This stage owns shared recovery/security regression cases.
- Risks or open questions: ZIP metadata alone is insufficient; bound streamed reads and verify actual bytes before store activation.
- Canonical components/API contracts touched: `ImageRestore`; unchanged `ImageStore`, `RepositorySnapshot`, manifest schema and public `run()` signature.

## Stage 2 — Deliver ZIP through Backup

**Estimate: 0.12**

- Goal: the advertised image download is a working ZIP.
- Dependencies: Stage 1 passed.
- Expected changes: explicit image format selection in `ImageBackup::export`; new ZIP route/headers/name, existing TAR compatibility route, matching access classification and one ZIP link in the shared Backup template.
- Verification approach: real authorized/denied requests; correct MIME, filename and bytes; revision pinning; empty/populated ZIP extraction and restore with both content formats; TAR URL still returns TAR; failure/retry and source non-mutation.
- Estimate details: coordinated route/export wiring drives work; runtime capability errors are the uncertainty. Reuse Stage 1 validation cases.
- Risks or open questions: misrouting could bypass access or mislabel bytes; test both URLs and unknown formats explicitly.
- Canonical components/API contracts touched: `ImageBackup`, `Application`, `InstancePageController`, `templates/pages/instance.php`; preserve existing callers through a compatible optional image-format argument.

## Stage 3 — Document and verify ordinary use

**Estimate: 0.08**

- Goal: users can download and recover with the documented ZIP workflow.
- Dependencies: Stage 2 passed.
- Expected changes: CLI help, image runbook, CLI reference, bundled README, affected browser/server assertions, UI audit and cycle summary/ledger.
- Verification approach: browser download → ZIP tool extraction → CLI verify/restore; missing/corrupt/wrong-pair recovery; phone/desktop themes and populated/empty Backup with all metadata and recent items preserved. Run focused image/archive/import/Backup suites, link/diff checks and dependency inventory.
- Estimate details: documentation and end-to-end evidence drive work; fixture/runtime differences are the uncertainty. This stage owns combined regression, UI checks and closeout documentation.
- Risks or open questions: stale TAR examples could mislead; update active guidance while retaining legacy fixtures and historical records.
- Canonical components/API contracts touched: existing operator/browser entry points and host documentation; no extra UI prose, deployments or library updates.

## Checkpoint and approval

Step 2 approved. 2026-10-26T00:56:13Z: whole-cycle **0.40 reaffirmed**, scope v1/original baseline `5510efde63b6f5eae1970d836d363bb63403a2f2`, R1 hashes verified, Codex single exposed rater. Stage values are independent stage-work judgments, not additive forecasts or measured time; shared overhead includes inspection/planning. Original 0.40 intake/Step 2 snapshots retained. No implementation or accepted outcome. Awaiting `Approved Step 3`; plans remain uncommitted.

API evidence: PHP documents [entry streams](https://www.php.net/manual/en/ziparchive.getstream.php), [entry attributes](https://www.php.net/manual/en/ziparchive.getexternalattributesindex.php) and [indexed metadata](https://www.php.net/manual/en/ziparchive.statindex.php); these APIs support inspection and bounded reading without unrestricted extraction. Existing `scripts/archive_thread.php` supplies the in-project ZIP precedent.

*— Codex · 2026-10-26 UTC*
