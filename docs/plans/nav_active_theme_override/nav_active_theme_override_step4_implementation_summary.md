> **Feature plan:** [Step 1](./nav_active_theme_override_step1_solution_assessment.md) · [Step 2](./nav_active_theme_override_step2_feature_description.md) · [Step 3](./nav_active_theme_override_step3_development_plan.md) · [Step 4](./nav_active_theme_override_step4_implementation_summary.md)

Debug mode: enabled

Branch: `fix/nav-active-theme-override` · Planning commit: `4e67e5f3`

## Stage 1 - Make the shared selected-nav rule a default
- Changes:
  - `public/assets/site.css`: the shared selected-nav selector is now `:where(:root:not([data-theme="chicago"])) .nav-link.is-active`, which lowers its specificity from (0,4,0) to (0,2,0). Any theme rule targeting `.nav-link` now wins regardless of load order. The Chicago exclusion and the declarations are unchanged.
  - New `tests/browser/nav_active_theme_styles.mjs`: for every theme sheet, loads a fixture nav in production order (theme before `site.css`) and theme-last order, and asserts that the selected link's computed color, background, border, box-shadow and weight are identical. It also asserts the Word 97 (`#dfdfdf`) and Midnight (`#3c3c3c`) sunken styles.
- Verification:
  - `node tests/browser/nav_active_theme_styles.mjs`: failed before the fix (Arena first); passed after it in 16 themes.
  - `node tests/browser/nav_unread_outline.mjs`: passed in 16 themes.
  - Live app (`./v3 start`, headless Chrome, all stylesheets loaded): on a fresh load and after a runtime theme switch, Board nav in Word 97 was `rgb(223,223,223)` and Midnight was `rgb(60,60,60)`, both sunken with no ring and with icons. On `/tools/feature-flags/` in both themes, the Tools nav and Feature Flags subnav were sunken gray (screenshot-checked). Chicago and Light computed styles are unchanged.
  - `./v3 test`: 6 failures (ApprovalSeedCommand, AgentIdentityService, LocalAppSmoke SQLite viewer, ReadModelBuilderTiming, 2× WriteApiSmoke). All 6 also fail with the original `site.css`, so they are pre-existing and unrelated (profile/content/API assertions).
- Notes:
  - Deviation from Step 3: the regression test is a browser computed-style test instead of a PHP specificity test. It checks the real cascade in both load orders across all themes, which is what the Step 3 risk asked for (not string matching). This is an in-scope refinement; the PHP test is not added.
  - First attempt `:root:where(:not(...))` (0,3,0) still tied with Arena's base `:root[data-theme="arena"] .nav-link` rule. Wrapping `:root` in `:where()` resolved that and any other theme's base nav-link rules.

## Closeout
- After reviewing Stage 1, the user asked to merge and close the worktree. Stages 2 and 3 were not started.
- Stage 2 (cross-theme before/after screenshots): not done. Partial coverage: the Stage 1 browser test shows that all 16 themes now render their selected nav as they did when the theme sheet loaded last (the pre-split cascade). It does not compare against today's `main` appearance. Arena, Console, Forge, QDB, Mitrapclub and Sticker will change visibly if their own rules had been overridden.
- Stage 3 (audit of other Word 97/Midnight nav and button collisions): not done; left as follow-up.
- Commit count: planning commit + 1 stage commit (below the planned 1 + 3 because the cycle ended early).
- Deployment/release: not applicable (static CSS; asset fingerprints update automatically).
- Baseline failures reproduced without this change: the 6 `./v3 test` failures listed in Stage 1.
