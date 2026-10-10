> **Feature plan:** [Step 1](./feature_flag_change_attribution_step1_solution_assessment.md) · [Step 2](./feature_flag_change_attribution_step2_feature_description.md) · [Step 3](./feature_flag_change_attribution_step3_development_plan.md) · [Step 4](./feature_flag_change_attribution_step4_implementation_summary.md)

# Step 2: Feature Description — Signed Feature-Flag Changes

## Problem

Feature-flag changes currently prove only that a root-approved session reached the server. The mutable snapshot and activity history do not durably identify or cryptographically prove which operator authorized an individual change.

## User Stories

- As a root-approved operator, I want a flag change signed by my browser identity so that it is provably mine.
- As an auditor, I want each flag-change activity item to identify its signer and expose durable signature evidence so that I can verify who authorized it.
- As an operator, I want a failed signature attempt to leave the setting unchanged so that I can recover safely and retry.

## Core Requirements

- A new site-level flag change must finalize only after a valid detached signature over its exact canonical action record verifies against the current root-approved operator identity.
- Every successful change must commit the signed immutable action evidence with the existing feature-flags snapshot, retaining both current configuration and per-change history.
- Activity and source views must attribute each successful flag change to the verified signer and provide access to its signature evidence.
- Missing, invalid, expired, mismatched, or unavailable signatures must not alter the snapshot, create action evidence, commit, or publish activity; the operator receives clear retry feedback.
- Existing flag evaluation, environment/private-config precedence, mutability rules, and root-approval authorization remain unchanged.

## Delivery Scope

- **Work type:** application change.

## Completion Boundary

- **Normal entry:** A root-approved operator changes or resets a mutable flag from `/tools/feature-flags/`.
- **End-to-end outcome:** The browser signs the prepared change, the site verifies it, applies the existing snapshot update, and shows attributed audit evidence.
- **Recovery:** A signing or verification failure leaves all persisted configuration and activity unchanged; the operator can correct their local identity/signing state and retry.
- **Release condition:** A valid signed change is visible and independently verifiable through the established repository, activity, and source surfaces; unsigned or invalid requests cannot change a flag.

## Risks

- **Signature identity disagrees with the authorized session:** could attribute or authorize the wrong operator. **Earliest validation:** define and test the binding between resolved root-approved identity and signing key. **Mitigation before Step 3:** confirm the canonical identity/key contract reused by existing signed writes.
- **Prepared requests are replayed or altered:** could duplicate or misrepresent a configuration change. **Earliest validation:** review existing prepared-write expiry and single-use guarantees. **Mitigation before Step 3:** explicitly adopt their applicable replay and exact-byte protections.
- **Audit evidence and snapshot diverge after a partial failure:** could change configuration without verifiable attribution. **Earliest validation:** trace the commit and read-model refresh boundary. **Mitigation before Step 3:** require an atomic canonical commit and failure-path tests.

## Shared Component Inventory

- `LocalWriteService` feature-flag write path — extend as the canonical snapshot-update owner; retain its evaluation and invalidation behavior.
- Existing prepared signed-write lifecycle and detached-signature verifier — reuse for exact-record preparation, browser signature validation, expiry, and replay handling; do not introduce another signature model.
- `browser_signing.js` and the Feature Flags page/script — extend the established browser-key signing and feedback behavior for the existing operator entry point.
- Existing activity, source metadata, and commit-manifest surfaces — extend to present the signer and action signature; do not create a separate audit UI.
- `SiteFeatureFlagsRecord` and its evaluator — retain as the current-state configuration contract; action evidence complements rather than replaces it.

## Simple User Flow

1. A root-approved operator selects a new value or reset action on the Feature Flags page.
2. The site prepares the exact change for that operator to sign.
3. The browser signs it with the operator's local identity key and submits it.
4. The site verifies authorization and signature, commits the action evidence with the updated configuration, and refreshes the existing derived state.
5. The page confirms success, while activity and source surfaces identify the signer and make the proof inspectable.

## Success Criteria

- Every newly changed mutable site flag has a committed, valid signature tied to the root-approved identity that changed it.
- Activity for a new flag change names that verified identity and links to canonical signature evidence.
- Invalid, unsigned, mismatched, replayed, or expired change submissions leave the flag value, git history, and activity unchanged.
- The current feature-flags page retains its existing runtime value, reset, lock, dependency, and override behavior after a successful signed change.

## Approval Gate

Reply **Approved Step 2** to proceed to the development plan.
