> **Feature plan:** [Step 1](unified_removal_format_step1_solution_assessment.md) · [Step 2](unified_removal_format_step2_feature_description.md) · [Step 3](unified_removal_format_step3_development_plan.md) · Step 4

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/unified_removal_format`; [ledger](../effort_estimation_trial.md#cycle-v3unified_removal_format).
Extended estimates: enabled.

**Cycle estimate: 0.70**

## Entry — 2026-10-29 UTC

Step 3 explicitly approved; planning commit `5143ab6f`, branch `feature/unified-removal-format`. Scope v2/A, original baseline/R1/P2 retained; single exposed Codex rater. Step 3 whole-cycle 0.70 reaffirmed. Original S1–S7/v2 stage work: 0.10/0.15/0.15/0.10/0.15/0.10/0.15; non-additive, prior v1 values in plan. No old-format compatibility or migration; no dependencies/deployment/data cleanup. All stages pending at entry. No measured labor or accepted outcome.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Receipt contract

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes: strict common codec/specification, explicit authority profiles and repeated label blocks. No old-format reader. Signed profile binds exact request/signature; source hash remains optional for signed requests, required for local actions, matching the existing signed target contract without inventing hashes.
- Verification: `php tests/run.php RemovalReceiptTest` 2/2; malformed/duplicate fields, invalid dates, contradictory authority, duplicate label bindings and anonymous resolution covered. Whitespace checks pass.
- Notes: S1/v2 unchanged; no writer switched yet. No dependencies. Initial runner did not discover the new suite until explicitly registered; final run passed.

*— Codex · 2026-10-29 UTC*

## Stage 2 - Common interpretation

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: common accepted-removal resolver replaces array-union precedence in label aggregation, import and rebuild; preserves signed attribution/evidence while combining compatible label bindings. Conflicting structure/source/label hashes throw explicit errors; local resolution cannot undo signed deletion.
- Verification: `php tests/run.php RemovalReceiptTest ReadModelPostRetractionsTest ReadModelThreadLabelsTest` 13/13, including actual rebuild/content suppression and retained independent replies. Combination/conflict tests pass. Full new-format transition/retry matrix follows writer integration and Stage 7.
- Notes: S2/v2 unchanged; no core table/index/query change, so no schema increment. Existing writers are switched in subsequent stages. Zero dependencies.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Operator deletion and repair

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: local receipts now use the common codec and `.txt` paths; removed JSON decoding and Git-history acceptance reconstruction. Recovery journals explicitly identify the new format and validate before replay. Changed accepted label hashes and incompatible signed evidence are rejected before mutation.
- Verification: read-model retraction suite 8/8; four actual CLI deletion/repair/failed-commit retry scenarios 4/4; new live-resolution → deletion test 1/1 verifies retained bindings, independent reply, readable receipt and rejection of an old unversioned journal without deleting the source.
- Notes: S3/v2 unchanged. CLI source route assertions await Stage 5 registration; no release claim. Existing source-byte discovery for explicit repair remains separate from removed acceptance reconstruction. Zero dependencies.

*— Codex · 2026-10-29 UTC*

## Stage 4 - Signed retraction

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes: signed receipt wrapper uses the shared codec exclusively, preserving exact detached request/signature and result paths; incompatible local evidence blocks signed mutation. No previous text format fallback remains.
- Verification: receipt/acceptance/transaction suites 10/10, including actual GPG round trip and process-exit fault injection. Extended receipt suite 4/4 tests real signed/local files, unioned bindings, conflicting source hash and old-text rejection. Five actual signed API scenarios pass: account attribution, operator privilege recheck, author authorization/bad signature, canonical deletion/replay and refresh failure/retry.
- Notes: S4/v2 unchanged. Logs `/tmp/unified-signed-tests.log`; no new dependency or UI. Archive registration for local receipts remains Stage 5.

*— Codex · 2026-10-29 UTC*

## Stage 5 - Source and archives

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: text local receipts registered in source/catalog/import and labeled in Activity; previous JSON paths are not canonical imports. Exact local acceptance remains required for imported duplicates.
- Verification: archive/export/import planner/recovery run 27/27 unaffected checks passed; source test initially used the wrong historical route and failed, corrected to `/source/blob/<commit>/...` then passed with exact current/historical bytes and normal deletion retry. New actual ZIP export/unpack test passes readable receipt equality, local duplicate, empty-destination rejection and tamper rejection.
- Notes: S5/v2 unchanged. `/tmp/unified-source-tests.log` retains initial 27/28 run; corrected source and added archive tests pass separately. No dependency delta.

*— Codex · 2026-10-29 UTC*

## Stage 6 - Maintenance and recovery guidance

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes: maintenance cutoff uses validated deleting receipts, excluding resolution-only entries. Removed legacy migration implementation; removed migrate from help and return an explicit unsupported-format error for the old command. Updated retraction/shared receipt/schema/operator/CLI references and replaced obsolete migration assertions with old-format rejection checks.
- Verification: parser/maintenance/sweep/acceptance suites 62/62; added resolution-only versus local-deletion artifact/exchange cleanup scenario 1/1. Actual CLI help lists preview/run/status; migrate exits 1 with clear no-migration guidance. New-action repeat cleanup remains idempotent.
- Notes: S6/v2 unchanged. Internal recovery journals remain JSON machine state. No old files migrated/deleted; no dependencies. `/tmp/unified-maintenance-tests.log`.

*— Codex · 2026-10-29 UTC*
