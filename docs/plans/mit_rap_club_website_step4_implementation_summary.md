> **Feature plan:** [Step 1](./mit_rap_club_website_step1_solution_assessment.md) · [Step 2](./mit_rap_club_website_step2_feature_description.md) · [Step 3](./mit_rap_club_website_step3_development_plan.md) · [Step 4](./mit_rap_club_website_step4_implementation_summary.md)

## Stage 1 - Theme registry entry and stylesheet
- Changes:
  - `src/ForumRewrite/View/ThemeRegistry.php`: added `{name: 'mitrapclub', label: 'MIT Rap Club', mode: 'dark'}` to `all()`.
  - New `public/assets/theme-mitrapclub.css`: dark chalkboard/cypher palette (warm amber-red accent, chalk-white ink, typewriter body font), following the `theme-chouse.css` variable + structural-selector pattern (not forked from it).
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
- Notes:
  - Visual review against `cypherposium.com`'s aesthetic is still open (Key Risks: High risk) — will revisit once the profile is wired up in Stage 4 and a real page can render with this theme.

## Stage 2 - Presentation slot choices
- Changes:
  - `src/ForumRewrite/PresentationSlotRegistry.php`: added `'mitrapclub'` to the `editorial` and `brandedStylesheet` slot `choices` lists.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
- Notes:
  - No behavior change for existing profiles; purely additive to the allowed-choices lists.

## Stage 3 - Editorial content
- Changes:
  - `src/ForumRewrite/ProfilePresentationContent.php`: added a `'mitrapclub'` key to `EDITORIAL` with club-specific `communityHeading`/`communityParagraphs` (cyphers, Code Cypher, CMS/W rap-theory context from Step 1 research); `docsIntro`/`architectureTitle`/`architectureDescription`/`busy*` follow the existing generic (non-club-specific) shape shared by `boston`/`qdb`.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed (key not yet wired to a profile, so no new behavior is exercised until Stage 4).
- Notes:
  - Per Step 2's accepted risk: the fixed `about()` intro sentence stays generic; the club-specific description lives in `communityParagraphs`, exercised once Stage 4 registers the profile.

## Stage 4 - Site profile registration
- Changes:
  - `src/ForumRewrite/SiteProfileRegistry.php`: added the `mitrapclub` profile — `defaultTheme: 'mitrapclub'`, `browserNamespace: 'mitrapclub'`, `editorialContentKey: 'mitrapclub'`, `enabledExperienceKeys: ['forum']`, `composerPrompt: 'Drop a verse, a cypher clip, or an announcement...'`, `presentationSlots: {navigation: 'forum', boardCard: 'thread', compose: 'thread', about: 'default', editorial: 'mitrapclub', brandedStylesheet: 'mitrapclub'}`.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Confirmed specifically: `SiteProfileRegistryTest` (all cases, including browser-namespace collision/uniqueness checks) and `ProfilePresentationContentTest` (including `testAboutAndPlatformDocumentPagesRenderTheActiveProfileContent`, which iterates every registered profile and now covers `mitrapclub`) all pass.
- Notes:
  - No browser-namespace or permitted-theme collisions, as anticipated in Step 3's Stage 4 risk.
  - Manually rendered `/about/` and the board for `mitrapclub`: `theme-mitrapclub.css` is correctly linked (fingerprinted path), site name and the custom composer prompt render.

## Stage 5 - Social links on the about page
- Changes:
  - `src/ForumRewrite/ProfilePresentationContent.php`: added a `socialLinks` field to every `EDITORIAL` entry (`[]` for `zenmemes`/`boston`/`qdb`; YouTube/Instagram/Facebook for `mitrapclub`) and threaded it through `about()`'s return value.
  - `templates/pages/about.php`: added an additive "Find us" section/list, rendered only when `socialLinks` is non-empty.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Manually rendered `/about/` for all four profiles: `zenmemes`/`chouse`/`qdb` show no "Find us" section (unchanged); `mitrapclub` shows all three links with the correct URLs.
- Notes:
  - The Facebook link uses the specific post URL given in Step 1's research links (`facebook.com/photo/?fbid=...`), not a confirmed page URL — the club's actual Facebook page slug wasn't verified during research. Worth swapping for the real page URL once known.
