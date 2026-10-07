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
