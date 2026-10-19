> **Feature plan:** [Step 1](./scripture_response_reliability_step1_solution_assessment.md) · [Step 2](./scripture_response_reliability_step2_feature_description.md) · [Step 3](./scripture_response_reliability_step3_development_plan.md) · [Step 4](./scripture_response_reliability_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Reliability — Feature Description

## Problem

Existing scripture replies can miss the reader's decision and publish inaccurate quotations despite explicit instructions. The selected mini writer still needs measured editorial performance and an independent quotation safeguard.

## User Stories

- As a requester, I want an answer centered on my actual concern so that I can understand or act without sorting through a devotional survey.
- As a reader, I want trustworthy quotations and clear interpretation so that I can distinguish scripture from the writer's application.
- As a requester, I want complete initial replies, optional depth, and clear recovery so that failures do not leave misleading or partial answers.

## Core Requirements

- Evaluate the selected `gpt-5-mini` writer on relevance, judgment, context, and warranted depth. For practical questions, distinguish actual constraints from optional plans; do not invent emotional or spiritual problems. No mandatory answer template, word limit, or verse quota.
- Verify every displayed verbatim biblical quotation against an identified passage and translation before publication, including Expand. Verification covers wording, reference, translation label, and marked omissions; it does not establish interpretive faithfulness.
- Initially support verified KJV quotations from an identified, reproducible source. Preserve explicitly requested translations only when their source text can be verified; otherwise explain the limitation and use clearly labeled paraphrase or interpretation without silently substituting another translation. Reader-supplied wording is input, not independent verification.
- On unavailable or mismatched source text, never publish an unverified quotation or silently patch a claim. Permit a complete answer with clearly labeled paraphrase when supported; otherwise report failure with explicit retry. Keep retries bounded and visible, preserving moderation, permissions, and duplicate prevention.
- Keep initial answers complete and expansions additive. Evaluate final published text, source recovery, and submission-to-visible-completion latency together; preserve failures and interrupted trials as evidence.

## Delivery Scope and Completion Boundary

Work type: **application change**. Deliver the existing scripture request → progress → verified complete reply → optional Expand → explicit recovery flow. Reuse existing rendering and task lifecycle. Preserve historical replies and unrelated modes. Exclude a separate editorial generation pass and new conversation features. Release requires the joint gates below; model selection alone is not acceptance.

## Risks

- **Source coverage or availability:** Can block useful answers. Before Step 3, identify and validate the KJV source, usage terms, context availability, and unavailable-source behavior; keep unsupported translations explicit.
- **Correct wording, misleading application:** Can falsely imply reliability. Before Step 3, freeze independent context/judgment review criteria and unseen cases; reject critical interpretive errors.
- **Added waiting or cost:** Verification and recovery can defeat responsiveness. Before Step 3, fix call limits, timing boundaries, and a new spend budget; validate capture and bounded failures with stubs before paid trials.

## Shared Component Inventory

- **Post/thread cards, shared expansion partial, cached/static/offline views:** Reuse stored-result rendering and existing visibility rules.
- **Request/status APIs and browser progress:** Extend existing completion and explicit failure behavior.
- **Writer, expansion service, queue, publication, CLI and private diagnostics:** Extend the canonical scripture lifecycle with shared verification; preserve unrelated modes.
- **Profile listings and RSS:** Preserve initial-reply presentation and supplementary-record exclusions.

## Simple User Flow

1. Request scripture analysis using existing discussion controls.
2. Receive a complete, relevant answer with verified quotations or an explicit source limitation; recover explicitly if completion fails.
3. Request Expand for additional explanation under the same safeguards.

## Success Criteria

- Freeze repeated initial/expansion cases, anonymous review, candidate settings, critical-error rules, source fixtures, and numeric quality/latency gates in Step 3 before implementation. Retain the approved departure answer and prior failures as regressions; reserve fresh cases for selection.
- Retain the predecessor's quality floor (15/16 initial answers and all six expansions passing) and latency targets (median ≤20 seconds and ≥30% improvement over matched baseline, no sampled completion >40 seconds), subject to explicit review if the new evaluation design requires changes. Any fabricated/misattributed quotation or unsupported scriptural promise blocks release.
- Every evaluated published quotation passes independent source checking; unavailable text, mismatches, unsupported translations, omissions, and supplied inaccurate quotations exercise normal-flow recovery. All permission, moderation, duplicate, and failure checks pass.
- A new numeric call/spend ceiling must be approved with Step 3 before paid trials; the prior budget does not renew automatically. If gates fail or evidence is insufficient, document the result and return for reassessment.
