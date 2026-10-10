> **Feature plan:** [Step 1](./vote_identity_readiness_step1_solution_assessment.md) · [Step 2](./vote_identity_readiness_step2_feature_description.md) · [Step 3](./vote_identity_readiness_step3_development_plan.md) · [Step 4](./vote_identity_readiness_step4_implementation_summary.md)

# Vote Identity Readiness — Step 2: Feature Description

## Problem

A browser that already holds a usable local identity can still perform publication, verification, or identity-hint work only after a vote is clicked. That makes a returning voter's first click wait behind identity preparation that could safely start earlier.

## User Stories

- As a returning voter with a locally stored identity, I want my identity ready before I vote so that my first vote can be submitted immediately.
- As a visitor without a local identity, I want identity creation to remain an intentional first-action flow so that merely reading vote-capable pages does not create an identity.
- As an operator, I want prewarming failures to preserve the established visible retry and recovery path so that a background failure never silently prevents voting.

## Core Requirements

- On a page with vote controls, silently ready an existing usable local identity, including the server work required for it to cast a vote.
- Background readiness must never create a keypair, prompt for a username, or replace local identity material.
- A first vote from an already-ready identity bypasses identity-preparation feedback and proceeds through the current vote path.
- A vote that races background readiness joins its single in-flight operation; it must not duplicate identity publication or apply the vote twice.
- A browser without usable local identity material, or one whose background readiness fails, retains the current user-initiated identity setup and recovery behavior.

## Delivery Scope

- **Work type:** application change.
- **Completion boundary:** A voter with an existing local identity opens a page containing a thread, post, or QDB vote control; background readiness completes without creating a new identity; their first vote uses the current write and feedback flow without first showing `Preparing identity...`. A missing identity still follows the current first-vote setup, and a failed background attempt retries visibly on the vote without applying it prematurely. Release requires automated coverage of ready, unready, racing, and failed preparation states.

## Risks

- **Background publication associates a locally stored key with a visit before the voter acts.** Earliest validation: product review of the exact network behavior for an unpublished local key. Mitigation: limit the operation to usable stored identities and document the behavior in the affected identity setting/help surface if required.
- **An idle task and an immediate click can duplicate readiness or a vote.** Earliest validation: an automated overlap case. Mitigation: use the established identity-preparation coordination and existing reaction de-duplication.
- **Some reaction pages lazy-load signing assets.** Earliest validation: automated coverage for each reaction-page asset mode. Mitigation: extend the canonical signing loader/prewarm path rather than adding a parallel vote-only runtime.
- **A background failure could be hidden indefinitely.** Earliest validation: forced publication/loader failure in browser tests. Mitigation: keep background failures silent only until a user clicks, then retry through the current visible error and recovery flow.

## Shared Component Inventory

- **Browser signing and local identity storage:** extend the canonical readiness path; do not add a second key, fingerprint, publication, or hint mechanism.
- **OpenPGP and lazy signing loaders:** reuse the existing loading contract so regular, lazy-loaded, and QDB vote pages have equivalent readiness behavior.
- **Thread, post, and QDB reaction controls:** reuse their current binding and optimistic write behavior; no new voting control or endpoint.
- **Identity publication and identity-hint APIs:** reuse the existing server contract; no API or database change.
- **Account Key recovery surface:** retain as the recovery destination for first-action or failed-prewarm identity setup; no new account UI.

## Simple User Flow

1. A visitor opens a page with one or more vote controls.
2. If a usable local identity is present, the browser silently finishes its readiness work while the page is idle.
3. The visitor votes; an already-ready identity submits through the normal vote flow, while an in-progress readiness task finishes first.
4. If no usable identity exists, the visitor receives the existing identity setup flow only after choosing to vote.
5. If background readiness failed, the vote retries through existing visible feedback and recovery without being applied early.

## Success Criteria

- A stored usable identity that needs readiness can finish it before a first vote, and the subsequent vote does not display `Preparing identity...`.
- No key-generation, username-prompt, or identity-publication request occurs during prewarming when the browser lacks usable local identity material.
- A fast first vote during readiness creates no duplicate publication or duplicate reaction record.
- Failed background readiness does not apply a reaction and leaves the user with the existing actionable first-click recovery.
- Existing manual and automatic identity creation, all current reaction types, and no-JavaScript behavior remain unchanged.
