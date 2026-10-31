> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · [Step 2](./multi_redemption_invites_step2_feature_description.md) · [Step 3](./multi_redemption_invites_step3_development_plan.md) · Step 4 (not authorized)
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.70**

## Completion Contract

Existing signed invitation APIs issue a capped link, admit N distinct keys, refuse N+1, recover retries without extra use and revoke remaining capacity after partial use. **Backend only: no templates, CSS, browser scripts, UI copy, controls or dialogs change.** Browser issuance stays single-use/30-day; existing Lobby redemption must handle API-created links unchanged. Full restore preserves grants/counts; content import still excludes invitation authority. Release requires API/race/replay/restore and existing-client compatibility evidence, not a new UI. No deployment or old-reader rollback promise.

## Key Risks

**High risk:** over-admission, lost grants and reopened capacity. Validate shared replay/acceptance semantics before enabling issuance; serialize final checks with existing write locks and preserve acceptance receipts. **High risk:** older software assumes one recipient; upgrade all writers/readers before multi-use creation, document roll-forward recovery afterward. No rewriting signed legacy records. Inspection must not block authenticated same-key recovery solely because capacity is exhausted or later revoked.

## Stage 1 — S1: Signed issuance contract
**Estimate: 0.12**

- Goal/dependencies: fix bounded issuance semantics first; none.
- Expected changes: optional signed `Max-Redemptions`, API `max_redemptions`; absent means 1; supported integers 1–1000. Document legacy/new-reader boundary before runtime enablement.
- Verification approach: canonical round-trip, unchanged legacy bytes, malformed/duplicate/overflow limits and tampering.
- Risks: silent reinterpretation; reject invalid limits rather than clamp; no frontend edits.
- Canonical components/API contracts touched: `InvitationRecord`, `InvitationRecordParser`, existing prepare/finalize contracts; no new endpoints/schema planned.
- Estimate details: stage work; parsing/compatibility driver, older-reader ambiguity resolved by explicit upgrade boundary.

## Stage 2 — S2: Preserve accepted recipients
**Estimate: 0.18**

- Goal/dependencies: authoritative used-key sets and durable approvals; S1.
- Expected changes: extend `InvitationLedger` recipient lookup and `InvitationApprovalReplay`; share lifecycle decisions without replacing acceptance authority. Partial revocation remains prospective.
- Verification approach: legacy/new mixed histories, same-second ordering, duplicate recipients, receipts, expiry/revocation and full/incremental parity.
- Risks: authored timestamps misorder acceptance; retain verified receipt semantics, stop on contradictory evidence rather than reopen slots.
- Canonical components/API contracts touched: ledger, replay/order, `ApprovalAcceptance`, existing read-model consumers; concept correction here.
- Estimate details: stage work; replay consistency driver, receipt-order edge cases are primary uncertainty.

## Stage 3 — S3: Atomic admission and recovery
**Estimate: 0.20**

- Goal/dependencies: issue/redeem/revoke through existing signed APIs; S2.
- Expected changes: wire limit preparation/finalization; recheck capacity/authority under write lock; recover same-key acceptance without restoring separately revoked membership; allow revocation after partial use. Inspection retains existing response fields/status semantics for unchanged clients, without exposing recipient identities.
- Verification approach: N/N+1, concurrent last slot, simultaneous same-key claims, lost response, failed write/derived refresh and revoked approval.
- Risks: stale preparation or duplicate grant; count committed acceptance only, reconcile derived state on retry; inspect must permit authenticated recovery checks.
- Canonical components/API contracts touched: `LocalWriteService`, `IdentityApprovalAndInvitationApiController`, existing approval commit/synchronization and lock.
- Estimate details: stage work; concurrency/retry driver, failure injection uncertainty.

## Stage 4 — S4/v2: Existing-client compatibility
**Estimate: 0.08**

- Goal/dependencies: API-created links work with existing clients; S3. Supersedes UI stage S4/v1 (0.16), retained in scope history.
- Expected changes: backend-only compatibility corrections if needed; signed API example and integration tests. No frontend source edits.
- Verification approach: unchanged browser issues one-use invites; API-created capped links admit two recipients, refuse a third and recover same-key retries. Check targeted/manual, partial revoke and public/members-only flows.
- Risks: new status meanings break clients; preserve existing response contracts, stop for scope review if frontend changes are unavoidable.
- Canonical components/API contracts touched: existing inspect/prepare/finalize contracts; browser suites exercise unmodified Invite/Lobby/signing/auth code.
- Estimate details: stage work; API/client compatibility driver, recovery-contract uncertainty.

## Stage 5 — S5: Restore and compatibility proof
**Estimate: 0.12**

- Goal/dependencies: no capacity reset or foreign authority import; S3–S4.
- Expected changes: add mixed-history repository restore/export and content-import exclusion fixtures; document upgrade/rollback/restore procedure, without broadening import policy.
- Verification approach: before/after rebuild and full restore identical recipients/counts; import neither activates excluded invites nor changes destination capacity; failed restore cannot publish fresh capacity.
- Risks: partial authority evidence; preserve existing trusted-restore rules, refuse incomplete invitation state rather than treating it as unused.
- Canonical components/API contracts touched: repository backup/restore, `ContentImportPlanner`, `ContentImportRunner`, replay; invitation reference/spec.
- Estimate details: stage work; integration evidence driver, restore-fixture completeness uncertainty.

## Stage 6 — S6/v2: Backend release verification
**Estimate: 0.08**

- Goal/dependencies: prove backend Completion Contract; S1–S5.
- Expected changes: API/operator/reference docs, lifecycle concept and study closeout; frontend files remain unchanged.
- Verification approach: signed API examples, focused regressions and existing browser compatibility flows; verify diff contains no frontend edits. Record production/browser omissions; no redesign/theme matrix.
- Risks: API-only tests miss existing consumers; retain real-browser compatibility checks; deployment stays separate.
- Canonical components/API contracts touched: invitation/backend/browser tests, `docs/concepts/approval_acceptance.md`, new lifecycle entry and reference/plans.
- Estimate details: stage work; shared final regression/docs owner, environmental test uncertainty. Supersedes S6/v1 (0.12), preserved in history.

## Execution and checkpoint

User expressly revised scope to backend-only on 2026-10-31 UTC. User subsequently reapproved Steps 1 and 2 for v2. The six-stage backend-only plan is ready for Step 3 review; Step 4 remains unauthorized. S1/S2/S3/S5 scopes and estimates remain 0.12/0.18/0.20/0.12; S4/v2 0.08 and S6/v2 0.08 replace frontend-oriented work. [V1 history](multi_redemption_invites_scope_v1_history.md) retains all earlier scopes, stage originals and 0.85 projection. Mockups are superseded reference artifacts, not deliverables for implementation.

Each stage targets ≤1 hour; split before implementation if needed; >8 stages/day requires rescoping. Commit stages with summaries. No SQL schema change expected; discovered schema/query-contract changes follow CONTRIBUTING version/rebuild/regression rules. Concepts: replay changed, acceptance/signed-review reused, backend lifecycle entry added in S6.

Scope v2/original baseline/R1/P2: **0.70 projected total**, independently judged, not the stage sum. Reduction reflects removed frontend work; atomic admission/replay risks remain. Frozen hashes reverified; Codex single exposed rater; no measured work/accepted outcome. Planning only; links/diff checked, no runtime tests. Step 3 handoff reaffirmed 2026-10-31 UTC after explicit v2 Step 2 approval; total 0.70 and all six stage estimates unchanged. Await Approved Step 3; no implementation or new commit/push authorized.

*— Revised by Codex · 2026-10-31 UTC*
