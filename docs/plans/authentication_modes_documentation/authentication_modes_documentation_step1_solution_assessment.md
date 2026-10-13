> **Feature plan:** [Step 1](./authentication_modes_documentation_step1_solution_assessment.md) · [Step 2](./authentication_modes_documentation_step2_feature_description.md) · [Step 3](./authentication_modes_documentation_step3_development_plan.md) · [Step 4](./authentication_modes_documentation_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Let's review our authentication modes and ensure documentation is up to date, please. Write Step 1 of @railspike.md. Debug mode on.

## Understood Intent

Assess a documentation-only review of current authentication behavior, including public and approved-members-only access, browser identities, signed actions, and session recovery. This step selects an approach; subsequent approved work updates the documentation.

## Problem

Authentication guidance is scattered across setup, architecture, and historical plans, making it difficult to distinguish identity preparation, proof of identity, session state, and permission to access member features.

## Review Evidence

- The [flag registry](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php) defines independent, default-off members-only and automatic guest-keypair flags: guest preparation is not membership approval.
- The [authentication controller](../../../src/ForumRewrite/Http/AuthApiController.php) can authenticate an unapproved identity, while its status endpoint reports success only for approved viewers; the [invitation controller](../../../src/ForumRewrite/Http/IdentityApprovalAndInvitationApiController.php) requires both authentication and approval to issue invitations, even on public sites.
- [README access guidance](../../../README.md) says unapproved visitors can access only Lobby, Account, and their own profile; [the access gate](../../../src/ForumRewrite/Application.php) also allows documentation GETs and onboarding/authentication endpoints. Its description needs qualification.
- The [session reference](.././session_reauthentication_reference.md) identifies implemented recovery but retains proposal-era problem statements and alternatives. [Existing browser tests](../../../tests/PrivateSiteAuthTest.php) distinguish public recovery, missing keys, approval, and challenge expiry; these were inspected, not executed for this assessment.

## Option A — Correct Existing Documentation In Place

Review the current behavior and repair the README, architecture guidance, and session reference where each topic already lives.

- Pros: Smallest change; preserves familiar documentation entry points.
- Cons: Readers must still assemble the access model across several documents; duplicated explanations can drift again.

## Option B — Establish One Reference and Reconcile Entry Points

Create a current authentication reference with a compact access/state matrix, then correct and link the existing guidance to it, clearly separating historical proposals from implemented behavior.

- Pros: One place explains anonymous participation versus guest/named keys, public versus members-only access, authentication versus approval, invitations, and recovery after session loss or missing keys.
- Cons: Requires reconciling existing prose and maintaining evidence links; a new page alone would leave contradictory guidance intact.

## Recommendation

Choose **Option B**, including Option A's targeted corrections. This is a bounded documentation slice: a reader enters through the README, determines the applicable access and identity state, understands permitted actions, and finds recovery guidance. Verify claims against current source and relevant tests; mark unresolved behavior explicitly. Keep runtime changes and authentication redesign outside this slice, and preserve historical plans as history. Step 2 should name the allowed documentation files and reader-facing acceptance criteria.
