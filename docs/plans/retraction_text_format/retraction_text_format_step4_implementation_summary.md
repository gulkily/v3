> **Feature plan:** [Step 1](retraction_text_format_step1_solution_assessment.md) · [Step 2](retraction_text_format_step2_feature_description.md) · [Step 3](retraction_text_format_step3_development_plan.md) · Step 4

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/retraction_text_format`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_text_format). Extended estimates: enabled.

**Cycle estimate: 0.60**

## Entry — 2026-10-25 UTC

Step 3 explicitly approved. Branch `feature/retraction-text-format`; planning commit `75ec624b`. Scope v3/Option B, original baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`, R1/P2; exact frozen hashes previously verified and rechecked at entry. Codex single exposed rater; **0.60 reaffirmed**, not measured labor. Original S1–S6/v1 stage estimates: 0.15, 0.20, 0.15, 0.20, 0.10, 0.15; scopes/drivers inherited from Step 3, Stage 6 owns shared acceptance. No old-retraction compatibility, dependencies, deployment or other-family conversion. Planning diff check reported Markdown hard-break trailing spaces; normalize these in the next stage without changing content. Policy/audit/ledger remain outside the planning commit.

*— Codex · 2026-10-25 UTC*

## Stage 1 - Text contract

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: strict RetractionReceipt codec/path helpers and canonical specification; current writer still unchanged. Exact request/signature hashes, structure and unknown/duplicate-header validation. Normalized planning Markdown whitespace.
- Verification: `php tests/run.php RetractionReceiptTest` **3/3**, including actual GPG signature verification after round trip, substitution/malformed cases and no prose retention. Diff checks passed.
- Notes: S1/v1 scope unchanged; local receipt hashes do not confer archive authority. Filesystem/missing-file cases belong to Stage 2. Zero dependencies.

*— Codex · 2026-10-25 UTC*

## Stage 2 - Atomic retained evidence

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: acceptance reads all three bound text files; deletion installs request/signature before the receipt and only then deletes the post. Atomic file replacement avoids partial receipts. Scoped commit/restore preserves unrelated staging; complete receipts support interrupted retry.
- Verification: receipt/transaction suites **6/6**. Injected exceptions and actual subprocess exits at all six write/delete/stage boundaries; rejected Git commit, five-path successful commit, missing signature, repeat/no-extra-commit and unrelated index checks pass.
- Notes: interruption test exposed staged-deletion paths disappearing from the index; include HEAD paths in the commit and avoid re-adding already staged absent paths. Fixed and rerun. No new journal, dependencies or history migration. Stage 1 commit `d4c5cb1c`; S2/v1 unchanged. Existing legacy/application fixtures are reconciled in dependent stages; this intermediate commit is not releasable.

*— Codex · 2026-10-25 UTC*

## Stage 3 - Signing and retry integration

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: author/operator finalize retains detached request/signature and exposes `record_path`, `signature_path`, `acceptance_path`. Removed old request conversion from prepare/finalize; incomplete unaccepted files cannot bypass fresh authorization/signature checks. Retry marks derived state stale and refreshes without the original post. Attribution now stays a plain internal value instead of a JSON trailer round trip.
- Verification: six focused signed API tests passed: deletion/path inspection plus actual rebuild, author invalid-signature/unauthorized actions, seven account-attribution scenarios, enqueue/artifact/refresh failures and no-new-commit retry. Operator privilege-recheck scenario passes its signing/revocation/rebuild checks but its later import assertion fails on the new receipt family; Stage 4 owns that known integration dependency.
- Notes: S3/v1 unchanged; Stage 2 commit `95fc49bb`. No new UI or dependencies. Source/import contract not yet releasable.

*— Codex · 2026-10-25 UTC*

## Stage 4 - Source, rebuild and import

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: explicit receipt family in source/catalog/activity; canonical retraction loading requires bound local acceptance. Archive requests/signatures/receipts are duplicates only when the complete set matches local acceptance; otherwise rejected. Historical source response now preserves exact bytes instead of line-trimming Git output.
- Verification: reader/import/recovery/export and signed source/API checks **26/26**; strengthened transaction/import case separately **1/1**. Actual `v3 rebuild`, independent replies, listings/profile/activity counts, original archive reimport, current and historical request/signature/receipt byte equality, untrusted receipts, tampered signatures, incomplete sets and local duplicates covered.
- Notes: S4/v1 unchanged. Stage 3 commit `a726ef3d`. Source byte test exposed existing `exec` line trimming; fixed shared blob response rather than weakening equality. No core read-model schema/query changes or dependencies. Old suppression-only requests are no longer acceptance evidence.

*— Codex · 2026-10-25 UTC*

## Stage 5 - Cleanup and maintenance

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes: sweep cutoff follows text receipts; new `.txt` requests are not reported as legacy migration work. Retired retraction conversion while preserving source-removal migration. CLI/reference/spec clarify the boundary and replace JSON-trailer documentation.
- Verification: maintenance/sweep/exchange/acceptance suites **18/18**, including real CLI preview/status/run, artifact retirement, late LLM writes, cron discovery, WAL/interruption and repeat cleanup. Existing source-removal acceptance test extended for actual migration/idempotence **1/1**. CLI help inspected.
- Notes: S5/v1 unchanged; Stage 4 commit `24f5bc8f`. Historical retractions remain untouched and unsupported. No schema changes or dependencies.

*— Codex · 2026-10-25 UTC*

## Stage 6 - Acceptance and documentation

**Original estimate: 0.15**

**Retrospective stage work: 0.20**

- Changes: completed policy in AGENTS/CONTRIBUTING and prioritized audit; F01/R04 closed, other conversions remain open. Finished format/API/recovery docs and reconciled fixtures. Removed the last historical Git/trailer acceptance fallback; retained current committed operator checks. Exact signed finalize replay now recovers after prepared-token cleanup, while changed evidence is rejected.
- Verification: final affected PHP regressions **117/117**; signed API/source checks **16/16**; strengthened actual repository ZIP export byte-preservation/normal-retry check **1/1**. Covers ordinary rebuild, public projections, source current/history, original-archive reimport, missing/tampered evidence, transaction interruption, partial refresh, high-traffic/static/offline publication and actual offline update-reader regressions. No full-suite claim.
- Browser: actual author/operator/account/partial-retry flows passed with OpenPGP verification of retained detached signatures and all three current-source files. All 13 themes at phone/desktop widths passed overflow checks; separate keyboard activation, Escape/focus restoration and touch checks passed. Inspected desktop and mobile raw receipt screenshots plus Word97 phone retraction page. Raw mobile text uses the browser's native scaled text presentation; no source-view redesign. No navigation/UI copy changed.
- Corrections: browser fixture initially tried reading a response body after navigation invalidated it; capture the actual response in route interception before forwarding it. Updated obsolete embedded-signature/legacy-acceptance fixture assumptions. New API replay and legacy rejection tests pass. Actual export contains request/signature/receipt unchanged and no deleted post.
- Notes: Stage 5 commit `53eb2e5a`. Scope S6/v1 unchanged; retrospective increase reflects final recovery/fixture corrections, not new features or measured time. Zero dependency/manifest/lock/vendor delta. Diff/lint/link checks passed. Physical devices, production deployment, complete repository suite and maintainer fit/acceptance remain unverified or pending; no production repositories changed.

*— Codex · 2026-10-25 UTC*

## Study closeout and release limits

2026-10-25 UTC: six planned/six completed stages locally on `feature/retraction-text-format`, scope v3/Option B, baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`, R1/P2, Codex single exposed rater. Planning `75ec624b`; Stages 1–5 `d4c5cb1c`, `95fc49bb`, `a726ef3d`, `24f5bc8f`, `53eb2e5a`; Stage 6 is this summary's commit. No stage scope changes or split outcomes.

**Original cycle projection: 0.60. Retrospective scope assessment: 0.70.** Independently judged, not a sum of stages or measured labor. Exact frozen R1 hashes verified. Earlier v1 0.70/v2 0.45 and selected v3 0.60 projections remain historical; this retrospective is distinct. S1–S6 originals retained; retrospectives 0.15, 0.20, 0.15, 0.20, 0.10, 0.20. Maintainer comfortable-fit/acceptance pending; no accepted-count increment. Merge/push/deployment not performed.

Previous retractions remain untouched and unsupported. New-record transaction/retry recovery is verified; older JSON-only code is not a compatible rollback. Other canonical JSON families remain audit follow-ups, not delivered conversions. History purge remains outside this cycle.

Temporary evidence: `/tmp/retraction-text-regressions.log`, `/tmp/retraction-text-api.log`, `/tmp/retraction-text-browser.log`, `/tmp/retraction-text-corrections.log`, and `/tmp/retraction-browser-evidence/`; durable outcomes are recorded above.

*— Codex · 2026-10-25 UTC*

## Merge verification

User authorized merge and push. Integrated `origin/main` at `95e6c17a`, preserving
its schema version 27, viewer-tag projection/indexes, archive README and Railspike
beta.4. Retained both sets of planning/study history and the readable-format policy.
Combined-tree checks: **138/138** focused PHP regressions (including schema rebuild,
viewer tags, archive exports and Railspike sync), **16/16** signed API/source tests,
actual browser author/operator/account/partial flows and all 13 themes at phone and
desktop widths, plus menu keyboard/Escape/focus/touch checks passed. PHP lint passed
for all 54 added/modified PHP files relative to main; diff checks passed. No full-suite
or deployment claim. Study scores and pending maintainer fit judgment are unchanged.
Evidence: `/tmp/retraction-merge-{regressions,api,browser,menu}.log`.

A concurrent push added Backup-page cleanup at `21798f60`; merged cleanly after
the initial non-fast-forward push rejection. Additional BackupPage/LocalApp smoke
checks: 150 run, 147 passed, 3 failed. The server-start shorthand check passed on
isolated retry; continuation grouping and the stale archive-filename reflection
test both reproduce on untouched `21798f60`. These incoming-main failures are not
claimed fixed. Logs: `/tmp/retraction-merge-backup.log`,
`/tmp/retraction-merge-baseline-followup.log`, `/tmp/retraction-merge-port-retry.log`.
