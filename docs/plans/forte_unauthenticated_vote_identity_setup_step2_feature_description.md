# Forte Unauthenticated Vote Identity Setup — Step 2: Feature Description

## Problem

An unsigned Forte visitor who votes before preparing a browser identity can receive a generic reload message that conceals the failed prerequisite and leaves them unable to act.

## User stories

- As a first-time Forte voter, I want my vote to begin browser identity setup so that I can vote without first opening the composer.
- As a voter affected by a loading failure, I want a useful, actionable explanation so that I know whether to retry, reload, or change my browser/context.
- As an operator, I want to identify an inconsistent Forte release or unavailable signing asset so that a deployment fault is distinguishable from an identity problem.

## Core requirements

- A first Like or Flag on Forte must invoke the existing browser identity preparation path when no ready identity is present.
- A failure to load the identity runtime must retain its actionable cause rather than being replaced by the generic availability message.
- The interaction must preserve the existing identity, signing, and successful reaction behavior for returning voters and composer-first users.
- Forte must continue to load signing assets only when interaction requires them.
- Release verification must detect an incompatible Forte page/asset combination before it reaches voters.

## Shared component inventory

- **Forte Like and Flag controls:** extend the canonical reaction interaction; no new vote UI or endpoint.
- **Shared reaction feedback:** extend the existing in-place feedback treatment to report identity-runtime failures clearly.
- **Lazy browser-signing loader:** reuse as the sole on-demand signing entry point; extend its observable failure contract as needed.
- **Browser identity preparation:** reuse the canonical identity flow for setup, recovery, and any environment-specific guidance.
- **Forte page asset configuration and release checks:** extend the existing fingerprinted asset/release contract; no parallel asset pipeline.
- **Existing reaction APIs:** reuse unchanged; they remain reachable only after identity preparation succeeds.

## User flow

1. A visitor without a ready browser identity presses Like or Flag in Forte.
2. Forte loads the identity runtime and starts the existing identity-preparation flow.
3. On success, Forte continues the original vote and shows its normal applied state.
4. On failure, Forte leaves the vote unapplied and shows the specific recovery guidance; operators can trace the failed release/asset condition.

## Success criteria

- In a fresh browser profile, first Like and first Flag on Forte each reach identity preparation without prior composer interaction.
- A simulated missing or failed signing asset produces a specific actionable message and never the generic reload-only message.
- Existing prepared-identity voting and composer-first voting retain their current successful outcomes.
- Automated coverage verifies the first-vote path and the failed-load path, and release verification confirms the referenced Forte assets are available and mutually compatible.
