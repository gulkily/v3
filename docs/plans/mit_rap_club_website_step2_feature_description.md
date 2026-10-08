> **Feature plan:** [Step 1](./mit_rap_club_website_step1_solution_assessment.md) · [Step 2](./mit_rap_club_website_step2_feature_description.md) · [Step 3](./mit_rap_club_website_step3_development_plan.md) · [Step 4](./mit_rap_club_website_step4_implementation_summary.md)

## Problem

MIT Rap Club has no online home; this app already supports branded community instances (`chouse`, `qdb`) but has no site_id, theme, or content configured for the club yet.

## User Stories

- As a club visitor, I want to land on a `mitrapclub`-branded board so that I recognize this as the club's own space, not a generic forum.
- As a club member, I want to post and reply about cyphers, events, and media so that the club's updates live in one place instead of staying scattered across social platforms.
- As a visitor, I want to find links to the club's YouTube, Instagram, and Facebook so that I can follow them where they're already active.
- As a club officer, I want upcoming events to stand out on the board so that members know what's next without digging.

## Core Requirements

- New `mitrapclub` site_id in `SiteProfileRegistry`, deployed as its own instance (own repo root/database/static-artifact root via the existing `FORUM_SITE_ID`/`FORUM_REPOSITORY_ROOT`/`FORUM_DATABASE_PATH` env vars), using the existing `forum` experience — no new routes or experience class.
- New `mitrapclub` visual theme (new `ThemeRegistry` entry + stylesheet), selected via the existing `brandedStylesheet` presentation slot, reflecting the cypher/grassroots look referenced in Step 1 (`cypherposium.com`).
- New about/editorial copy introducing the club via `ProfilePresentationContent`, reusing the existing about/editorial rendering pipeline.
- Reuse the existing composer, nav, board, and thread_card partials as-is (generic `forum`/`thread` presentation slots) — threads double as discussion, event announcements, and media shares per Step 1's Option C.
- Visible, standing links to the club's YouTube, Instagram, and Facebook from the board/about pages.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** a visitor hits the `mitrapclub` deployment's root URL.
- **End-to-end outcome:** branded board loads with the new theme; about page introduces the club; composer starts a new thread; existing threads/replies render normally; outbound links to YouTube/Instagram/Facebook are visible.
- **Needed recovery:** if `FORUM_SITE_ID` is unset or unknown, the app falls back cleanly to the `zenmemes` profile (existing `SiteProfileRegistry::active()` behavior) rather than erroring.
- **Release condition:** the new profile, theme, and content pass the same automated profile checks as `chouse`/`qdb` (`SiteProfileRegistry::validate()` plus `tests/Support/ProfileRegressionContract.php`).

## Risks

- **Theme doesn't land the intended cypher aesthetic** (subjective). Earliest validation: review the rendered board/about page against the `cypherposium.com` reference before Step 4 closes. Mitigation: ship a clean baseline theme first; treat further polish as iterative.
- **Generic about-page copy is a mismatch.** `ProfilePresentationContent::about()`'s fixed intro ("is a small forum for people who want a more durable local internet...") doesn't describe a rap-cypher club. Earliest validation: Step 3 decides whether to accept generic copy for v1 or add a per-profile introduction override. Mitigation: default to accepting it for v1 unless Step 3 scopes the override.
- **No real deployment target yet.** Unlike `chouse`, there's no hosting/DNS plan for `mitrapclub`. Earliest validation: Step 3 confirms whether this feature covers only the application-level profile/theme/content, or also stands up a real deployment. Mitigation: scope Step 3 to the application-level change; treat hosting as a follow-up feature.
- **Tagged/pinned threads may not read as "events" clearly enough** (accepted limitation from Step 1's Option C). Earliest validation: check during Step 3/4 whether the generic `thread_card` partial surfaces a pinned/tagged thread distinctly. Mitigation: accept as a known v1 limitation; revisit with a dedicated events experience only if felt in practice.

## Shared Component Inventory

- `src/ForumRewrite/SiteProfileRegistry.php` — add the `mitrapclub` entry; reuse the existing registry/validation, no change to its shape.
- `src/ForumRewrite/PresentationSlotRegistry.php` — reuse `navigation: forum`, `boardCard: thread`, `compose: thread`; add `mitrapclub` as a new choice for `editorial` and `brandedStylesheet` (and `about` only if the generic about copy is rejected in Step 3).
- `src/ForumRewrite/View/ThemeRegistry.php` — add one new theme entry (`mitrapclub`), following the existing `chouse`/`qdb` entries; no structural change.
- `src/ForumRewrite/ProfilePresentationContent.php` — add a `mitrapclub` key to the existing `EDITORIAL` map; reuse `about()`/`editorial()`/`busy()` unchanged.
- `public/assets/theme-chouse.css`, `public/assets/theme-qdb.css` — pattern to follow for a new `public/assets/theme-mitrapclub.css`; no shared stylesheet is forked, each theme already ships its own file.
- `templates/partials/nav.php`, `templates/partials/thread_card.php`, board/compose templates — reused unmodified via the existing `forum`/`thread` presentation slots; no new partials needed.

## Simple User Flow

1. Visitor opens the `mitrapclub` site.
2. Board loads with the new theme and club branding.
3. Visitor reads the about page: who the club is, and links to YouTube/Instagram/Facebook.
4. Visitor browses existing threads, including any pinned/tagged event announcements.
5. Visitor or member uses the composer to start a new thread.

## Success Criteria

- The `mitrapclub` site_id resolves via `FORUM_SITE_ID` and renders its own theme, about copy, and editorial content end-to-end.
- Unset/unknown `FORUM_SITE_ID` still falls back to `zenmemes` with no regression to existing profiles.
- `SiteProfileRegistry::validate()` and the existing profile-regression test contract pass for the new profile.
- The about page and board visibly link out to the club's YouTube, Instagram, and Facebook accounts.

Waiting for "Approved Step 2" before drafting Step 3.
