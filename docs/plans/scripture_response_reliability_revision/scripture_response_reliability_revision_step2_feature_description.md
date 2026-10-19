> **Feature plan:** [Step 1](./scripture_response_reliability_revision_step1_solution_assessment.md) · [Step 2](./scripture_response_reliability_revision_step2_feature_description.md) · [Step 3](./scripture_response_reliability_revision_step3_development_plan.md) · [Step 4](./scripture_response_reliability_revision_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Reliability Revision — Feature Description

## Problem

The failed candidate verifies published quotations but rejects ordinary quoted language and misreads practical or pastoral concerns. A full-size writer has not yet been evaluated against the repaired approach.

## User Stories

- As a requester, I want an answer faithful to my concern and stated constraints so that I can understand or act responsibly.
- As a reader, I want scripture, reader wording, terminology and interpretation distinguished so that ordinary discussion works without misleading quotation claims.
- As a requester, I want complete replies, useful expansion and clear recovery so that verification does not strand legitimate requests.

## Core Requirements

- Compare `gpt-5-mini` and `gpt-5.6-sol` on the same repaired guidance, quotation safeguards and delivery flow. Select the writer only for scripture replies and Expand; preserve other modes, moderation and the existing shared model setting. Keep one writing call per attempt; exclude an editorial generation pass.
- Permit legitimate quoted terms, casual labels and reader wording without blanket rejection. Preserve their origin and distinction from verified scripture; labels supplied by the writer must not let fabricated passages bypass verification.
- Retain source-owned KJV quotations, accurate references/translation labels and marked omissions, including publication-time revalidation. Unsupported translations or unavailable sources require an explicit limitation and supported paraphrase, or explicit failure; never silently substitute or repair claims.
- Address the actual concern: practical advice preserves deadline values and roles without invented travel time; pastoral support avoids imposed productivity routines; interpretation supplies context and requested competing readings. Initial answers are complete and expansions additive. No mandatory template, word limit or verse quota.
- Preserve permission/moderation checks, duplicate prevention, source binding and explicit retry. Publish no partial answer; retain failed/interrupted trials and uncertain charges. A model or source failure must not silently trigger another model or paid regeneration.

## Delivery Scope and Completion Boundary

Work type: **application change**. Reuse existing request → progress → complete reply → optional Expand → explicit recovery. Preserve historical replies. Exclude new conversation features, additional translations and remote deployment. Keep verified mode inactive until all joint gates pass; model selection alone does not permit promotion.

## Risks

- **More permissive language can reopen quotation bypasses:** Before Step 3, define origin/verification acceptance examples; require adversarial stub checks for fabricated scripture disguised as reader text or terminology before paid trials.
- **Stronger writer can remain wrong or slower:** Before Step 3, freeze deadline-role, pastoral and interpretive review rules plus matched timing boundaries; retain failed cases as regressions and reserve fresh selection cases. Do not relax speed gates implicitly.
- **Compatibility, cost or account access can block evaluation:** Before Step 3, establish supported settings and proposed numeric ceilings; rehearse routing/budget stops before paid calls. Retain endpoint rejection and reassess without substitution or expanding spend.

## Shared Component Inventory

- **Writer, quotation/source verification, queue, publication and recovery:** Extend canonical services; reuse request/status APIs and browser progress.
- **Thread/post cards and expansion partial:** Reuse shared result rendering; show quotation distinctions consistently.
- **Cached/static/offline views, profiles and RSS:** Preserve stored-result visibility and supplementary-record exclusions.
- **Operator configuration/CLI, private diagnostics and evaluation harness:** Extend scripture-only selection, final-answer review and accounting; reuse existing controls.

## Simple User Flow

1. Request scripture analysis using existing discussion controls.
2. Receive a complete answer with trustworthy quotation distinctions, or an explicit recoverable failure.
3. Request Expand for additional explanation under the same safeguards.

## Success Criteria

- Freeze candidate identities/settings, regressions, fresh held-out cases, anonymous final-answer/source/context review, and a new call/spend ceiling in Step 3. Include all regression, comparison and retry calls; prior unused allowance does not renew.
- Retain ≥15/16 initial quality passes, all eight everyday passes, ≥4 matched everyday improvements, no loss of complex-case completeness and all six expansion passes. All published quotations pass independent source checks; any fabricated/misattributed quotation or unsupported scriptural promise blocks release.
- Retain initial and expansion latency gates separately: median submission-to-visible completion ≤20 seconds and ≥30% below matched baseline, no sampled completion >40 seconds, and complete timing evidence. Missing completions cannot qualify. Any change to these inherited gates needs explicit approval.
- All legitimate-language and adversarial quotation fixtures, deadline-role regressions, permission/moderation, duplicate, source/translation limitation and explicit-recovery checks pass through normal delivery. If no candidate qualifies or evidence is insufficient, report the result without promotion.
