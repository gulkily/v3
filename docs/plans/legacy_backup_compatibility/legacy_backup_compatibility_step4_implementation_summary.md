> **Feature plan:** [Step 1](./legacy_backup_compatibility_step1_solution_assessment.md) · [Step 2](./legacy_backup_compatibility_step2_feature_description.md) · [Step 3](./legacy_backup_compatibility_step3_development_plan.md) · [Step 4](./legacy_backup_compatibility_step4_implementation_summary.md)

Debug mode: enabled

## Scope and state

- Steps 1–3 explicitly approved. Branch: `fix/legacy-backup-compatibility`; planning commit: `099870a5`.
- Application work follows four approved stages. No deployment or production import is authorized. Production/WSL acceptance remains pending until verified there.
- Requests: diagnose/prevent recurrence (implementation authorized); preserve remote findings (saved unchanged; include with operator documentation stage).
- Stages 1–4 complete locally; production deployment and named WSL acceptance remain pending.

## Stage 1 - Legacy-compatible backup validation

- Changes: Reuse canonical loading and batch history preloading during image-reference scans; disable filesystem-date fallback for backups through an optional constructor flag preserving existing callers. Attach record paths to parse/reference errors. Retain image integrity checks and original bytes.
- Verification: `php tests/run.php ImageContentTest CanonicalRecordParsersTest LegacyTimestampImportTest` passed (55 tests). Covers Git/metadata dates, 40-post scan under 10 seconds, supported dated-path rename, absent provenance, stale metadata, invalid path, symlinks, legacy image tar/ZIP/image exports, unchanged archived bytes, missing images, and existing corrupt-image checks. `git diff --check` passed.
- Notes: Initial test runs exposed two fixture errors (unsupported rename path and reading multiple Git date lines); corrected before passing. No production evidence inferred. No schema/UI changes; those checks are inapplicable.

## Stage 2 - Safe source errors and accurate recovery guidance

- Changes: Read at most 4 KiB from plain-text, unencoded error responses under the download deadline, reject invalid UTF-8, normalize Unicode/terminal controls, and display at most 1 KiB of detail. Non-text errors retain HTTP status and retry guidance. Resume advice is emitted only when the selected destination has a pending import journal.
- Verification: `php tests/run.php ImportInstanceCommandTest ContentImportRecoveryTest` passed (11 tests). Includes bounded slow/oversized/HTML/binary/empty/control-filled failures, unchanged destination and no journal after 409, preserved independently pending journal, failed-publication advice, and successful offline resume. `git diff --check` passed.
- Notes: Stage 1 commit `a2b7298b`. Production/WSL checks pending. Existing terminal sanitization remains in place; browser UI is unchanged.

## Stage 3 - HTTP and import acceptance

- Changes: Added real application-route coverage for committed legacy text/image posts, portable metadata, an existing image-store directory, tar/ZIP/image downloads, preview/import, preserved bytes/dates, published tag and served thread content, repeat import, and exporter 409/missing-image failures.
- Verification: Focused new HTTP test passed. `php tests/run.php` completed: **1377 run, 1372 passed, 5 failed**. All backup/import/publication/recovery tests passed. `git diff --check` passed. Full-suite output: `/tmp/legacy-backup-full-tests.log` (temporary local evidence).
- Baseline failures: All five reproduce individually on unchanged `main` (`4c521b74`) in a temporary detached worktree: `ApprovalSeedCommandTest::testApprovalIsEffectiveForExistingAuthenticatedMemberOnNextRequest`, `AgentIdentityServiceTest::testReplyAgentBootstrapCreatesApprovedIdentityAndPrivateKeyOutsidePublicArtifacts`, `LocalAppSmokeTest::testBuildStaticCommandReportsProgressAndArtifactSummary`, `LocalAppSmokeTest::testSharedStaticRefreshCommandKeepsDetailArtifactsWithoutRebuildingTheReadModel`, and `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts`. Baseline logs: `/tmp/legacy-backup-baseline-tests.log`, `/tmp/legacy-backup-baseline-additional-tests.log`, `/tmp/legacy-backup-baseline-timing-tests.log`. Temporary worktree removed. These unrelated assertion failures remain follow-up work; the full suite is not green.
- Notes: Stage 2 commit `77f391f2`. Fixture correction: static detail pages are disabled by default, so verify published tag output plus the actual served thread. Release SQLite inspection cursors before starting the target HTTP request. An invalid timestamp fails application startup with 503 before export; use a malformed image reference to exercise the exporter's path-bearing 409. No production/WSL success claimed.

## Stage 4 - Operator recovery and incident handoff

- Changes: Added bounded endpoint diagnosis, legacy-path/history inspection, source deployment and WSL preview/import/repeat instructions, image/provenance caveats, and journal-specific recovery guidance to the runbook and CLI/operator references. Tracked terminal-copy verification in the UI copy audit checklist. Included the user-requested source findings unchanged at `docs/plans/zenmemes_import_http409_findings.txt`.
- Verification: Plan navigation, new runbook links, Bash snippet syntax, and `git diff --check` passed. Local fixed-route acceptance passed in Stage 3. At **2026-10-20 22:51:31 UTC**, the unauthenticated live source still returned 409 with `Backup failed: Missing required post header: Created-At`. The updated CLI against that source, using an initialized disposable destination and `--dry-run`, correctly displayed the source detail, omitted resume advice, and created no journal/database; temporary destination removed.
- Notes: Stage 3 commit `4235376b`. **Pending:** deploy the source-side fix through the authorized release process, update the importing checkout for diagnostics, verify a successful public archive, then obtain the user's actual WSL preview/import/repeat and content/date checks. No production content was changed. This implementation is not evidence of incident recovery.

## Final verification status

- **Passed:** legacy compatibility and provenance restrictions; actual tar/ZIP/image HTTP downloads; CLI preview/import/repeat; preserved post bytes/dates; published/served content; malformed reference and missing/corrupt image rejection; safe bounded error details; destination preservation; interrupted-merge recovery; documentation/links/diff checks.
- **Failed (baseline-confirmed):** full suite, 1372 passed / 1377 total; all five failures listed in Stage 3 reproduce on unchanged `main` and remain unrelated follow-ups. Existing account-template `identityHint` warnings also appeared; that template is unchanged.
- **Pending:** source deployment and successful live zenmemes.com archive, named user's WSL recovery evidence. The live source is still failing; no deployment access or authorization was assumed.
- **Inapplicable:** schema migration/rebuild acceptance (no read-model change), browser/phone/theme/icon layout checks (no browser UI or navigation change), production writes (not authorized).
- All approved implementation requests are delivered locally. Original source findings preserved. No changes to `todo.txt`, strict parser contract, original records, or deployed systems. No merge/push was performed at the initial implementation handoff.

## Main integration — October 21, 2026

- Request: User explicitly authorized merging into `main` and pushing. Integrated the feature with updated `origin/main` (`02512b8e`, 52 commits beyond the original base); resolved the sole conflict in `docs/plans/README.md` by retaining both sets of feature links.
- Verification: Focused merged-tree backup/parser/legacy/HTTP-import/error/recovery checks passed (61 tests), including the real source download, preview/import, original dates/bytes, and zero-addition repeat. Log: `/tmp/legacy-backup-merge-focused-tests.log`. `git diff --cached --check` passed. The first broader rerun was stopped during repeated publication because upstream now builds 1349 documentation pages plus 396 browse pages per release; focused acceptance includes that updated publication path. No new full-suite pass is claimed.
- Deployment and the user's production WSL recovery remain separate from this authorized Git integration.
