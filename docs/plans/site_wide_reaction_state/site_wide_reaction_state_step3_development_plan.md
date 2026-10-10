# Site-Wide Reaction State — Step 3: Development Plan

> **Feature plan:** [Step 1](./site_wide_reaction_state_step1_solution_assessment.md) · [Step 2](./site_wide_reaction_state_step2_feature_description.md) · [Step 3](./site_wide_reaction_state_step3_development_plan.md) · [Step 4](./site_wide_reaction_state_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a known reader opens any reaction-bearing site page.
- **End-to-end outcome:** authoritative existing reactions arrive disabled; a
  successful action remains disabled on matching controls on later pages for
  that site and identity. Any QDB vote disables its complete caption pair.
- **Required recovery:** invalid, unavailable, stale, or cross-identity/site
  cache data is ignored; server rendering and duplicate validation remain
  effective.
- **Deployment/external verification:** no migration or new service; ship with
  fingerprinted assets and verify in a local browser across two site profiles.
- **Release condition:** focused PHP, browser-runtime, profile-isolation, and
  static-QDB checks pass, alongside the applicable full suite and diff check.

## Key Risks

- **High risk:** a client marker leaks across identities or sites. Impact:
  incorrect disabled controls. Early validation: identity and namespace-switch
  browser fixtures. Mitigation: versioned keys include both identities and
  `BrowserRuntimeProfile` namespace; invalid storage is a cache miss.
- **High risk:** generic hydration adopts QDB's mutual-exclusion rule. Impact:
  readers cannot use independent reactions. Early validation: same-target
  like/flag fixture. Mitigation: generic matching is exact by target kind, ID,
  and tag; QDB aggregation is isolated to its adapter.
- Server/client state may disagree. Impact: confusing presentation. Early
  validation: cleared-cache and duplicate-write fixtures. Mitigation: server
  render/write paths stay authoritative and accepted duplicate results hydrate.

## Stage 1

- Goal: expose one authoritative QDB `has voted` presentation state.
- Dependencies: approved Steps 1–2.
- Expected changes: extend QDB listing and permalink viewer-state contracts to
  aggregate caption plus legacy vote tags; pass one disabled-state input to the
  shared QDB action partial.
- Verification approach: rendering fixtures cover caption, legacy up/down,
  flag-only, anonymous, listing, and numeric-permalink states.
- Risks or open questions:
  - Impact: a flag could incorrectly disable voting.
  - Early warning / validation: flag-only rendering fixture.
  - Mitigation: aggregate only QDB vote tags.
- Canonical components/API contracts touched: `QdbBoardPolicy`,
  `ThreadAndPostPageController`, `qdb_quote_actions.php`.

## Stage 2

- Goal: establish safe shared browser reaction-state primitives.
- Dependencies: Stage 1.
- Expected changes: add a versioned advisory-marker format and safe storage
  access; derive its namespace from `window.forumBrowserRuntime` and identity
  from the existing browser-identity helper or successful API response.
- Verification approach: browser-runtime fixtures prove malformed/unavailable
  storage fails open and identities/namespaces select distinct marker sets.
- Risks or open questions:
  - Impact: a missing identity disables actions from an untrusted cache.
  - Early warning / validation: no-identity hydration fixture.
  - Mitigation: do not read or write advisory markers until identity is known.
- Canonical components/API contracts touched: `thread_reactions.js`,
  `BrowserRuntimeProfile`, `window.__forumBrowserIdentity`.

## Stage 3

- Goal: hydrate and persist independent reactions consistently across sites.
- Dependencies: Stage 2.
- Expected changes: have the existing thread/post reaction handler recognize
  exact persisted markers, update all matching controls after successful or
  accepted-duplicate responses, and retain established feedback/score behavior.
- Verification approach: browser fixtures cover thread/post target separation,
  like/flag independence, duplicate responses, and same-document duplicates.
- Risks or open questions:
  - Impact: a post marker alters a thread control with the same identifier.
  - Early warning / validation: colliding-ID fixture.
  - Mitigation: include target kind in marker and matching logic.
- Canonical components/API contracts touched: `thread_reactions.js`, existing
  `apply_thread_tag`/`apply_post_tag` response contracts, reaction markup.

## Stage 4

- Goal: adapt shared markers to QDB's one-vote UI semantics.
- Dependencies: Stages 1–3.
- Expected changes: identify QDB action roots and known caption/legacy vote
  tags so either vote marker disables and confirms the pair without affecting
  its separate flag; hydrate dynamic and static QDB markup locally.
- Verification approach: browser and HTML fixtures cover either caption,
  legacy up/down markers, pair-wide disablement, preserved flag control, and
  static quote pages.
- Risks or open questions:
  - Impact: a caption catalog change leaves an old valid marker unrecognized.
  - Early warning / validation: historical-tag fixture.
  - Mitigation: use the existing QDB vote-tag policy/markup contract, including
    legacy tags, rather than current label text.
- Canonical components/API contracts touched: `thread_reactions.js`,
  `qdb_quote_actions.php`, QDB caption catalog/policy contract.

## Stage 5

- Goal: prove profile-safe release readiness and record implementation evidence.
- Dependencies: Stages 1–4.
- Expected changes: complete focused regression coverage, local cross-profile
  browser verification, asset/static checks, and the Stage 4 summary evidence.
- Verification approach: targeted PHP and Node-backed suites, relevant full
  suite, `git diff --check`, and manual navigation after a QDB vote and a
  non-QDB like/flag action.
- Risks or open questions:
  - Impact: an untested profile regresses while QDB passes.
  - Early warning / validation: profile-matrix and generic-reaction suites.
  - Mitigation: halt release on any changed established reaction contract.
- Canonical components/API contracts touched: existing reaction browser tests,
  QDB rendering tests, profile regression contract, Step 4 summary.

Waiting for "Approved Step 3" before beginning Step 4.
