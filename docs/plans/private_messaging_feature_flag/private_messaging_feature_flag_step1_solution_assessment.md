> **Feature plan:** [Step 1](./private_messaging_feature_flag_step1_solution_assessment.md) · [Step 2](./private_messaging_feature_flag_step2_feature_description.md) · [Step 3](./private_messaging_feature_flag_step3_development_plan.md) · [Step 4](./private_messaging_feature_flag_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

As an admin/operator, I want to be able to gate whether the PM/DM (messages) feature is enabled on the server with a feature flag. Please write Step 1 of Railspike.

## Problem Statement

Operators have an existing site-wide feature-flag control surface, but no single flag to disable private messaging consistently across user entry points and server access.

## Option A: Deployment-only messaging switch

Add a server-enforced messaging flag controlled only through deployment configuration, with its effective value visible to operators.

- Pros: Clear deployment authority; one switch covers the whole feature.
- Cons: Routine changes require deployment access; does not use the existing operator toggle workflow.

## Option B: Site-wide messaging flag with deployment override

Extend the existing feature-flag system with one messaging toggle available to authorized operators, retaining deployment override precedence and enforcing the effective value throughout messaging.

- Pros: Fits existing operator controls and permissions; supports both routine administration and deployment policy; gives users consistent availability.
- Cons: Requires coverage of all messaging surfaces and clear feedback when deployment configuration locks the toggle.

## Option C: Separate sending and reading flags

Offer independent controls for new messages and existing-history access, allowing operators to leave messaging available in a read-only state.

- Pros: Supports gradual shutdown while keeping history accessible.
- Cons: Adds policy combinations and user-facing states beyond the requested enabled/disabled switch.

## Recommendation

Choose **Option B**, reusing the [existing flag system](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php) and covering the [current messaging experience](../../architecture/private_messaging.md).

- Propose **enabled by default** to preserve current behavior; operators can explicitly disable messaging for their server.
- Disabled means hiding messaging navigation and composers, and rejecting direct messaging page/API access, including legacy mailboxes, recipient-key lookup, unread/read state, and history sync. Already-open clients receive a clear unavailable state and stop messaging activity when they encounter the disabled flag.
- Preserve stored messages and unread/history-sync state; re-enabling restores access under existing identity and key rules. Disabling cannot retract messages already downloaded or decrypted.
- Keep deletion, per-user exceptions, read-only mode, and storage migrations outside this slice.

This is a viable small vertical slice: an authorized operator disables messaging, ordinary entry points and direct requests respect that choice, and re-enabling restores the existing experience without losing history. Step 2 should settle the default and disabled-state behavior, including requests already in flight and recoverable drafts or uncertain sends.
