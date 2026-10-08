# Multi-Site Refactor P2 Presentation — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./multi_site_refactor_p2_presentation_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_presentation_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_presentation_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_presentation_step4_implementation_summary.md)

## Stage 1 - Registered presentation slots

- Changes:
  - Added a closed presentation-slot catalog with named choices and shared fallbacks.
  - Added validated profile selections for navigation, board card, compose, about, editorial, and branded stylesheet.
- Verification:
  - `php -l` passed for the slot registry, profile registry, and slot test.
  - `php tests/run.php PresentationSlotRegistryTest SiteProfileRegistryTest` — 8 passed.
  - `git diff --check` passed.
- Notes:
  - Selections are semantic keys only; no slot accepts a template path or stylesheet stack.

## Stage 2 - Shared chrome slots

- Changes:
  - Resolved navigation and the default branded stylesheet from registered profile slots.
  - Replaced the last layout navigation experience check with the navigation slot selection.
  - Corrected a P1 board-render warning by deriving its reaction-script flag from the explicit QDB policy.
- Verification:
  - `php -l` passed for the renderer and board controller.
  - `php tests/run.php QdbExperienceRoutingTest SiteProfileRegistryTest` — 8 passed.
  - `git diff --check` passed.
- Notes:
  - Theme-menu filtering and unavailable-preference recovery remain Stage 5.

## Stage 3 - Board-card presentation slot

- Changes:
  - Resolved the board-card partial and its data contract from the registered `boardCard` profile slot.
  - Resolved the QDB add surface from the registered `compose` slot, with the shared thread compose surface as its bounded fallback.
  - Preserved the QDB quote-card interface and generic thread-card fallback without introducing profile-specific template paths.
  - Restored the QDB vote-handler script on random and search quote listings after their route extraction.
- Verification:
  - `php -l` passed for the board controller and QDB experience.
  - `php tests/run.php QdbExperienceRoutingTest QuoteCardDisplayNumberTest PresentationSlotRegistryTest` — 18 passed.
  - `git diff --check` passed.

## Stage 4 - Profile-selected editorial content

- Changes:
  - Added a closed profile-presentation content catalog selected through the validated about and editorial slots.
  - Moved the about/editorial copy, Chouse-only about section, platform-document branding, and lock-contention busy message out of direct profile-name conditionals and templates.
  - Preserved the Zenmemes busy message and supplied neutral busy copy for the Boston and QDB editorial selections.
- Verification:
  - `php -l` passed for the content catalog and affected controllers.
  - `php tests/run.php ProfilePresentationContentTest PresentationSlotRegistryTest SiteProfileRegistryTest` — 11 passed, including the three-profile about/docs render matrix and invalid-slot fallback.
  - `git diff --check` passed.

## Stage 5 - Profile-owned theme availability

- Changes:
  - Restricted each profile's theme menu, early stylesheet map, and accepted theme hint to its validated permitted themes.
  - Made `chouse` available only to Chouse and `qdb` only to QDB; shared themes remain available to all three profiles.
  - Recovered unavailable stored/hinted themes to the active profile default without changing the existing browser storage-key contract, which remains the separate browser/offline P2 slice.
  - Validated that profile descriptors cannot name unknown or duplicate theme choices.
- Verification:
  - `php tests/run.php ProfileThemePresentationTest ThemeRegistryTest SiteProfileRegistryTest PresentationSlotRegistryTest` — 17 passed.
  - `php tests/run.php LocalAppSmokeTest` — 115 passed; four unrelated long-standing failures remain (anonymous public session, adjacent signature links, API/RSS schema version, and SQLite-viewer route source).
  - `git diff --check` passed.

## Stage 6 - Presentation regression contract and handoff

- Changes:
  - Added a three-profile rendering matrix for navigation, board cards, compose surfaces, about content, and branded theme availability.
  - Extended slot coverage so every missing or invalid slot selection resolves to its registered fallback.
  - Updated the P2 bounded-presentation checklist as complete.
- Verification:
  - `php tests/run.php PresentationProfileMatrixTest PresentationSlotRegistryTest ProfilePresentationContentTest ProfileThemePresentationTest QdbExperienceRoutingTest QuoteCardDisplayNumberTest` — 25 passed.
  - `php tests/run.php` completed with P2 coverage passing. Seven unrelated long-standing failures remain: two browser-signing Node-harness helpers, four LocalAppSmoke checks, and `PlatformDocsPageTest::testCataloguedAndUncataloguedDocumentsRenderWithSourcePaths`.
  - `git diff --check` passed.
- Deployment note:
  - No deployed entry point was supplied in this workspace, so the planned operator render check remains an external release task; local profile render coverage is complete.
