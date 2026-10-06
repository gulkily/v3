# Multi-Site Refactor Review — Step 4: Documentation Summary

> **Feature plan:** [Step 1](./multi_site_refactor_review_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_review_step2_feature_description.md) · [Step 3](./multi_site_refactor_review_step3_development_plan.md) · [Step 4](./multi_site_refactor_review_step4_implementation_summary.md)

> **Scope exception:** Step 4 is documentation-only by explicit request. No application, asset, test, configuration, runtime-state, or deployment changes were made; the normal Step 4 branch/commit protocol does not apply.

## Stage 1 - Conditional inventory

- Changes:
  - Confirmed `SiteProfileRegistry` owns only name, default theme, and composer prompt; it does not yet own profile capabilities or browser identity.
  - Located QDB branches in `Application`, `TemplateRenderer`, `BoardPageController`, `ComposeAndAccountKeyController`, `LocalWriteService`, `ThreadRepository`, `BoardViewOptions`, board/quote templates, and QDB-specific pages.
  - Located Zenmemes-only browser/offline names in the layout, theme/density/registration/health scripts, service worker, manifest, `FrontController`, and static-output path selection.
  - Located Chouse-specific behavior in the branded theme and `about.php`; the direct content conditional is the only runtime Chouse branch found.
- Verification:
  - Read-only repository-wide searches and call-path review of profile, route, render, write, browser, and static-publication boundaries.
- Notes:
  - QDB is a distinct product experience, not merely a visual theme. Chouse is currently primarily a presentation/content variant.

## Stage 2 - Recommended component boundaries

- Changes:
  - **Profile descriptor (declarative):** extend `SiteProfileRegistry` with stable identity, default theme, browser/offline namespace, allowed themes, editorial-content key, and enabled experience keys.
  - **Profile experience module (specialized):** put QDB's classic routes, navigation, welcome/search/add pages, board/card policy, footer, and pagination policy behind one QDB experience entry point. Do not add a generic “if QDB” capability switch to every shared controller.
  - **Quote-number domain component (specialized):** own QDB quote-ID minting, parsing, lookup, and display permalink rules in one component instead of spreading the `-qdb-<N>` convention across write, repository, and template code.
  - **Named presentation slots (bounded):** allow a profile to select known shared slots—navigation, board card, compose surface, about sections, and branded stylesheet—while retaining a shared default for every slot. Do not permit arbitrary template-path overrides or stacked themes.
  - **Browser/offline identity service (declarative):** expose one profile-derived contract to page layout, preference scripts, PWA registration, worker, manifest, health checks, bootstrap validation, and static-artifact publishing.
  - **Static presentation-path resolver (shared):** own the profile suffix/default convention now duplicated by `public/index.php` and three operational scripts.
- Verification:
  - The boundaries correspond to existing canonical seams; no proposed component owns repository, database, identity, approval, or content state.
- Notes:
  - Pollyanna's fallback idea is useful only in bounded form: named, shared slots with explicit fallbacks. Its ordered multi-theme lookup, special CSS concatenation, and theme-name checks should not be adopted.

## Stage 3 - Implementation-ready refactor backlog

| Priority | First slice | Current evidence | Target owner / outcome | Compatibility and verification |
| --- | --- | --- | --- | --- |
| P0 | Profile runtime identity | `SiteProfileRegistry`; `templates/layout.php`; Zenmemes preference/cache/manifest literals | Profile descriptor plus one browser/offline identity contract | Preserve current Zenmemes keys/cache as legacy input; test all three profiles and unknown-profile fallback. |
| P0 | Static presentation-path resolver | `public/index.php`; publish/task/diagnostic scripts repeat the Zenmemes empty-suffix rule | Shared resolver used by web and CLI entry points | Keep existing Zenmemes and profile-specific output paths; test selected-profile paths without changing repository/database defaults. |
| P1 | QDB experience routing module | `Application` contains QDB-only route and numeric-permalink branches | QDB module contributes its route matcher/handlers; generic application dispatches a selected experience | Preserve every classic URL and make non-QDB rejection explicit; route matrix covers all three profiles. |
| P1 | QDB board presentation module | `BoardPageController`, `TemplateRenderer`, `board.php`, QDB pages contain QDB flags/branches | QDB module supplies navigation, board policy, card renderer, footer, and special pages through named interfaces | Preserve generic board output byte/behavior expectations; QDB board, random, search, add, and paginated URLs retain behavior. |
| P1 | QDB quote-number component | `LocalWriteService`, `ThreadRepository`, and `quote_card.php` parse/store `-qdb-<N>` | QDB quote-number service/value object provides mint, parse, lookup, and display link | Imported and newly authored quotes retain existing URLs/numbers; non-QDB IDs remain opaque. |
| P2 | Bounded editorial/presentation slots | `about.php`, `about.css`, platform docs, busy message, branded theme files | Profile-owned content data or known partial slots with shared fallback | Preserve Zenmemes copy and Chouse section; explicitly decide which QDB pages replace rather than inherit generic copy. |
| P2 | Theme availability policy | global `ThemeRegistry` exposes Chouse/QDB themes to all profiles | Profile descriptor declares permitted/default branded themes while shared palettes remain global | Confirm whether cross-profile branded themes are intentionally selectable before restricting the menu. |
| P2 | Browser/offline implementation | layout/scripts, worker, manifest, `FrontController`, router, static builder | Browser/offline identity contract applied end-to-end | Validate matching/stale/foreign cache handling, worker/manifest delivery, and generated static artifacts; require an operator check for production web-server behavior. |
| P3 | Refactor test matrix | profile/theme/smoke/worker tests contain fixed site literals | Registry- and experience-derived fixtures and assertions | Test profile contract, route availability, card/chrome selection, browser namespace, PWA identity, and state-path isolation. |

## Stage 4 - Fourth-site design contract

- Changes:
  - A new site supplies: stable ID/display identity; default/permitted themes; browser/offline namespace; editorial-content selection; enabled experience keys; and, only when necessary, bounded presentation-slot implementations.
  - A new site reuses: repository, read model, identities, approvals, generic routes, generic board/card/compose slots, shared palettes, and static publication machinery by default.
  - A new specialized module is justified only when it adds a coherent product behavior with its own route set, domain convention, or board policy—QDB is the current example.
  - Before adding a direct site-name check, classify it: profile data, named slot, reusable capability with a second plausible consumer, or specialized module. If none apply, record why a new seam is needed.
- Verification:
  - Walked the contract against current sites: Zenmemes uses defaults plus site metadata; Chouse uses branded theme/content slots; QDB uses the QDB experience and quote-number component.
- Notes:
  - Do not fork shared instance state for a new design. A separate repository/database remains an explicit deployment choice, not a profile feature.

## Future implementation sequence

1. Establish and test the expanded profile descriptor plus static presentation-path resolver.
2. Extract QDB route and board-presentation policy behind a single experience boundary.
3. Extract QDB quote-number behavior into its domain component.
4. Move editorial/theme/browser-offline decisions to bounded profile slots and contracts.
5. Replace fixed-literal tests with the full profile/experience matrix, then add a fourth-site fixture before shipping another design.

## Open decisions before implementation

- Whether Chouse and QDB branded themes should remain selectable from every profile.
- Whether the default generic `/about/`, platform docs, and busy copy should be profile data, profile partials, or intentionally Zenmemes-only routes.
- The exact Zenmemes preference/cache migration and expiry policy.
- The profile-aware worker/manifest delivery mechanism for hosts that serve physical public files directly.
