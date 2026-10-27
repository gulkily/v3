> **Feature plan:** [Step 2](./image_backup_zip_step2_feature_description.md) · [Step 3](./image_backup_zip_step3_development_plan.md) · [Step 4](./image_backup_zip_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/image_backup_zip`; [ledger](../effort_estimation_trial.md). Standing P2 enrollment / R1.

Extended estimates: enabled (new-cycle default).

**Cycle estimate: 0.40**

## Request and problem

“Please make the images archive a zip instead of tar.” Backup currently offers an image TAR, and the verify/restore command only reads TAR. Changing the download alone would break the recovery workflow. Step 1 is skipped because the requested format is explicit.

## User stories

- As a user saving a backup, I want the Images download to be ZIP so I can open it with ordinary ZIP tools.
- As an operator, I want to verify and restore the downloaded ZIP and older TAR backups so existing recovery remains usable.

## Core requirements

- Backup offers `Images (.zip)` through `/downloads/images.zip`, with ZIP bytes, matching filename and media type. Preserve revision pinning, access rules and the existing manifest/object layout.
- Image verification/restoration accepts the new ZIP and existing TAR archives, preserving revision matching, inventory/checksum checks, capacity limits, private staging and no-overwrite behavior.
- ZIP validation rejects unsafe paths, duplicate names, links/special entries, corruption, unsupported encryption and excessive expanded data before activation. Failed export/restore remains actionable and retryable without partial success.
- Preserve the existing TAR URL as a compatibility endpoint serving TAR; advertise only ZIP. Previously downloaded files remain usable without conversion.
- Update the bundled README, CLI help, recovery documentation and affected browser/server checks. Preserve the Backup page's useful data and sectioning; no new explanatory panels.

## Delivery scope and completion boundary

Application change spanning image export, routing/download headers, restore parsing, relevant tests and documentation, UI verification record and this cycle's planning/study ledger. Reuse existing supported archive facilities; no new dependencies or schema changes are authorized. No repository-archive format changes, image conversion, deployment or history/retraction changes.

Normal entry: Backup → Images (.zip) → saved ZIP → existing verify/restore command with the matching content snapshot. Completion requires actual browser download, extraction with a ZIP tool, successful verify/restore with identical image bytes, legacy TAR coverage and failure/retry evidence. Production deployment remains outside scope.

## Shared component inventory and flow

Extend `ImageBackup`, `ImageRestore`, `InstancePageController` and application route/access classification. Backup aliases share `templates/pages/instance.php`; reuse that template. Keep `ImageStore`, `RepositorySnapshot` and the image manifest as the canonical validation contracts. Update `restore_images.php`, image recovery runbook, CLI reference and repository archive README together.

1. Download the content snapshot and Images ZIP with matching revisions.
2. Inspect/extract the ZIP or run image verification against the content snapshot.
3. Restore verified images; on mismatch, obtain the matching pair and retry.

## Risks and success criteria

- **Untrusted ZIP input:** incorrect expansion/path handling could bypass TAR-era safeguards. Review supported archive APIs before Step 3; require malformed/over-limit fixtures and unchanged destination evidence.
- **Compatibility:** switching bytes under a TAR name breaks older clients. Validate new/legacy routes separately, including headers and authorization.
- **Runtime availability:** installed ZIP support may differ between CLI and web. Use already-supported facilities and confirm ordinary server/CLI flows; dependency changes require separate explicit approval.

Acceptance: both content formats pair with the ZIP; populated and empty image collections work; legacy TAR restores; mismatched, corrupt and unsafe archives fail without activating a store; UI still exposes the restored metadata/recent items and works on phone/desktop.

## Study snapshots and approval

2026-10-26T00:45:01Z: scope v1, starting revision `5510efde63b6f5eae1970d836d363bb63403a2f2`, R1 hashes verified. Codex single exposed rater; initial exporter/restore/route inspection occurred before this intake record, and reference scores were visible. No independent or pristine pre-inspection rating is claimed.

- Intake after inspection: **0.40 whole-cycle projection**; ZIP validation and legacy recovery are the main drivers, supported archive API behavior is the uncertainty.
- Step 2 handoff: **0.40 reaffirmed**, including planning, implementation, recovery verification and documentation. Step 1 not applicable; no historical estimates invented, measured labor or accepted outcome.

Follow-up to the archive README and Backup UI work, with distinct format/recovery scope. No incidental enhancement requests. Awaiting `Approved Step 2`; planning remains uncommitted and implementation has not started.

*— Codex · 2026-10-26 UTC*

Step 2 approved on 2026-10-26. Requirements unchanged; Step 3 is ready for review.

*— Revised by Codex · 2026-10-26 UTC*
