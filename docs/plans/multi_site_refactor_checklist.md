# Multi-Site Refactor Checklist

Use this checklist to refactor Zenmemes, Chouse, and QDB into explicit site components before adding another design. Each item is a future implementation slice; this document makes no code changes itself.

## P0 — Establish the profile contract

- [ ] Extend `SiteProfileRegistry` into the canonical profile descriptor: display identity, default/permitted themes, browser/offline namespace, editorial-content key, and enabled experience keys.
- [ ] Add registry validation for unique, browser-safe identifiers and safe fallback to Zenmemes for an absent or unknown profile.
- [ ] Create one shared presentation-path resolver for static-output roots; replace the repeated Zenmemes empty-suffix rule in the web entry point and operational scripts.
- [ ] Preserve shared repository, database, identity, approval, and content state as instance concerns—not profile concerns.

## P1 — Extract QDB as a specialized experience

- [ ] Move QDB classic route matching and dispatch out of `Application` into a QDB experience module; retain every current classic URL and reject those routes for other profiles.
- [ ] Move QDB navigation, welcome/search/random/add surfaces, board policy, card selection, pagination, and footer out of generic board/template branches into that module's named interfaces.
- [ ] Extract QDB quote-number minting, parsing, lookup, and display-permalink logic from `LocalWriteService`, `ThreadRepository`, and `quote_card.php` into one QDB quote-number component.
- [ ] Keep QDB-specific behavior specialized unless a second plausible consumer justifies a reusable capability.

## P2 — Bound presentation variation

- [ ] Define named presentation slots with shared fallbacks: navigation, board card, compose surface, about sections, and branded stylesheet.
- [ ] Move Zenmemes/Boston editorial content, the Chouse about section, platform-document branding, and the Zenmemes busy message into profile-owned content data or bounded partial slots.
- [ ] Decide and document whether Chouse and QDB branded themes are selectable outside their own profiles; make theme-menu availability profile-owned.
- [ ] Do not support arbitrary template paths, ordered theme stacks, or CSS concatenation overrides; profile overrides must select only registered slots.

## P2 — Profile browser and offline identity

- [ ] Replace Zenmemes-only theme/density storage keys with profile-derived keys, including a one-time Zenmemes legacy-preference fallback.
- [ ] Make worker cache names, cache discovery/cleanup, bootstrap validation, offline-health diagnostics, and PWA identity profile-derived.
- [ ] Define one profile-aware manifest/worker delivery contract that works for dynamic pages and published static artifacts, including hosts that serve physical files directly.
- [ ] Verify a matching profile cache is retained, stale cache is refreshed, and a foreign-profile cache is untouched.

## P3 — Add the regression contract

- [ ] Replace fixed site literals in profile/theme/smoke/worker tests with expectations derived from the profile descriptor and selected experience.
- [ ] Add a three-profile matrix covering route availability, navigation/card/chrome selection, browser namespace, PWA/cache identity, static output path, and shared instance state.
- [ ] Add a fourth-site fixture before shipping the next design to prove the declared profile contract is sufficient.

## New-site gate

- [ ] Supply a stable ID/display identity, default/permitted themes, browser/offline namespace, editorial-content selection, and enabled experiences.
- [ ] Reuse generic routes, board/card/compose slots, shared palettes, publication machinery, and instance state by default.
- [ ] Add a specialized module only for a coherent product behavior with its own route set, domain convention, or board policy.
- [ ] Classify every proposed site-specific change as profile data, a named slot, a reusable capability with a second consumer, or a specialized module before adding a direct site-name conditional.
