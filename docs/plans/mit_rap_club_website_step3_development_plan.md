> **Feature plan:** [Step 1](./mit_rap_club_website_step1_solution_assessment.md) · [Step 2](./mit_rap_club_website_step2_feature_description.md) · [Step 3](./mit_rap_club_website_step3_development_plan.md) · [Step 4](./mit_rap_club_website_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** `FORUM_SITE_ID=mitrapclub` is set and the app root is loaded.
- **End-to-end outcome:** branded board (new theme) loads; about page tells the club's story and links to YouTube/Instagram/Facebook; composer starts a thread; existing thread/reply behavior is unaffected.
- **Required recovery:** unset or unknown `FORUM_SITE_ID` still falls back to the `zenmemes` profile — no regression to existing profiles.
- **Deployment/external verification:** out of scope for this plan (carried from Step 2's risk decision). This covers only the application-level profile/theme/content and its test coverage; standing up a real hosted `mitrapclub` instance (vhost, DNS, separate repo/DB roots) is a follow-up feature.
- **Release condition:** `./v3 test` passes in full, including the profile-iteration tests (`SiteProfileRegistryTest`, `LocalAppSmokeTest`'s per-profile loop) now covering `mitrapclub`.

## Key Risks

- **High risk:** Theme/branding may not land the intended cypher aesthetic (usability). Early validation: visual review against `cypherposium.com` after Stage 1. Mitigation: ship a clean baseline theme; treat polish as iterative.
- Browser-namespace or permitted-theme collision when registering the new profile. Early validation: `./v3 test` after Stage 4 (existing validation tests fail loudly on collisions). Mitigation: confirm `mitrapclub` is unique against `zenmemes`/`chouse`/`qdb` before writing the entry.
- Touching `templates/pages/about.php` for social links could regress chouse's `showHackableSection` branch. Early validation: full smoke-test profile loop re-run after Stage 5. Mitigation: keep the new block strictly additive and conditional on data presence.
- Deployment/hosting is explicitly out of scope here (see Completion Contract) — called out so it isn't mistaken for unfinished work.

## Stage 1
- Goal: introduce a new `mitrapclub` theme.
- Dependencies: none.
- Expected changes:
  - `ThemeRegistry::all()` gains one entry: `{name: 'mitrapclub', label, mode}`.
  - New stylesheet `public/assets/theme-mitrapclub.css`, authored following the existing `theme-chouse.css`/`theme-qdb.css` variable pattern (not forked from either).
- Verification approach: existing theme tests continue passing; visual check of the new stylesheet in a browser.
- Risks or open questions:
  - Impact: aesthetic may miss the cypher/grassroots tone from Step 1's inspiration.
  - Early warning / validation: compare rendered page against `cypherposium.com` before Step 4 closes.
  - Mitigation: ship a clean baseline now; iterate visually afterward.
- Canonical components/API contracts touched: `ThemeRegistry::all()`.

## Stage 2
- Goal: register `mitrapclub` as a valid choice for the `editorial` and `brandedStylesheet` presentation slots.
- Dependencies: Stage 1 (the theme name referenced by `brandedStylesheet` must exist).
- Expected changes: `PresentationSlotRegistry::SLOTS['editorial']['choices']` and `SLOTS['brandedStylesheet']['choices']` each gain `'mitrapclub'`.
- Verification approach: existing slot-resolution/validation tests still pass for `zenmemes`/`chouse`/`qdb` (no behavior change for them).
- Risks or open questions: none material.
- Canonical components/API contracts touched: `PresentationSlotRegistry::SLOTS`.

## Stage 3
- Goal: add club-specific editorial copy.
- Dependencies: Stage 2 (slot choice must exist for `editorial()` resolution).
- Expected changes: `ProfilePresentationContent::EDITORIAL` gains a `'mitrapclub'` key with `communityHeading`/`communityParagraphs` describing the club (cyphers, the CMS/W rap-theory programming context from Step 1 research) plus `docsIntro`/`architectureTitle`/`architectureDescription`/`busy*` copy matching the existing shape.
- Verification approach: render `about()`/`platformDocs()`/`busy()` for the new key and confirm no errors; existing presentation-content tests unaffected.
- Risks or open questions:
  - Impact: the fixed, generic intro sentence in `about()` ("is a small forum for people who want...") still won't read as club-specific.
  - Early warning / validation: visible on first render of the about page.
  - Mitigation: accepted for v1 per Step 2's resolution — the real description lives in the new `communityParagraphs`, not the fixed intro.
- Canonical components/API contracts touched: `ProfilePresentationContent::EDITORIAL`, `about()`, `platformDocs()`, `busy()`.

## Stage 4
- Goal: register the `mitrapclub` site profile.
- Dependencies: Stages 1–3 (theme, slot choices, editorial key must already exist for validation to pass).
- Expected changes: `SiteProfileRegistry::all()` gains `'mitrapclub' => {name, displayName, defaultTheme: 'mitrapclub', permittedThemes: existing list + 'mitrapclub', browserNamespace: 'mitrapclub', editorialContentKey: 'mitrapclub', enabledExperienceKeys: ['forum'], composerPrompt: <club-specific prompt>, presentationSlots: {navigation: 'forum', boardCard: 'thread', compose: 'thread', about: 'default', editorial: 'mitrapclub', brandedStylesheet: 'mitrapclub'}}`.
- Verification approach: `./v3 test`, specifically `SiteProfileRegistryTest` (`testAllProfilesHaveRequiredFields`, `testActiveHonorsRegisteredOverrides`, duplicate/unsafe-identifier checks).
- Risks or open questions:
  - Impact: browser-namespace collision with an existing profile.
  - Early warning / validation: the existing duplicate-namespace test fails immediately on the next test run.
  - Mitigation: `mitrapclub` is already distinct from `zenmemes`/`chouse`/`qdb`; confirm again before committing.
- Canonical components/API contracts touched: `SiteProfileRegistry::all()`.

## Stage 5
- Goal: surface standing links to the club's YouTube, Instagram, and Facebook.
- Dependencies: Stage 3 (link data lives alongside the editorial content, not hardcoded in a template).
- Expected changes:
  - Extend the `mitrapclub` about-page content (from Stage 3's data) with the three social URLs.
  - `templates/pages/about.php` renders an optional "find us" list only when that data is present — additive, so other profiles render unchanged.
- Verification approach: render `mitrapclub`'s about page and confirm the three links appear; re-render `zenmemes`/`chouse`/`qdb` about pages and confirm no markup change.
- Risks or open questions:
  - Impact: a change to `about.php` could regress chouse's `showHackableSection` branch.
  - Early warning / validation: re-run the full profile-loop smoke test after this stage.
  - Mitigation: keep the new block strictly additive and gated on data presence.
- Canonical components/API contracts touched: `ProfilePresentationContent::about()`, `templates/pages/about.php`.

## Stage 6
- Goal: confirm no regression and full profile-regression coverage for the new profile.
- Dependencies: Stages 1–5.
- Expected changes: none — verification-only. `ProfileRegressionContract::all()` and `LocalAppSmokeTest`'s profile-switch/profile loop pick up `mitrapclub` automatically since both iterate `SiteProfileRegistry::all()`.
- Verification approach: run `./v3 test` (full suite) and confirm `SiteProfileRegistryTest`, the `LocalAppSmokeTest` profile-loop assertions, and presentation-content tests all pass with `mitrapclub` included.
- Risks or open questions:
  - Impact: profile iteration could surface an unanticipated interaction (e.g., an offline-cache key collision).
  - Early warning / validation: this stage's test run.
  - Mitigation: fix forward within this stage before Step 4 is considered done.
- Canonical components/API contracts touched: `tests/Support/ProfileRegressionContract.php`, `tests/LocalAppSmokeTest.php`, `tests/SiteProfileRegistryTest.php` (all read-only / exercised, not modified).

Waiting for "Approved Step 3" before branching and starting Step 4.
