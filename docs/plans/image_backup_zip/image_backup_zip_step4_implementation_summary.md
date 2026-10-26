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

## Stage 3 — Document and verify ordinary use

**Original estimate: 0.08**

**Retrospective stage work: 0.08**

- Updated CLI help/reference, image recovery runbook and the single bundled repository README to use ZIP, retaining explicit legacy TAR support. Updated import, browser and Apache checks. No dependencies added or updated.
- Passed 31 focused PHP cases across image content, ZIP restore/export, repository export/validation/import, instance import, Backup and two LocalAppSmoke cases. This is not a full-suite result. CLI help and diff checks passed.
- Chromium end-to-end passed: signed image posts, actual ZIP browser save, filename/MIME, external unzip validation/extraction, matching content export, CLI verify/restore/rebuild, byte identity and restored rendering, subsequent publishing, feature flag and membership gates. Fixture: `/tmp/image-content-browser-u0mho8`; output `/tmp/image-zip-browser.log`. Apache/mod_php passed ZIP/TAR responses, headers, image bytes, flag/membership gates and oversized-upload rejection (`/tmp/image-zip-apache.log`).
- Browser verification initially exposed a test race (preview visible before preparation status settled); the harness now waits for final status. It also exposed a stale writable-restore fixture assumption: content exports omit Git history. After verifying/restoring/rebuilding and reading the history-free snapshot, the harness explicitly initializes Git before testing new writes. Both failures and their test-only corrections are retained here; final browser run passed.
- All 14 themes passed phone/desktop checks for populated, empty and long-value Backup states, aliases, download actions and revision-failure recovery. Metadata and recent items remain present. Visual evidence and limitations are in the UI audit. Shared malformed/mismatched/no-overwrite recovery is covered by Stages 1–2.
- Estimate details: 2026-10-26, original S3 0.08 retained without revision; separate retrospective 0.08. Fixture corrections fit the planned runtime/fixture uncertainty. Same exposed rater and frozen R1/P2; no measured time inferred.

*— Codex · 2026-10-26 UTC*

## Study closeout and handoff

Locally complete; full suite, physical devices, deployment and maintainer acceptance remain unverified. No merge or push performed. Three planned stages completed, each with its summary in a stage-scoped commit, following planning-only commit `7256340b2`. Stage 1: `a74b1c97e`; Stage 2: `29ed6b06d`; Stage 3 is this closeout commit. Four cycle commits meet the planning-plus-stages requirement.

**Cycle projection: 0.40 retained. Retrospective cycle scope assessment: 0.40.** Original stage estimates and separate retrospective judgments are preserved above; no scores are measured labor or additive totals. Stable `v3/image_backup_zip`, scope v1, original baseline, exposed single Codex rater, R1/P2 and extended mode remain unchanged. Frozen reference hashes reverified. Maintainer comfortable-fit/acceptance pending; no independent outcome or accepted-count increment. Ledger closeout included in the final stage commit.

Normal download/extraction/CLI restoration and recovery contract passed locally. Dependency inventory delta is zero; no package manifests, locks, vendored components or core read-model schema changes. Deployment, external services and migrations are inapplicable to this local implementation. If ZIP support is absent in a serving/CLI runtime, enable the existing PHP ZIP extension and retry as documented; no automatic installation or mislabeled fallback is introduced. Debug mode remains disabled.

*— Codex · 2026-10-26 UTC*
