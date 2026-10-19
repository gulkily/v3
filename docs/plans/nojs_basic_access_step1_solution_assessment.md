> **Feature plan:** [Step 1](./nojs_basic_access_step1_solution_assessment.md) · [Step 2](./nojs_basic_access_step2_feature_description.md) · [Step 3](./nojs_basic_access_step3_development_plan.md) · [Step 4](./nojs_basic_access_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

As a user with JavaScript disabled, I want to have access to basic features of the site. Obviously, I won't be able to use in-browser signing, but I want to be able to use basic features, perhaps aided by fingerprinting. This will probably be a big lift. Write Step 1.

## Problem

Visitors need useful browsing and basic participation with JavaScript disabled, without weakening signed identity or site access rules.

## Current footing

- [Board navigation](../../templates/pages/board.php) already renders links and pagination; [thread](../../templates/partials/thread_compose_form.php) and [reply](../../templates/partials/reply_form.php) composers already use HTML POST forms. This provides a foundation, not proof that complete journeys work without scripts.
- [Existing access rules](../reference/authentication_modes.md) allow anonymous attribution but retain members-only gates and action-specific signatures; automatic identity recovery currently requires browser signing.
- “Basic features” and “fingerprinting” remain scope choices: a browser/network fingerprint is a weak continuity or abuse signal; an OpenPGP fingerprint identifies a public key. Neither proves control of that key.

## Option A — Progressively enhance the existing site

Make normal links and HTML forms support the essential journeys, with scripts adding signing and richer interactions when available.

- Pros: reuses existing routes, rendering, and policy; one interface to maintain; improves resilience for everyone.
- Cons: requires auditing hidden menus, script-dependent controls, submission errors, and instance differences; some features remain unavailable.

## Option B — Provide a separate basic interface

Offer a small server-rendered entry point with its own navigation and forms for a selected feature set.

- Pros: explicit, bounded surface; can simplify difficult existing interactions.
- Cons: duplicates presentation and navigation; risks feature drift and a second-class experience; shared permissions and writes still need integration.

## Option C — Add persistent guest participation

Extend basic HTML participation with a server-issued guest cookie/session; optionally use coarse request signals for abuse controls where justified.

- Pros: can support continuity and limited duplicate suppression without browser signing; complements A or B.
- Cons: adds lifecycle, privacy, retention, and abuse-policy decisions; cookies can be cleared and fingerprints collide or change. Neither provides verified authorship, one-person-one-vote, or member access.

## Recommendation and viable first slice

**Choose A; defer C until a specific continuity need is demonstrated.** Avoid making fingerprint collection a prerequisite for basic access. Keep B as a fallback only if assessment reveals a journey that cannot reasonably share the existing interface.

- First releasable slice: on a public instance, enter through ordinary navigation, browse a board and thread with pagination, submit an anonymous text thread or reply where policy permits, recover a validation error with draft intact, and follow the success result to the persisted content—all with JavaScript disabled from the start. Prove which parts already work before estimating changes; keep the slice within a day/eight planning stages or split it further.
- Preserve members-only gates. Existing authenticated sessions may remain useful; fresh entry and recovery without browser signing need a separate decision, potentially external signing. Do not silently open protected content.
- Defer reactions/voting policy, signed actions, key management, encrypted messaging, offline tooling, and external-signing workflows to separately assessed slices. Search and additional site variants can follow once their existing routes and policy are inventoried.
- Step 2 should confirm the target instance and essential journeys, cookie expectations, and whether “fingerprinting” means guest continuity or key lookup. Acceptance must exercise normal entry, successful participation, and recovery in an actual browser with scripts disabled; source inspection alone is insufficient. Keep UI copy minimal and essential recovery visible.

Approval status: Approved Step 1 received 2026-10-19; no implementation authorized.
