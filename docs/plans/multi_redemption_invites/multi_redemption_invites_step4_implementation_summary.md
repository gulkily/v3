> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · [Step 2](./multi_redemption_invites_step2_feature_description.md) · [Step 3](./multi_redemption_invites_step3_development_plan.md) · Step 4
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
**Cycle estimate: 0.70**

Authorization: User said “approved step 3” on 2026-10-31. Backend-only v2; six stages, no frontend changes, dependencies, merge, push or deployment.
Branch: feat/multi-redemption-invites
Planning commit: 465d3b38
Step 4 entry: 2026-10-31, Codex single exposed rater; 0.70 reaffirmed, original baseline/R1/P2 retained. All three frozen R1 hashes verified. No measured labor or maintainer acceptance.
Stage originals: S1 0.12; S2 0.18; S3 0.20; S4/v2 0.08; S5 0.12; S6/v2 0.08. Scope and uncertainty inherited from approved Step 3; non-additive. V1 history preserved separately.
Reuse: Extend src/ForumRewrite/Canonical/InvitationRecord.php and InvitationRecordParser.php; GenericTextRecordParser already rejects duplicate headers. Extend Invitation/InvitationLedger.php and InvitationApprovalReplay.php; preserve Security/ApprovalAcceptance.php receipt authority. Extend Write/LocalWriteService.php and existing Http/IdentityApprovalAndInvitationApiController.php contracts. Existing tests and browser signing flows supply fixtures; no new endpoint or frontend component.
Completion: Signed APIs admit N distinct keys, refuse N+1, recover same-key retry and partial revocation, preserve replay/restore counts and unchanged browser entry.

*— Codex · 2026-10-31 UTC*

## Stage 1 - S1: Signed issuance contract

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes: Optional bounded issuance header in canonical parser/model; absent remains 1. Existing generic parser rejects duplicate headers; exact prepared-byte/signature checks will remain the API authority. Added format and upgrade boundary specification.
- Verification: php tests/run.php CanonicalRecordParsersTest — 53/53 passed; valid endpoints, malformed/overflow/duplicate limits and non-issuance rejection covered. Runtime issuance deliberately awaits S3 after replay validation; signature tampering checked there.
- Notes: No dependencies, schema or frontend edits. Original unchanged; retrospective is judgment, not measured labor.

*— Codex · 2026-10-31 UTC*

## Stage 2 - S2: Preserve accepted recipients

**Original estimate: 0.18**
**Retrospective stage-work assessment: 0.18**

- Changes: Shared ledger/replay recipient sets, duplicate-key idempotence, prospective revocation and receipt-aware acceptance. Missing multi-use receipts fail closed; accepted over-capacity evidence is rejected. Runtime ledger reads validated receipts. Existing single redeemed field retained for compatibility until API stage.
- Verification: CanonicalRecordParsersTest, ApprovalAcceptanceTest, ApprovalPolicyTest — 64/64 passed. Subsequent receipt-loss regression: ApprovalAcceptanceTest 6/6 passed. Covers legacy expiry, two accepted keys, same-second revocation, duplicate records, receipt loss and excess capacity. Initial test failures exposed malformed old test fixture bodies and a canonical-only assertion helper; corrected fixtures/assertions, retained real canonical parsing. Full/incremental rebuild consumers call the shared replay; end-to-end parity awaits S3/S5.
- Notes: S1 commit 7a33faee. No schema/dependency/frontend changes. Original estimate unchanged; no measured labor.

*— Codex · 2026-10-31 UTC*

## Stage 3 - S3: Atomic admission and recovery

**Original estimate: 0.20**
**Retrospective stage-work assessment: 0.20**

- Changes: Existing signed API accepts optional max_redemptions; strict input and exact-byte finalization. Final checks under existing write/publication lock count recipients and recheck revocation. Receipt-backed same-key recovery survives expiry/revocation and refresh failure without restoring unavailable membership. Existing approval recovery now verifies exact Git blob hashes (line-based command output previously mismatched invitation trailing blank lines). Read-only inspection preserves available/used and targeted fields, allowing authenticated retry on closed links with accepted recipients.
- Verification: Multi-recipient signed integration, direct approval recovery and policy suite 8/8 passed; legacy signed invitation test separately passed. Final same-key concurrent extension passed 1/1. Independent PHP processes race for last slot and same-key acceptance; one distinct winner, one same-key recovered result. Covers invalid limits, tampering, pre-commit rollback, committed refresh failure, lost-response retries, partial revocation, unavailable membership and rebuild grants. Initial failures exposed exact-byte recovery and inherited fork cleanup in the test harness; switched to independent processes and verified recovery.
- Notes: S2 commit 5701a13b. Browser/restore follow in S4/S5. No dependencies/schema/frontend changes. Original estimate unchanged; no measured labor.

*— Codex · 2026-10-31 UTC*

## Stage 4 - S4/v2: Existing-client compatibility

**Original estimate: 0.08**
**Retrospective stage-work assessment: 0.10**

- Changes: Real-browser API-created capped invitation fixture reuses existing browser fixture/router, Chrome, Playwright Core and shipped signing/identity/Lobby code. API usage documented. No frontend source changed.
- Verification: node tests/browser/invite_multi_redemption.mjs passes public and members-only targeted/manual entry, two recipients/third refusal, lost response and full/partial revocation recovery. node tests/browser/invite_zero_click.mjs passes its full public/members-only legacy flow including browser single-use/30-day issuance, expired links and actual v3 rebuild. InvitationIssuanceTest and BrowserSigningNormalizationTest 92/92 passed. Diff confirms zero public/templates changes.
- Limitation: First multi-use browser run passed public flows but failed members-only manual entry with “Invalid approval acceptance time.” A full rerun of the same condition passed both modes without code changes; cause remains unexplained. This is not claimed fixed. Keep this intermittent environment/timing observation visible for release acceptance.
- Notes: S3 commit 3e2130a0. Existing dependencies only. Stage retrospective reflects browser replay overhead; whole-cycle projection remains 0.70, independently judged, no measured labor. No visual/theme redesign involved.

*— Codex · 2026-10-31 UTC*

## Stage 5 - S5: Restore and compatibility proof

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes: Extended signed integration fixture through ordinary ImageBackup export, byte-preserving archive repack and v3 import-repository. Verified restored signed posts/signatures/receipts and same-key recovery; missing receipt blocks both inspection and rebuild. ContentImportPlanner and actual ContentImportRunner exclude invitation authority. Documented upgrade, archive layout, roll-forward and recovery procedures.
- Verification: Signed multi-redemption integration plus ContentImportPlannerTest and ArchiveExportTest — 26/26 passed. ContentImportRunner has no standalone class matching that filter; it is exercised directly by the integration fixture. Before/after rebuild and restore keep accepted grants and exhausted/revoked states. Legacy invitation restore is covered by the existing signed invitation test (S3).
- Limitations: Existing public export repository/ wrapper requires repacking to top-level records/ for import-repository; no direct format interoperability fix. Snapshot verification is not a full Git-history restore claim; preserve a separate full-history backup. No deployment/old-reader rollback verified.
- Notes: S4 commit 60766d25. No import-policy expansion, dependencies, schema or frontend edits. Original estimate unchanged; no measured labor.

*— Codex · 2026-10-31 UTC*
