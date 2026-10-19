> **Feature plan:** [Step 1](./scripture_response_reliability_revision_step1_solution_assessment.md) · [Step 2](./scripture_response_reliability_revision_step2_feature_description.md) · [Step 3](./scripture_response_reliability_revision_step3_development_plan.md) · [Step 4](./scripture_response_reliability_revision_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Reliability Revision — Development Plan

## Completion Contract

Deliver existing scripture request → complete reply → optional Expand → explicit recovery, using a scripture-only writer and verified quotations. Preserve history, moderation, shared model and unrelated modes; no schema change, editor pass, additional translation or deployment. Step 3 approval covers this plan and its [evaluation protocol](./scripture_response_reliability_revision_step3_evaluation_protocol.md).

Create the revision branch and planning commit first; then verify, summarize and commit each stage before continuing. Seven stages target one hour each; split overruns. Promotion requires all unchanged Step 2 gates. Otherwise keep verified mode inactive and reassess.

## Key Risks

- **High risk: quotation bypass/false rejection.** Resolve origin and adversarial oracles before dependent trials; writer labels cannot establish verification.
- **High risk: wrong constraints/pastoral framing.** Validate deadline roles and actual concern against the original input before qualification.
- **Compatibility/cost/measurement:** Prove Sol-only routing, supported payloads, durable budget stops and complete browser timing with stubs before spending; no silent fallback.

## Comparison and Budget

The protocol fixes A=current mini control, B=repaired mini, C=identically repaired Sol. Compare B/C for writer capability and each against A for inherited release improvements. Review final answers anonymously before revealing labels.

Proposed ceiling: **76 writing calls, 152 moderation calls, $10**, with 72 planned calls if both candidates qualify: 12 regressions + 48 fresh initial + 12 conditional expansion. Spare calls cover documented operational replacements only. No automatic retries, quality replacements, paid judges or renewed prior allowance.

## Stage 1

- Goal: Accept legitimate quoted discussion safely.
- Dependencies: Planning commit.
- Expected changes: Origin-aware contract and concern-specific guidance.
- Verification approach: Positive/adversarial document replay and normalized rendering.
- Risks or open questions: Bypass blocks dependents; retain source ownership.
- Canonical components/API contracts touched: Verifier, source, prompts.

## Stage 2

- Goal: Scope writer selection to scripture/Expand.
- Dependencies: Stage 1.
- Expected changes: Private override and narrowly supported Sol payloads.
- Verification approach: Stub payloads, moderation/other-mode isolation, exhaustion.
- Risks or open questions: Global mutation/fallback blocks trials.
- Canonical components/API contracts touched: Settings, provider factory, payload validation, CLI.

## Stage 3

- Goal: Complete normal delivery and recovery.
- Dependencies: Stages 1–2.
- Expected changes: Origin-aware stored-draft/publication revalidation.
- Verification approach: Browser request/Expand, permissions, duplicates, NIV/source limitations, retry and offline results.
- Risks or open questions: Reject partial/unverified drafts; preserve history.
- Canonical components/API contracts touched: Fulfillment, queue, APIs, shared rendering.

## Stage 4

- Goal: Freeze and rehearse the protocol.
- Dependencies: Stages 1–3 verified.
- Expected changes: Three-arm ledger, review, gates and operations notes.
- Verification approach: Stub HTTP/worker/browser flows, failures, hashes, timing and hard stops.
- Risks or open questions: Measurement/isolation gaps block paid calls.
- Canonical components/API contracts touched: Harness, proxy, diagnostics, docs.

## Stage 5

- Goal: Qualify candidates on known regressions.
- Dependencies: Stage 4 freeze and approved budget.
- Expected changes: Up to 12 calls and anonymous ratings.
- Verification approach: Frozen concern/language/source oracles.
- Risks or open questions: Reject failures before fresh trials; no tuning/substitution.
- Canonical components/API contracts touched: Isolated delivery, ledger, regression report.

## Stage 6

- Goal: Measure fresh-case acceptance and select provisionally.
- Dependencies: Eligible Stage 5 candidate(s), unchanged artifacts, remaining budget.
- Expected changes: Up to 48 initial calls and gates against A.
- Verification approach: Unchanged joint gates; retained failures and model IDs.
- Risks or open questions: Missing control/no qualifier blocks expansions.
- Canonical components/API contracts touched: Trials, final-answer review, gate/report utilities.

## Stage 7

- Goal: Verify expansions and release.
- Dependencies: Stage 6 qualifier and budget.
- Expected changes: 12 matched calls; qualifying tested settings and final operations notes.
- Verification approach: Expansion gates; focused stub isolation, stored surfaces, recovery and rollback.
- Risks or open questions: Failure blocks promotion; no untested fallback. Disable requests before rollback, preserving pending-draft verification.
- Canonical components/API contracts touched: Scripture settings, shared lifecycle/rendering, operator docs.
