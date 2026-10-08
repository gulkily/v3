> **Feature plan:** [Step 2](./mitrapclub_branding_fixes_step2_feature_description.md) · [Step 3](./mitrapclub_branding_fixes_step3_development_plan.md) · [Step 4](./mitrapclub_branding_fixes_step4_implementation_summary.md)

## Problem

The `mitrapclub` site profile still shows non-club-specific defaults in two places — a lowercase slug as the displayed site name, and a shared generic busy-page message — undercutting the branding the rest of the profile already establishes.

_Scope note: the Facebook-link fix and the per-profile favicon/manifest-icon item from the original checklist are both deferred out of this cycle — no confirmed Facebook page URL or icon asset is available yet. The favicon item moves to Cycle 3 (visual identity); the Facebook link stays unscheduled until the real URL is known._

## User Stories

- As a visitor, I want the site name in the header/tab title to read "MIT Rap Club" so the branding is consistent with the about page.
- As a visitor who hits a busy page, I want club-appropriate copy instead of the generic "Temporarily Busy" text shared by two other profiles.

## Core Requirements

- Header/title display reads the `mitrapclub` profile's `displayName` ("MIT Rap Club") instead of its lowercase `name`, with no visible change for `zenmemes`/`chouse`/`qdb` (where `name` and `displayName` are already identical).
- `mitrapclub`'s busy-page copy (`ProfilePresentationContent::EDITORIAL['mitrapclub']`) uses club-voiced text instead of the shared "Temporarily Busy" string.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** a visitor loads any `mitrapclub` page, or deliberately hits a busy page.
- **End-to-end outcome:** site name reads "MIT Rap Club" wherever rendered; busy page shows club-voiced copy.
- **Needed recovery:** none beyond today's behavior — other profiles render unchanged.
- **Release condition:** `./v3 test` passes in full; `zenmemes`/`chouse`/`qdb` render byte-identical to before this change.

## Risks

- Changing the site-name source from `name` to `displayName` could affect more than display if some call site treats it as an identifier rather than a label. Earliest validation: grep every `SiteConfig::siteName()` call site before Step 3 stages begin. Mitigation: scope the change narrowly to display-only call sites; leave `name`/`browserNamespace` (used as identifiers elsewhere) untouched.
- This is a two-item cycle with little room for surprises; the main risk is scope creep pulling the deferred Facebook/favicon items back in. Mitigation: hold the line at Step 3 — those two stay out per the scope note above.

## Shared Component Inventory

- `SiteConfig::siteName()` and its call sites (`templates/layout.php` and any other template reading `siteName`) — reuse this single choke point; just change which profile field it returns.
- `src/ForumRewrite/ProfilePresentationContent.php::EDITORIAL['mitrapclub']` (`busyTitle`/`busyHeading`/`busyMessage`) — reuse the existing per-profile content mechanism added in the prior feature; only the `mitrapclub` strings change.

## Simple User Flow

1. Visitor loads any `mitrapclub` page; header/title reads "MIT Rap Club".
2. Visitor hits a busy page; sees club-voiced copy.

## Success Criteria

- Header/title shows "MIT Rap Club" for the `mitrapclub` profile; other profiles unchanged.
- Busy page shows new club-voiced copy for `mitrapclub`; other profiles unchanged.
- `./v3 test` passes in full with no regression to other profiles.

Waiting for "Approved Step 2" before drafting Step 3.
