> **Feature plan:** [Step 1](./feature_flags_security_review_cleanup_step1_solution_assessment.md) · [Step 2](./feature_flags_security_review_cleanup_step2_feature_description.md) · [Step 3](./feature_flags_security_review_cleanup_step3_development_plan.md) · [Step 4](./feature_flags_security_review_cleanup_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/feature_flags_security_review_cleanup` · [Ledger](../effort_estimation_trial.md#enrollment-index-after-p2)

# Access history — development plan

## Completion Contract
- Deliver the approved User Access → five recent events → expanded evidence → full history → return flow, with empty history and expired-session authentication/retry. Preserve account actions, policy controls, audit records, existing URLs and paging. Mockup data/controls do not ship.
- Release requires role/session, populated/empty/legacy, loaded phone/desktop, keyboard, theme, overflow and full-history checks; record reported-state reproduction and limitations. No deployment or policy behavior changes.
- After approval: create a feature branch; organize approved plans/debug log/mockup under this feature's folder and update index/links before the planning commit. Keep study ledger updates pending for closeout. Each stage includes verification and summary in its own commit before the next.

## Key Risks
- **High risk:** private evidence leakage. Validate authenticated operator gates and no-store behavior before data wiring; hints must never authorize history. Keep API denials and RSS restrictions intact.
- **High risk:** authentication loops or misleading history. Replay hint-only/expired sessions and legacy records early; reuse safe local return destinations and existing evidence/unknown classifications.
- Shared standard/Forte presentation can diverge. Reuse one event presenter/details contract; preserve ordinary Activity and stable paging. Reuse existing queries/limit support; no schema change planned.

## Stage 1 — Protected entry and recovery
- Goal: replace the one-line operator dead end with authentication and return.
- Dependencies: Step 3 approval and planning commit.
- Expected changes: reproduce hint-only denial versus empty history; add protected HTML recovery using existing authentication/return behavior; remove Feature Flags paragraph.
- Verification approach: actual sign-in, expiry/retry, non-operator denial, unsafe return targets, private API/RSS restrictions and no-store/no-leak responses.
- Risks or open questions: hints cannot grant authority; resolve real session recovery before data wiring.
- Canonical components/API contracts touched: Application security-page guard, RouteServices operator check, authentication/safe return; API denials unchanged.

## Stage 2 — Recent history and evidence
- Goal: show five recent events in User Access with expandable evidence.
- Dependencies: Stage 1 verified/committed.
- Expected changes: reuse bounded `fetchActivityRows`/has-more and enrichment; introduce `AccessHistoryPresenter::present(array $item): array` plus shared event partial. Replace loose link; add empty/recovery states and conditional View all.
- Verification approach: zero/five/six events, tied dates, grants/invitations/policy/legacy, unknown fields, escaping, receipts/source links, permissions and unchanged account/pending actions.
- Risks or open questions: labels cannot establish acceptance facts; validate representative records and retain historical warnings.
- Canonical components/API contracts touched: User Access, ActivityService existing queries, approval/source evidence; no schema change.

## Stage 3 — Full history and shared views
- Goal: View all supports older history and return to account management.
- Dependencies: Stage 2 verified/committed.
- Expected changes: reuse compact summaries/expandable evidence in standard security Activity and Forte selected details; preserve existing URLs, row contracts, sort/cursors; add return and empty state. Ordinary Activity stays unchanged.
- Verification approach: recent/full agreement, paging without duplicates/omissions, source/back/return, expiry and standard/Forte parity.
- Risks or open questions: check affected navigation icons in Word97/Chicago/Midnight if destinations are renamed/added.
- Canonical components/API contracts touched: activity templates/controller, shared presenter/details, existing Forte endpoints and icon mappings if affected.

## Stage 4 — Browser acceptance and closeout
- Goal: verify the approved operator flow in the running application.
- Dependencies: Stage 3 verified/committed.
- Expected changes: extend isolated approval fixtures; record UI audit, summary, study closeout and limitations.
- Verification approach: normal Tools entry and reported failure/recovery; operator/hint-only/non-operator, expiry, empty/populated/expanded/legacy, full history/paging/source/back, long values, keyboard, phone/desktop and affected themes. Run focused PHP/history/auth and existing activity/browser checks; identify baseline failures separately.
- Risks or open questions: mockup screenshots cannot satisfy acceptance; require actual session/signature and no-store evidence. Disclose unreproduced user conditions.
- Canonical components/API contracts touched: account-key approval/Forte parity harnesses; UI checklist, cycle summary/debug log and effort ledger.

## Approval and study checkpoint
- Step 2 approved; no outstanding scope requests. Four stages, each approximately one hour; split if investigation invalidates sizing. No implementation until Approved Step 3.
- 2026-10-21T20:31:02Z: Step 3, scope v2, start `0b9f5f8baba58b31251b6a6a6b228412784328fe`, R1/P2, Codex exposed single rater (model identifier unavailable): **0.45 total reaffirmed**. Existing bounded query and authentication-return support sustain the estimate, including prior planning/mockup, verification, documentation and correction. Frozen-source hashes verified at Step 1; no independent rating, measured labor or outcome acceptance.
