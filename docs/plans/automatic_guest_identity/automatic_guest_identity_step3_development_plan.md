> **Feature plan:** [Step 1](./automatic_guest_identity_step1_solution_assessment.md) · [Step 2](./automatic_guest_identity_step2_feature_description.md) · [Step 3](./automatic_guest_identity_step3_development_plan.md) · [Step 4](./automatic_guest_identity_step4_implementation_summary.md)

# Automatic Guest Identity — Step 3: Development Plan

## Completion Contract

- Normal entry: a fresh browser opens a standard site page after an operator enables the site flag.
- End-to-end outcome: one browser-local `guest` keypair is prepared without a prompt; immediate preference publishes it, deferred preference publishes it when the visitor first signs an action.
- Required recovery: generation or publication failure leaves no partial public identity claim and directs the visitor to the existing Account Key recovery path; existing local keypairs are never replaced.
- Deployment/external verification: confirm the operator flag is rendered into the current dynamic/static page configuration and fresh-browser smoke tests cover each publication mode.
- Release condition: focused feature-flag, rendered-page, and browser-identity tests pass, including concurrent preparation/first-use coverage.

## Key Risks

- **High risk: usability/performance.** Background generation can delay the first interaction. Validate an action started during preparation; mitigate by coordinating with the existing identity-preparation promise.
- **High risk: data/privacy.** Immediate publication creates a public identity before a signed action. Validate deferred as the stored default; mitigate with explicit Account Key copy and no private-key transmission.
- **High risk: release consistency.** Cached/static HTML can retain a previous site setting. Validate the layout/runtime setting via the existing static artifact path; mitigate through the canonical asset/config rendering contract.

## Stage 1

- Goal: Make automatic guest preparation an operator-visible, default-off site capability.
- Dependencies: Approved Step 2.
- Expected changes: Register one mutable site feature flag; expose its effective value through the existing layout-to-browser runtime settings; update flag evaluator and rendered-page coverage.
- Verification approach: Assert default, environment/site overrides, Feature Flags page presence, and rendered runtime setting for enabled and disabled values.
- Risks or open questions:
  - Impact: stale rendered pages may use an old value.
  - Early warning / validation: static artifact/render test inspects the emitted setting.
  - Mitigation: reuse existing feature-flag invalidation and asset-fingerprinting behavior.
- Canonical components/API contracts touched: FeatureFlagRegistry/Evaluator, Tools Feature Flags page, TemplateRenderer/layout runtime configuration.

## Stage 2

- Goal: Prepare a browser-local guest identity automatically and safely coordinate it with first use.
- Dependencies: Stage 1 runtime setting.
- Expected changes: Extend canonical browser identity preparation with an automatic no-prompt `guest` entry and a publication-policy input; schedule it only when enabled and no usable keypair exists.
- Verification approach: Browser-script tests prove one generation/no prompt, existing-key preservation, deferred no-publication, and action overlap waiting for preparation.
- Risks or open questions:
  - Impact: simultaneous automatic and action setup could publish late or prompt unexpectedly.
  - Early warning / validation: concurrent-preparation test records prompt, generation, and publication calls.
  - Mitigation: retain one canonical preparation coordinator and make first use complete required publication after it settles.
- Canonical components/API contracts touched: browser_signing identity coordinator, OpenPGP loader, existing identity-hint and publication APIs.

## Stage 3

- Goal: Give each browser a clear, durable choice of immediate or first-use publication.
- Dependencies: Stage 2 publication-policy input.
- Expected changes: Extend Account Key with the per-browser publication setting, explanatory privacy copy, and immediate publication for an already-generated unpublished identity when the visitor explicitly selects that option.
- Verification approach: Browser/UI tests verify default deferred state, persistence, switch-to-immediate publication, and failure feedback/retry behavior.
- Risks or open questions:
  - Impact: visitors may mistake the choice for a site-wide setting.
  - Early warning / validation: Account Key rendering test asserts per-browser wording and state.
  - Mitigation: locate it only on Account Key and identify it as applying to this browser.
- Canonical components/API contracts touched: Account Key template and browser-signing saved-state/publication flow; no new endpoint.

## Stage 4

- Goal: Verify the complete operator-to-visitor path and document operation.
- Dependencies: Stages 1–3.
- Expected changes: Add end-to-end-focused regression coverage and concise operator documentation for enabling, disabling, and rolling back the site flag.
- Verification approach: Run focused PHP/browser tests, a fresh-profile smoke test for both choices, static-render checks, and documentation/link checks.
- Risks or open questions:
  - Impact: an enabled deployment could create unexpected identities if the wrong preference default ships.
  - Early warning / validation: fresh-profile deferred-mode smoke test shows no publication before first signed action.
  - Mitigation: default the flag off and publication deferred; rollback by disabling the site flag.
- Canonical components/API contracts touched: existing test harnesses, static artifact builder, README/operator deployment guidance.
