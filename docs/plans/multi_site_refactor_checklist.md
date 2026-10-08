# Multi-Site Refactor Checklist

Use this checklist to refactor Zenmemes, Chouse, and QDB into explicit site components before adding another design. Each item is a future implementation slice; this document makes no code changes itself.

## P0 — Establish the profile contract

- [x] Extend `SiteProfileRegistry` into the canonical profile descriptor: display identity, default/permitted themes, browser/offline namespace, editorial-content key, and enabled experience keys.
- [x] Add registry validation for unique, browser-safe identifiers and safe fallback to Zenmemes for an absent or unknown profile.
- [x] Create one shared presentation-path resolver for static-output roots; replace the repeated Zenmemes empty-suffix rule in the web entry point and operational scripts.
- [x] Preserve shared repository, database, identity, approval, and content state as instance concerns—not profile concerns.

## P1 — Extract QDB as a specialized experience

- [x] Move QDB classic route matching and dispatch out of `Application` into a QDB experience module; retain every current classic URL and reject those routes for other profiles.
- [x] Move QDB navigation, welcome/search/random/add surfaces, board policy, card selection, pagination, and footer out of generic board/template branches into that module's named interfaces.
- [x] Extract QDB quote-number minting, parsing, lookup, and display-permalink logic from `LocalWriteService`, `ThreadRepository`, and `quote_card.php` into one QDB quote-number component.
- [x] Keep QDB-specific behavior specialized unless a second plausible consumer justifies a reusable capability.

## P2 — Bound presentation variation

- [x] Define named presentation slots with shared fallbacks: navigation, board card, compose surface, about sections, and branded stylesheet.
- [x] Move Zenmemes/Boston editorial content, the Chouse about section, platform-document branding, and the Zenmemes busy message into profile-owned content data or bounded partial slots.
- [x] Decide and document whether Chouse and QDB branded themes are selectable outside their own profiles; make theme-menu availability profile-owned.
- [x] Do not support arbitrary template paths, ordered theme stacks, or CSS concatenation overrides; profile overrides must select only registered slots.

## P2 — Profile browser and offline identity

> **Completed (2026-10-06):** [P2 browser/offline Step 4](multi_site_refactor_p2_browser_offline_identity/multi_site_refactor_p2_browser_offline_identity_step4_implementation_summary.md) verifies isolated browser preferences and offline/PWA runtime identity for Zenmemes, Chouse, and QDB. A shared origin supports one active root-scope worker at a time while preserving the existing logged-in session and browser-held identity.

- [x] Replace Zenmemes-only theme/density storage keys with profile-derived keys, including a one-time Zenmemes legacy-preference fallback.
- [x] Make worker cache names, cache discovery/cleanup, bootstrap validation, offline-health diagnostics, and PWA identity profile-derived.
- [x] Define one profile-aware manifest/worker delivery contract that works for dynamic pages and published static artifacts, including hosts that serve physical files directly.
- [x] Verify a matching profile cache is retained, stale cache is refreshed, and a foreign-profile cache is untouched.

## P3 — Add the regression contract

- [x] Replace fixed site literals in profile/theme/smoke/worker tests with expectations derived from the profile descriptor and selected experience.
- [x] Add a three-profile matrix covering route availability, navigation/card/chrome selection, browser namespace, PWA/cache identity, static output path, and shared instance state.
- [x] Add a fourth-site fixture before shipping the next design to prove the declared profile contract is sufficient.

## New-site gate

Write the proposal with the [New-Site Specification Style Guide](../architecture/new_site_spec_style_guide.md) before opening implementation work.

- [ ] Supply a stable ID/display identity, default/permitted themes, browser/offline namespace, editorial-content selection, and enabled experiences.
- [ ] Reuse generic routes, board/card/compose slots, shared palettes, publication machinery, and instance state by default.
- [ ] Add a specialized module only for a coherent product behavior with its own route set, domain convention, or board policy.
- [ ] Classify every proposed site-specific change as profile data, a named slot, a reusable capability with a second consumer, or a specialized module before adding a direct site-name conditional.
