> **Feature plan:** [Step 1](./automatic_guest_identity_step1_solution_assessment.md) · [Step 2](./automatic_guest_identity_step2_feature_description.md) · [Step 3](./automatic_guest_identity_step3_development_plan.md) · [Step 4](./automatic_guest_identity_step4_implementation_summary.md)

# Automatic Guest Identity — Step 2: Feature Description

## Problem

First-time visitors must begin a signed action and choose a username before a browser identity exists, delaying the interaction they intended to perform.

## User Stories

- As an operator, I want to enable automatic guest-key preparation so that visitors can interact without a first-action username prompt.
- As a visitor, I want an automatically prepared guest identity to remain browser-local so that the site never receives my private key.
- As a visitor, I want to choose whether that guest public key is published immediately or on first use so that I control when an unused identity becomes public.

## Core Requirements

- A default-off, operator-managed site flag enables automatic guest-key preparation for browsers without a keypair.
- When enabled, the browser creates a usable `guest` identity without showing a username prompt and never replaces an existing browser keypair.
- A per-browser user setting selects immediate publication or deferred publication on the first signed use; deferred publication is the default.
- The first signed action waits for any in-progress automatic preparation, publishes when required, and completes through the existing signed-action flow.
- Failures leave the visitor able to retry through the existing account-key recovery path and do not expose private key material.

## Delivery Scope

- **Work type:** application change.
- **Completion boundary:** A normal site visit with the operator flag enabled creates a browser-local guest identity; immediate-publication preference completes publication in the background, while deferred preference publishes on the first signed action. Existing-key browsers are unchanged. A failed generation or publication shows existing actionable recovery, and release requires automated coverage of both preference paths plus operator-flag rendering.

## Risks

- **Background key generation slows initial interaction.** Validate with a fresh-browser manual smoke test early; run it asynchronously and ensure action flows wait safely.
- **Immediate publication can create unused public identities.** Validate the deferred default in browser coverage; state the consequence clearly beside the user setting.
- **A static or cached page may carry stale operator configuration.** Validate rendered runtime configuration through the existing asset/static-release path; use the current feature-flag rendering contract.
- **Concurrent preparation and first use can race.** Validate with a focused automated overlap case; reuse the canonical identity-preparation coordination and recovery behavior.

## Shared Component Inventory

- **Site feature-flag registry and Tools feature-flags page:** extend the canonical operator setting; no parallel configuration surface.
- **Layout runtime configuration:** extend the existing server-to-browser settings surface so dynamic and rendered pages agree.
- **Browser signing and identity preparation:** reuse as the sole key-generation, publication, synchronization, and first-use path.
- **Account Key page:** extend the existing browser-identity controls with the per-browser publication preference; no new account page.
- **Existing identity publication APIs and recovery messages:** reuse unchanged; no new private-key API or identity storage model.

## Simple User Flow

1. An operator enables automatic guest-key preparation for the site.
2. A visitor without a browser keypair opens a normal site page and receives a browser-local `guest` identity without a prompt.
3. If the visitor selected immediate publication, the public key is published in the background; otherwise it remains local.
4. The visitor starts a signed action; deferred identities publish first, then the action continues normally.
5. If setup fails, the visitor can retry or manage the identity from Account Key.

## Success Criteria

- With the operator flag off, fresh browsers retain the current first-action setup behavior.
- With it on, a fresh browser receives exactly one `guest` keypair without a username prompt, and an existing keypair is preserved.
- Deferred publication makes no identity-publication request until the first signed action; immediate publication does so during automatic preparation.
- Both publication choices complete a first signed action successfully, and automated tests cover normal, deferred, immediate, existing-key, and preparation-failure behavior.
