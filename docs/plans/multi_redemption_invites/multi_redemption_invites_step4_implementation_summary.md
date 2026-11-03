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

## Stage 6 - S6/v2: Backend release verification

**Original estimate: 0.08**
**Retrospective stage-work assessment: 0.10**

- Changes: Added invitation lifecycle concept, completed API/operator documentation, plan navigation/index and study records. Confirmed approval acceptance/review concepts remain aligned; replay changes documented in S2.
- Verification: Final focused PHP run 77/77 passed (canonical parsers, acceptance, policy, history, receipt maintenance, import recovery and three signed-write integration flows). Earlier client checks 92/92 and export/import checks 26/26 passed; counts overlap and are not summed as unique tests. Real Chrome public/members-only capped and legacy suites passed, including normal CLI rebuild. All six changed production PHP files lint; browser fixture syntax and whitespace checks pass. Current plan/spec/reference links and concept source paths resolve. Broad scan of 650 local Markdown links found four historical missing targets: three superseded v1 mockup PNGs and one unrelated ledger link; preserved, not current deliverables.
- Scope: Zero changes to public/, templates/, schema, manifests, locks or dependencies. No todo.txt edit. No full-suite, physical-device, live-site, deployment, old-reader rollback or full-history archive-restore claim.
- Notes: S5 commit da3454a4. Stage original unchanged; retrospective reflects final cross-consumer checks/documentation, not measured labor.

## Closeout and study outcome

Status: Six of six stages implemented and locally verified against backend-only v2; release/maintainer acceptance pending.
Authorization: Approved Step 3; no merge, push or deployment requested.
Planning commit: 465d3b38
Stage commits: S1 7a33faee; S2 5701a13b; S3 3e2130a0; S4 60766d25; S5 da3454a4; S6 is the closeout commit containing this record.
Original baseline: 2e17c971308a710a540c905e738048c4f51e53ca plus separately authorized invitation cleanup, preserved from intake.
Study checkpoint: 2026-10-31 UTC; railspike-effort / v3/multi_redemption_invites; v2; original baseline/R1/P2; Codex single exposed rater. Step 4 entry projection 0.70 retained, no prospective revision. V1 0.85 and original stage scopes remain in history.
**Retrospective cycle scope assessment: 0.80**
Assessment: Independent whole-cycle judgment, not stage sum or measured labor; replay/receipt recovery and full browser/restore proof required more ordinary correction than projected. No independent rater or reliable active-work measurement.
Maintainer comfortable-fit judgment: Pending; no accepted-outcome increment. Deduplicate worktree copies under the same cycle ID.
Remaining limitations: First browser run's members-only manual acceptance-time error did not recur on full replay; cause remains unexplained, not fixed. Export-to-import wrapper mismatch requires documented repacking. Full suite, production-scale behavior and deployment remain unverified. No follow-up implementation or scope expansion authorized.
Debug report: [Step 4 diagnostics](multi_redemption_invites_debug_log.md#step-4-local-closeout--2026-10-31-utc).

*— Codex · 2026-10-31 UTC*
