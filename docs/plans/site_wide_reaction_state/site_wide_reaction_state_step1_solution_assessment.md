# Site-Wide Reaction State — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./site_wide_reaction_state_step1_solution_assessment.md) · [Step 2](./site_wide_reaction_state_step2_feature_description.md) · [Step 3](./site_wide_reaction_state_step3_development_plan.md) · [Step 4](./site_wide_reaction_state_step4_implementation_summary.md)

## Original Query

As a user, I want to know which quotes I've already voted on and see the
buttons disabled. Let's write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`,
and consider offloading as much as possible to the client.

## Understood Intent

Each QDB quote must expose one durable “already voted” state: after any
caption or legacy vote, both vote buttons are disabled across listings and the
permalink. The browser should carry forward successful votes between rendered
pages when it can, but it must never decide eligibility—the server continues
to reject duplicate QDB votes. The browser-held state is partitioned by its
current identity and is advisory only, so a different identity or cleared
browser storage falls back safely to server-rendered state.

Current evidence: server write validation already permits only one QDB vote
per identity/quote. Rendering scans caption tags into the “upvoted” set, but
an existing legacy downvote only disables its down button; neither path
expresses the single semantic `has voted` state required by the UI.

## Problem

QDB cannot consistently show that a viewer has voted because its display model
tracks direction/tag-specific state rather than the one-vote-per-quote rule.

## Options

### Option A — Server-render a complete vote state on every page

Derive one authoritative voted-quote set for the current identity and render
both buttons disabled whenever it contains the quote.

- Pros: works without browser storage or JavaScript; accurately reflects older
  votes and another device.
- Cons: keeps repeated record scans on page rendering; successful client-side
  votes still need local DOM updates between navigation responses.

### Option B — Browser-only remembered vote index

Record successful quote votes in browser storage and disable matching controls
when a QDB page loads.

- Pros: cheap page hydration and immediate cross-page feedback; minimal
  server-render work for later pages.
- Cons: cannot recognize historical or another-device votes until cast locally;
  identity changes, cleared storage, and static pages make it incomplete.

### Option C — Server seed plus identity-scoped browser index (Recommended)

Render the authoritative current-page voted state once, then let the browser
store successful quote IDs under its current identity and apply that state to
every matching QDB control as pages load or actions finish.

- Pros: correct initial/history state, fast client-side continuity, works with
  dynamic and static views after hydration, and moves repeated presentation
  work to the browser.
- Cons: needs safe identity-key rotation/clearing, conservative handling before
  identity is known, and tests for stale client state; server enforcement must
  remain unchanged.

## Recommendation

Adopt Option C. Replace directional display flags with one server-authoritative
`has voted` state that includes all caption and legacy QDB vote forms, and
disable both vote controls for it. A versioned, identity-scoped browser index
should immediately mark a successful vote and hydrate matching cards on later
pages; it is a UI cache only and never authorizes a write. Step 2 should decide
the voted presentation (disabled labels/status), lifecycle on identity changes
and private-storage failure, and behavior for anonymous/static pages.

This is a viable vertical slice: a viewer opens a QDB page and immediately
sees prior voted quotes disabled; after voting another quote, its two controls
disable and remain so on another listing or permalink without waiting for a
fresh server-derived page state.

Waiting for "Approved Step 1" before drafting Step 2.
