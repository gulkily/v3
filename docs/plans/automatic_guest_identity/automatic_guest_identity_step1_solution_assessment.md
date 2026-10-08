> **Feature plan:** [Step 1](./automatic_guest_identity_step1_solution_assessment.md) · [Step 2](./automatic_guest_identity_step2_feature_description.md) · [Step 3](./automatic_guest_identity_step3_development_plan.md) · [Step 4](./automatic_guest_identity_step4_implementation_summary.md)

## Original Query

As an operator, I want to have a feature flag available to me where instead of waiting for the user to begin an action before prompting for a username, the server will automatically generate a guest keypair, so that the user can interact with the site right away when they choose to. There should be a separate user flag for whether the public key is automatically published right away or automatically published on first use.

## Understood Intent

Enable an operator-controlled, no-prompt guest identity bootstrap while retaining a browser-local keypair and a per-browser choice between immediate and first-use public-key publication.

## Problem Statement

New visitors currently encounter identity setup only when they initiate a signed action, adding delay and a username prompt at the moment they want to interact.

## Option A — Site toggle with browser-local guest bootstrap and per-browser publication preference

Expose a default-off mutable site flag that directs the browser to create a `guest` keypair on page load; persist a per-browser preference that either publishes it immediately or defers publication until the first signed action.

- Pros: preserves the existing browser-held private-key model; gives operators and visitors the requested independent controls; keeps unused identities unpublished by default.
- Cons: key generation adds background browser work; the user preference applies to that browser rather than a server profile before publication.

## Option B — Server-generated guest identities

Have the server create and assign guest keypairs before a visitor acts.

- Pros: centralizes generation and can begin before browser signing assets load.
- Cons: conflicts with the existing privacy model because the server would need to create or handle private key material; introduces identity/session lifecycle and abuse-control scope beyond this feature.

## Option C — Prompt for a username on page load

Move the existing username prompt earlier without creating an automatic guest identity.

- Pros: small conceptual change and preserves named first-use identities.
- Cons: does not meet the no-prompt guest interaction goal; front-loads friction for visitors who never interact.

## Recommendation

Choose **Option A**. It is a viable vertical slice: the server-controlled flag enables immediate browser-local guest preparation, while a separate browser preference chooses immediate versus first-use publication. Default both the site flag to off and deferred publication to preserve current behavior and avoid publishing unused visitor identities.
