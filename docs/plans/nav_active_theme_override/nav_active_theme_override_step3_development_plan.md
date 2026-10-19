> **Feature plan:** [Step 1](./nav_active_theme_override_step1_solution_assessment.md) · [Step 2](./nav_active_theme_override_step2_feature_description.md) · [Step 3](./nav_active_theme_override_step3_development_plan.md) · [Step 4](./nav_active_theme_override_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Entry: fresh load of any page with the main nav or Tools subnav, in Word 97 or Midnight.
- Outcome: the selected button is sunken gray (Word 97 `#dfdfdf`, Midnight `#3c3c3c`), and its nav icon still renders.
- Recovery: none beyond reload; a runtime theme switch gives the same result.
- Deployment/external: none (static CSS; asset fingerprints update automatically).
- Release: PHP tests pass; browser computed-style and screenshot checks pass for Word 97, Midnight, Chicago, a shared-default theme (Light), and the six other themes with their own selected-nav rules.

## Key Risks

- Six themes change appearance (Arena, Console, Forge, QDB, Mitrapclub, Sticker). Impact: visible change for those users. Early validation: before/after screenshots in Stage 2. Mitigation: record each change; pause for a user decision on any that look unintended.
- Async `site.css` hides the bug. Impact: false pass. Early validation: Stage 1 browser check waits for `site.css` to finish loading on a fresh page, not just after a theme switch. Mitigation: assert computed styles after all stylesheets load.
- Audit finds broad collisions. Impact: scope growth. Early validation: Stage 3 inventory. Mitigation: fix only Word 97 and Midnight nav/button collisions; record the rest as follow-up (Step 1 Option C).

## Stage 1 — Make the shared selected-nav rule a default
- Goal: Word 97 and Midnight selected nav renders correctly on fresh load.
- Dependencies: none.
- Expected changes:
  - Lower the specificity of the shared `.nav-link.is-active` rule in `site.css` (Option B), keeping the Chicago exclusion.
  - Add a PHP test asserting the shared rule is lower-specificity than every theme's own `.nav-link.is-active` rule. It must fail before the fix.
- Verification approach: run the new and existing CSS tests. In headless Chromium (playwright-core), fresh-load a page in Word 97 and Midnight, wait for all stylesheets, and assert the active link's computed background and border colors. Repeat after a runtime theme switch. Screenshot both themes to confirm the nav icons.
- Risks or open questions:
  - Impact: the test could check the source text instead of the actual priority. Early warning: confirm the test fails on the current CSS. Mitigation: compute selector specificity in the test rather than matching strings.
- Canonical components/API contracts touched: `.nav-link.is-active` in `public/assets/site.css`; `tests/LocalAppSmokeTest.php` (or a sibling CSS test).

## Stage 2 — Cross-theme verification
- Goal: confirm the effect on every other theme and record it.
- Dependencies: Stage 1.
- Expected changes: no code. Record a before/after table in the Step 4 summary for Chicago, Light, Arena, Console, Forge, QDB, Mitrapclub and Sticker.
- Verification approach: fresh-load screenshots of the selected nav before (main) and after (branch) for each theme. Chicago and Light must match exactly. The other six should match their theme rule.
- Risks or open questions:
  - Impact: a theme's long-dormant rule may look wrong now. Early warning: screenshot review. Mitigation: stop and ask the user before adjusting any theme.
- Canonical components/API contracts touched: none (verification only).

## Stage 3 — Audit other Word 97/Midnight nav and button collisions
- Goal: catch other equal-specificity shared rules that override Word 97 or Midnight nav or button styles.
- Dependencies: Stage 1.
- Expected changes:
  - Compare `site.css` rules after the critical-CSS marker against same-specificity Word 97 and Midnight nav/button rules.
  - Fix confirmed collisions in that scope with the Stage 1 approach, extending the test.
  - Record any out-of-scope collisions as follow-ups in the Step 4 summary.
- Verification approach: computed-style check for each fixed rule on a fresh load in both themes; rerun the tests.
- Risks or open questions:
  - Impact: the audit may find no collisions, or many. Early warning: inventory count before editing. Mitigation: if more than a few need fixing, stop and propose a separate cycle.
- Canonical components/API contracts touched: shared nav and button rules in `public/assets/site.css`; `theme-word97.css` and `theme-midnight.css` only if a fix needs them.
