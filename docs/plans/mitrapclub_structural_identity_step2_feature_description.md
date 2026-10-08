> **Feature plan:** [Step 1](./mitrapclub_structural_identity_step1_solution_assessment.md) · [Step 2](./mitrapclub_structural_identity_step2_feature_description.md) · [Step 3](./mitrapclub_structural_identity_step3_development_plan.md) · [Step 4](./mitrapclub_structural_identity_step4_implementation_summary.md)

## Problem

The `mitrapclub` profile still carries three pieces of shared, not-club-specific config as-is: the generic forum nav (labels + the generic `/tools/` operator page sitting in it), the full 13-theme switcher (letting a visitor leave the club's look entirely), and the single shared `favicon.ico`/PWA icon (not club-branded, even though the manifest's `name`/`short_name` already are).

## User Stories

- As a `mitrapclub` visitor, I want the nav to reflect what the club's site is actually for, so operator-facing tooling (`/tools/`) isn't presented as a top-level club feature.
- As a `mitrapclub` visitor, I want a settled answer on whether I can switch to a non-club theme, so the site's look is either intentionally club-only or intentionally flexible — not an oversight.
- As a member of another profile (`zenmemes`/`chouse`/`qdb`), I want no change to my nav, theme list, or favicon.

## Core Requirements

- `mitrapclub`'s nav decision is made explicit: either `/tools/` is dropped from the visible nav (reachable by direct URL only, same pattern qdb already uses for Account/Invite) or kept, with a stated reason either way — not left as an unexamined default.
- `mitrapclub`'s `permittedThemes` decision is made explicit: either trimmed to club-only (`mitrapclub` + `auto`) or kept at full breadth (matching `qdb`/`chouse`'s precedent) — with a stated reason.
- Per-profile favicon/PWA-icon **capability** is added (mirroring the existing per-profile branded-stylesheet selection pattern), so a profile can override the shared default icon when an asset exists for it.
- No actual new favicon artwork ships in this feature: no club icon asset exists yet (confirmed unavailable in Cycle 1). `mitrapclub` keeps using the shared default `favicon.ico`/manifest icons until a club asset is supplied in a later feature. This feature only builds the override mechanism and proves it's wired correctly, so adding mitrapclub's real icon later is a data change, not new plumbing.
- `zenmemes`/`chouse`/`qdb` get no visible or behavioral change from any part of this feature.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** a visitor loads any page on `mitrapclub` (nav/theme-switcher visible everywhere; favicon loaded on every page).
- **End-to-end outcome:** `mitrapclub`'s nav and theme-switcher reflect a deliberate decision (stated in Step 3); the favicon/icon override mechanism exists and is exercised by at least one non-default profile value (even if that value is just "use the shared default explicitly" for `mitrapclub` itself) to prove it's live code, not dead plumbing.
- **Needed recovery:** none — config/presentation only, no new runtime failure mode.
- **Release condition:** `./v3 test` passes in full; `zenmemes`/`chouse`/`qdb` nav, theme list, and favicon/manifest output are byte-identical to today.

## Risks

- No favicon asset exists for `mitrapclub` yet, so "club-branded favicon" (as the checklist originally phrased it) can't actually ship visually in this feature. Earliest validation: this Step 2's scoping. Mitigation: scope down to "override mechanism only" (see Core Requirements) — proven correct by the mechanism existing and being selectable, not by a new icon appearing. Revisit with real art in a later feature.
- The nav and theme-freedom items are independent either/or calls with no single "correct" answer — risk of relitigating them past Step 3. Earliest validation: Step 3 states and commits to one answer for each, same as this Step 2 already proposes a default (drop `/tools/`, keep theme breadth matching `qdb`/`chouse`'s precedent) that Step 3 can confirm or override.
- `qdb`'s nav precedent (`QdbPresentation::navigation()`) is a fully custom nav array tied to qdb-only routes (`/latest`, `/top`, `/leetness`, `/random`, `/add`, `/search`) — it is not a generic "trim" mechanism. Mitigation: `mitrapclub` uses the existing generic `forum` nav array (shared with `zenmemes`/`chouse`) and only removes/keeps the `/tools/` entry; it does not get a bespoke nav array or new routes.

## Shared Component Inventory

- `src/ForumRewrite/View/TemplateRenderer.php` (nav-building method, ~line 258) — reuse; add a presentation-slot-driven conditional for whether `/tools/` appears, following the existing `$isQdbNavigation` conditional's shape (not a new nav array).
- `src/ForumRewrite/SiteProfileRegistry.php` (`permittedThemes` per profile) — reuse; change only `mitrapclub`'s array value once Step 3 settles the decision.
- `src/ForumRewrite/Host/BrowserRuntimeAssetRenderer.php::manifest()` and `src/ForumRewrite/Host/StaticArtifactBuilder.php` (favicon copy loop, ~line 217) — extend the existing per-profile pattern (same shape as `brandedStylesheet`'s `PresentationSlotRegistry` slot) to resolve a per-profile favicon path instead of the single hardcoded `/public/favicon.ico`/`.gif`, falling back to today's shared files when a profile doesn't override them.
- `src/ForumRewrite/PresentationSlotRegistry.php` — reuse the existing slot-choice mechanism; add a `favicon` (or similarly named) slot if the override needs slot-style validation, rather than inventing a new config shape.

## Simple User Flow

1. Visitor opens any page on `mitrapclub`: nav shows the decided item set (with or without `/tools/`); theme switcher shows the decided theme list.
2. Browser requests `/favicon.ico` / `manifest.webmanifest` icons: served via the new per-profile-capable path, currently resolving to the same shared default bytes for `mitrapclub` (and unchanged for every other profile).
3. Visitor on `zenmemes`/`chouse`/`qdb` sees no change anywhere.

## Success Criteria

- `mitrapclub`'s nav and `permittedThemes` match the decision stated and justified in Step 3.
- The favicon/icon override mechanism is real (demonstrably selectable per profile), even though no new artwork ships.
- `zenmemes`/`chouse`/`qdb` nav, theme list, and favicon/manifest output are byte-identical to before this feature.
- `./v3 test` passes in full.

Waiting for "Approved Step 2" before drafting Step 3.
