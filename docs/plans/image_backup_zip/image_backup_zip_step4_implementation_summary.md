> **Feature plan:** [Step 2](./image_backup_zip_step2_feature_description.md) · [Step 3](./image_backup_zip_step3_development_plan.md) · [Step 4](./image_backup_zip_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/image_backup_zip`; [ledger](../effort_estimation_trial.md). Scope v1 / R1 / P2.

Extended estimates: enabled.

**Cycle estimate: 0.40**

## Approval and entry

Step 3 approved. Planning commit `7256340b2` precedes implementation on `feat/image-backup-zip`; starting revision `5510efde63b6f5eae1970d836d363bb63403a2f2`. Step 4 entry, 2026-10-26: 0.40 reaffirmed, same scope and verified R1; Codex single exposed rater. Original stage-work estimates inherited: S1 0.18, S2 0.12, S3 0.08; these are not summed or measured time. No new dependencies or schema changes. Existing Backup improvements remain in the branch. No additional requests.

*— Codex · 2026-10-26 UTC*

## Stage 1 — Verify and restore ZIP image backups

**Original estimate: 0.18**

**Retrospective stage work: 0.18**

- Changes: signature-based format dispatch, ZIP entry preflight and bounded entry streams, CRC checks and expanded-space checks, followed by the existing common manifest/revision/image validation and atomic activation. Legacy TAR reader and public restore signature remain intact.
- Verification: initial image/restore/content-export selection 11/11 passed; strengthened ImageZipRestoreTest 3/3 passed. Real CLI ZIP/TAR verify/restore/retry, empty images, wrong revision and different existing store, traversal/absolute/backslash names, duplicate entries, links/FIFOs, encryption, corrupt/truncated payloads, per-entry/count/expanded-total limits, cleanup and PHP without ZIP support were exercised. A ZIP under 1 MiB expanding past 258 MiB is rejected before activation. PHP lint and diff check passed.
- Estimate details: 2026-10-26, original S1 scope/0.18 retained; no revision. Existing ZipArchive supported the required metadata/stream operations. Retrospective is separately judged, not measured time; rater/reference/exposure unchanged.
- Notes: no dependencies added. UI and download publication remain Stage 2. Stage 1 owns malformed-archive and common recovery coverage.

*— Codex · 2026-10-26 UTC*

## Stage 2 — Deliver ZIP through Backup

**Original estimate: 0.12**

**Retrospective stage work: 0.12**

- Changes: image export defaults to ZIP with an explicit optional TAR format; ZIP includes manifest.json and objects/, including an empty objects directory. Images use stored ZIP entries to avoid recompressing already compressed JPEG/PNG bytes. Backup advertises images.zip; the TAR route explicitly selects TAR. Headers follow the actual archive extension, and both paths share the same access classification.
- Verification: existing image/restore/content-export/Backup/import selection 23/23 passed; new real-HTTP ImageZipBackupTest 2/2 passed. Checked empty/populated archives, pinned/unpinned routes, MIME/disposition/no-store, external unzip extraction, restored byte identity, legacy TAR, POST 405, unknown route, wrong revision 409, damaged-image failure/retry, restricted access, unchanged source, unsupported formats and PHP without ZIP support. Diff check passed.
- Estimate details: 2026-10-26 original S2 scope/0.12 retained without revision; separate retrospective 0.12. Existing APIs and routing were sufficient; no new components. Shared reader validation remains Stage 1 evidence.
- Notes: both content archive formats now pair with ZIP through the existing content-export regression. Active documentation/browser fixtures are updated in Stage 3; no deployment performed.

*— Codex · 2026-10-26 UTC*
