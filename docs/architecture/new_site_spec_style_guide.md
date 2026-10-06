# New-Site Specification Style Guide

Use this guide to propose a new site profile before implementation. A good spec explains the product experience and proves that the design fits the shared platform; it does not begin with files, templates, or conditionals.

## Write for a decision

- Lead with the audience, editorial purpose, and one-sentence experience promise.
- Describe what differs from the shared forum and why that difference matters to readers or contributors.
- State decisions, constraints, and open questions plainly. Do not use a site name as a substitute for a requirement.
- Keep the spec small: reuse is the default, and every exception needs a reason.

## Required sections

### 1. Intent and boundary

State the audience, content focus, voice, and success outcome. Name what remains shared: canonical repository, read model, identities, approvals, generic publishing, and instance state. Explicitly exclude unrelated redesigns or a separate deployment unless that is the request.

### 2. Profile declaration

Provide these values in a compact table:

| Field | Rule |
| --- | --- |
| Stable ID | Lowercase, browser-safe identifier; stable once published. |
| Display identity | Reader-facing name and short description. |
| Browser namespace | Unique, browser-safe value for preferences, diagnostics, manifest, and cache identity. |
| Themes | Default theme plus the permitted shared and branded themes. |
| Editorial content | Existing content selection or a concise request for new profile-owned copy. |
| Experiences | Generic forum by default; name a specialized experience only when justified. |
| Presentation slots | Select registered navigation, card, compose, about, editorial, and stylesheet slots; name a new slot only when no existing selection fits. |

### 3. Reuse and variation ledger

For every requested difference, use this format:

| Need | Reuse by default | Classification | Rationale and acceptance check |
| --- | --- | --- | --- |
| Reader-facing difference | Existing route, slot, palette, or service | Profile data, named slot, reusable capability, or specialized module | Why reuse is insufficient and how the result will be verified |

Do not list a direct site-name conditional as a solution. If no classification fits, record it as an open design question and stop for architecture review.

### 4. Experience and route policy

List the normal board, compose, and detail routes. A specialized experience must name its coherent product behavior and its route set, domain convention, or board policy. Also state which specialized routes must return the normal rejection outside that experience.

### 5. Presentation and content policy

Describe choices in terms of registered slots and profile-owned data: navigation, cards, compose surface, about copy, editorial content, and branded stylesheet. Specify theme-menu availability and accessibility or content constraints that matter to the new audience.

### 6. Browser, offline, and static delivery

State the browser namespace, expected preference isolation, PWA display identity, cache isolation, and static-output root. A profile must not inherit another profile's browser keys, worker cache family, or generated static artifacts.

### 7. Shared-state and deployment boundary

Confirm that repository, database, identities, approvals, and sessions remain instance-owned. Treat a separate repository, database, or vhost as an explicit deployment decision—not as profile behavior—and describe it in the deployment plan if required.

### 8. Acceptance and recovery

Define the normal profile-selection flow, visible end-to-end result, and recovery behavior for an absent or unknown selection. Require the descriptor-derived regression matrix and a fixture or equivalent proof before the design ships.

## Tone and precision rules

- Prefer “select the registered quote-card slot” to “make it look like a quote site.”
- Prefer “the generic forum experience rejects `/example` with 404” to “other sites should not show it.”
- Use a concrete reason for every special behavior; avoid adjectives such as “custom,” “special,” or “branded” without naming the affected contract.
- Separate a product decision from an implementation decision. “Readers need numbered excerpts” is a product decision; the component that supplies it is an implementation decision.
- Record unknowns as questions with an owner and a decision point. Do not conceal them in an implementation note.

## Ready-to-plan checklist

- [ ] The profile declaration is complete and internally consistent.
- [ ] Every difference is classified in the reuse and variation ledger.
- [ ] Existing routes, slots, palettes, publication machinery, and instance state are reused unless a rationale says otherwise.
- [ ] Any specialized module has a coherent behavior boundary and route or policy contract.
- [ ] Browser/offline and static-delivery identity are isolated by namespace.
- [ ] The normal flow, rejection/fallback behavior, and regression proof are explicit.

Use the completed spec as the input to the feature-development process. Implementation should not add a direct site-name branch that the spec did not classify.
