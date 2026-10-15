> **Feature plan:** [Step 1](./account_retraction_attribution_step1_solution_assessment.md) · [Step 2](./account_retraction_attribution_step2_feature_description.md) · [Step 3](./account_retraction_attribution_step3_development_plan.md) · [Step 4](./account_retraction_attribution_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Retraction notices equate authorship with an exact signing key, so an operator using another approved key for the same account is described as an outside operator. Readers need account-level attribution while audit evidence retains the actual signer and authorization classification.

## User stories

- As an account holder, I want retractions made with another approved account key attributed to the author so that notices reflect my account identity.
- As a reader, I want consistent attribution across post and source views so that I can understand who retracted a post.
- As an auditor, I want the actual signer and operator authority preserved so that account wording does not obscure the signed action.

## Core requirements

- Preserve author wording for exact-key self-retractions. For distinct keys, use author wording only when both were approved and shared a nonempty canonical username at acceptance; no direct approval link is required.
- Keep “This post was retracted by an operator.” for different accounts, unapproved matching names, and historical different-key actions lacking reliable acceptance-time evidence.
- Keep attribution stable across later approval changes and read-model rebuilds; never infer historical account membership solely from current profiles.
- Apply the same decision to every existing notice, including unavailable-source responses. Preserve signatures, actual actor identity, operator authorization classification, and existing content suppression.
- Preserve current retraction permissions: ordinary users gain no permission to retract posts signed by another account key.

## Delivery scope and completion boundary

Application change covering the existing retraction flow and its displayed results. An authorized operator enters through the existing post action, submits a signed retraction, and sees the appropriate account attribution on all affected views. Existing incomplete-update recovery must converge on the same result. Release requires consistent live and rebuilt attribution, conservative historical fallback, and unchanged authorization and audit evidence.

## Risks

- **Historical evidence:** Incorrect reconstruction could misattribute older actions. Before Step 3, assess acceptance-time evidence availability and define which records qualify; ambiguous records retain operator wording.
- **Durability:** Later profile changes or rebuilds could relabel actions. Before Step 3, establish a feasible durable evidence policy; unresolved durability blocks dependent planning.
- **Surface divergence:** Source responses use a separate data path from post views. Before Step 3, trace both paths and require one attribution policy across them.

## Shared component inventory

- Extend the canonical `RetractedPostNotice` policy used by `retracted_post_notice`, post cards, thread roots (including quote cards), paned board articles, and paned reply trees; no new notice component is needed.
- Extend the attribution supplied to `SourceFileController` for current and historical source requests; retain its unavailable-source behavior.
- Reuse existing retraction write APIs, browser controls, and recovery flow; preserve their permission checks. Signed records and audit/source inspection retain actual actor and authorization details.

## Simple user flow

1. An operator opens a post authored under another approved key for their canonical account.
2. They confirm the existing retraction action and complete signing.
3. Post views and blocked source requests say “This post was retracted by its author.”
4. Audit inspection still identifies the signing key and operator action; any incomplete update is retried through existing recovery.

## Success criteria

- Exact-key and approved same-account cases show author wording; different-account, unapproved-name, empty-name, and ambiguous historical cases retain the appropriate existing attribution.
- Canonical username case normalization produces consistent decisions across every listed surface.
- Approval changes, recovery, and rebuilds preserve accepted attribution.
- Verification confirms unchanged signed evidence, operator classification, content suppression, and rejection of unauthorized cross-key retractions.

## Review

Approved Step 2 by the user; scope unchanged.
