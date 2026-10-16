> **Feature plan:** [Step 1](./thywill_site_option_step1_solution_assessment.md) · [Step 2](./thywill_site_option_step2_feature_description.md) · [Step 3](./thywill_site_option_step3_development_plan.md) · [Step 4](./thywill_site_option_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Please review ~/thywill and write a report on which features v3 already supports and which would have to be added on in order for thywill to become a new site option.

## Understood Intent

Assess Thywill as another selectable v3 site profile. Deliver the requested source-based [feature support and gap report](thywill_site_option_review.md); assess adoption options without implementing a port.

## Problem

v3 already supplies the site/profile and community infrastructure, but Thywill's repeat prayer events, lifecycle, guided experience, and account recovery exceed existing forum behavior.

## Option A — Branded prayer forum

Add Thywill presentation over existing forum posts, identities, invitations, and reactions.

- Pros: smallest scope; uses current platform behavior.
- Cons: lacks repeat prayer tracking, generated-prayer workflow, answered testimony, and Thywill account recovery; unsuitable as a parity replacement.

## Option B — Native prayer experience

Reuse v3's platform while adding the prayer journey and choosing explicitly which secondary Thywill workflows to retain.

- Pros: recognizable prayer application; shared storage, identity, provider, and presentation foundations; can grow in vertical slices.
- Cons: new durable prayer semantics, views, and access rules; recovery differences and migration require explicit decisions.

## Option C — Full replacement parity

Preserve the active Thywill experience, including guided mode, optional email recovery, applications, administration, offline marks, and community history.

- Pros: minimizes losses when retiring the existing service.
- Cons: largest scope; requires several separately scoped feature cycles and a careful identity/data transition.

## Recommendation and viability

Prefer **Option B**. The first usable slice should let a member enter, submit a request, preview a generated prayer or recover from generation failure, publish it, and record repeated prayers with accurate totals. Establish access and moderation/lifecycle policy before moving real community data. A full existing-site replacement also requires migration; a fresh instance can defer it.

The detailed report distinguishes existing support, reusable foundations, missing behavior, conditional features, and stale README claims. Fourteen focused v3 tests passed. The report completes the requested review; advancing to a feature description requires `Approved Step 1`. No implementation, branch, or commit is authorized by this assessment.
