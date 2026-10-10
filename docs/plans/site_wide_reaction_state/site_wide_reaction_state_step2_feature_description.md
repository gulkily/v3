# Site-Wide Reaction State — Step 2: Feature Description

> **Feature plan:** [Step 1](./site_wide_reaction_state_step1_solution_assessment.md) · [Step 2](./site_wide_reaction_state_step2_feature_description.md) · [Step 3](./site_wide_reaction_state_step3_development_plan.md) · [Step 4](./site_wide_reaction_state_step4_implementation_summary.md)

## Problem

QDB already enforces one vote per identity and quote, but renders partial,
direction-specific state. Readers cannot reliably recognize all quotes they
have already voted on or receive a disabled pair of vote controls. More
broadly, server-rendered reaction state is limited to the page that supplied
it: after a successful client-side reaction, another page can only reflect
that state after its next server render.

The sites intentionally have different reaction semantics. QDB has one
mutually exclusive vote state per quote, while the other site surfaces keep
their independently meaningful actions (for example, liking and flagging)
separate. A shared solution must improve continuity without imposing QDB's
one-vote rule on every site.

## User Stories

- As a QDB reader, I want every quote I previously voted on to show both vote
  controls disabled so I can recognize it without retrying a vote.
- As a voter, I want a successful vote to remain visibly unavailable on later
  QDB pages in this browser so I do not attempt it again.
- As a privacy-conscious reader, I want one browser identity's vote markers
  kept separate from another identity's markers on the same device.
- As a reader on any site, I want an action I have just completed to stay
  unavailable for its matching target when I navigate to another page in this
  browser.
- As a reader on a non-QDB site, I want each action to retain its existing
  independent meaning, so reacting with one tag does not disable another.

## Core Requirements

- Add one versioned, advisory browser reaction index shared by all site
  profiles. Each marker is partitioned by site browser namespace, current
  identity, target kind and ID, and reaction tag.
- Seed initial disabled state from the existing authoritative server-rendered
  state. The browser index supplements that state after successful client-side
  writes; it never grants eligibility or replaces server duplicate validation.
- For normal thread/post actions, hydrate and disable only the exact matching
  target-kind-and-tag control. Existing independent action semantics remain
  unchanged: a like does not disable a flag, or vice versa.
- For QDB, adapt every caption, legacy upvote, and legacy downvote marker into
  one `has voted` state; render and hydrate both QDB vote buttons disabled for
  that quote on listings and numeric permalinks.
- On a successful client-side write, add the exact reaction marker, hydrate
  every matching control in the document, and persist it for later pages in
  the same site and identity namespace.
- Never expose an identity's stored markers under another identity or one
  site's markers on another site. Treat unknown identity, cleared storage,
  malformed data, or unavailable storage as a cache miss rather than a voting
  restriction.
- Preserve caption labels, score updates, compact layout, current-status
  behavior, flags, static page access, and each profile's existing controls.

## Delivery Scope

- **Work type:** application change — shared client reaction-state persistence
  and hydration, QDB viewer-vote derivation/rendering, focused regression
  coverage, and Step 4 summary.
- **Out of scope:** changing the vote record format, scoring, caption catalog,
  anonymous identity policy, a reaction-history page, adding new reaction
  types, or changing the independent-action rules of non-QDB sites.

## Completion Boundary

- **Normal entry:** a known viewer opens any supported site page containing
  reactions, including a QDB listing or numeric permalink.
- **End-to-end outcome:** server-known reactions arrive with their existing
  disabled state; a new successful action disables its matching controls now
  and on later pages. On QDB, any successful vote disables the entire vote
  pair.
- **Recovery:** stale/missing browser state falls back to server state; an API
  duplicate response still applies the appropriate local disabled state and
  reports its normal result.
- **Release condition:** server state, shared client index, per-profile
  namespace, identity switch, storage failure, QDB vote-pair, browser
  reaction, and cross-site regression coverage passes.

## Risks

- **Identity leakage:** shared-device storage could show another identity's
  votes. *Earliest validation:* switch identity in one browser fixture.
  *Mitigation:* key the index by current identity and ignore unknown identity.
- **Cross-site leakage:** similarly shaped identifiers could cause an action
  from one site to disable a control on another. *Earliest validation:* hydrate
  two site-profile fixtures using the same target ID. *Mitigation:* partition
  keys by the profile browser namespace.
- **Semantic flattening:** a generic cache could make independent actions act
  like a QDB vote. *Earliest validation:* like then verify flag remains
  available in a non-QDB fixture. *Mitigation:* retain target-kind-and-tag
  markers and make QDB's aggregate `has voted` interpretation an explicit
  adapter.
- **Client/server disagreement:** stale state could mislead or leave a button
  enabled. *Earliest validation:* old vote, cleared cache, and duplicate-write
  fixtures. *Mitigation:* server seed and write validation remain authoritative.
- **Static pages:** their HTML has no viewer state. *Earliest validation:* load
  a generated quote page with a client cache. *Mitigation:* hydrate existing
  markup locally without depending on a dynamic page response.
- **Storage failure:** browser privacy settings can reject persistence.
  *Earliest validation:* throwing storage fixture. *Mitigation:* fail open to
  server-rendered controls and existing API feedback.

## Shared Component Inventory

- `SiteProfileRegistry` supplies each site's browser namespace; use it as the
  first partition of the client index rather than relying on an URL or title.
- `thread_reactions.js` owns successful thread and post reaction updates;
  extend it with the advisory index, generic exact-control hydration, and a
  QDB vote-pair adapter.
- Existing thread/post/QDB templates provide the authoritative initial state
  and the action metadata the hydrator needs; preserve their profile-specific
  markup and semantics.
- `QdbBoardPolicy` and `ThreadAndPostPageController` derive current-page QDB
  viewer vote state; extend them to expose one voted-quote set.
- `qdb_quote_actions.php` is the canonical vote pair; extend it with the
  single disabled-state input rather than directional duplication.
- `LocalWriteService` owns duplicate enforcement; reuse it unchanged.

## Simple User Flow

1. A reader opens a site page; server-known prior reactions arrive with their
   existing disabled states. QDB's known prior vote arrives as a disabled pair.
2. The browser reads only its same-site, same-identity markers and applies an
   exact reaction state to normal controls or aggregate vote state to QDB.
3. The reader completes an action; the successful response records its marker,
   updates every matching control in the document, and remembers it for later
   pages.
4. A non-QDB like leaves a same-target flag available; a QDB caption vote
   disables both QDB captions for that quote.
5. After changing identity or losing storage, server state still renders known
   reactions and the server rejects any attempted duplicate.

## Success Criteria

- A successful reaction immediately disables every exact matching control in
  the document and survives a later page navigation for that site and identity.
- Caption and legacy QDB votes disable both controls for the same viewer on
  listings, search/random results, and numeric permalinks.
- Non-QDB reactions preserve independent controls: an action marker cannot
  disable a different tag or target kind.
- Switching identity, switching site namespace, malformed/disabled storage,
  and static HTML cannot show another identity's or site's markers or bypass
  server validation.
- QDB scores, flags, current-status feedback, and established per-profile
  reaction behavior are unchanged.

Waiting for "Approved Step 2" before drafting Step 3.
