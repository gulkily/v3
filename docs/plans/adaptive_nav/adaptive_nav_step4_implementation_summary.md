> **Feature plan:** [Step 1](./adaptive_nav_step1_solution_assessment.md) · [Step 2](./adaptive_nav_step2_feature_description.md) · [Step 3](./adaptive_nav_step3_development_plan.md) · [Step 4](./adaptive_nav_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/adaptive_nav`; [host ledger](../effort_estimation_trial.md#cycle-v3adaptive_nav). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.85

Branch: `feat/adaptive-nav` from `main` `a24e16df`. Planning commit `8fe60a2a`.

## Step 4 entry

2026-11-05T05:20:00Z. Step 3 approved ("Approved step 3"). Whole-cycle projection **0.85 reaffirmed** from Step 3, unchanged scope v2. Stage-work originals copied from the approved plan: S1 0.08, S2 0.12, S3 0.08, S4 0.14, S5 0.14, S6 0.08, S7 0.08, S8 0.13. Single exposed Claude rater; copying is not a new rating. Full suite not run in this cycle; the maintainer runs it periodically.

## Enhancement Requests

- Carried from Step 3: JSON slot storage (adopted, Stage 4); local-storage editor PF-20261104-F18 (separate work).

## Stage 1 - Desktop bar and menu split

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes:
  - `TemplateRenderer::navigationItems()`: in the app slot, every destination except Account, Invite and the personal Profile link is `main`; items are ordered Board, Lobby, Recent, Calendar, Users, Messages, Search, About, Tools, then menu items.
  - `site.css`: from 640px the app header spans up to 1100px centred on the 760px column, links sit tighter, and New post and You stay on the first row when the nav wraps.
  - `AppNavigationPlacementTest`: Search, About and Tools are `main`; the bar order is asserted.
- Verification:
  - Passed: `./v3 test --smoke AppNavigationPlacementTest CalendarPageTest` (430 run, 430 passed).
  - Passed: `tests/browser/nav_active_theme_styles.mjs` (16 themes).
  - Passed: `git diff --check`.
  - Passed: rendered-layout screenshots at 1280px (one row), 1024px (one row) and 800px (nav wraps to two rows; buttons on the first row).
  - Inapplicable: phone layout (Stage 2); icon themes (Stage 7).
  - Not run: full suite (maintainer runs it periodically).
- Notes:
  - Risk "the desktop bar wraps on narrow laptops" validated: it wraps below about 950px and stays tidy. Mitigation applied as planned.
  - Playwright for the browser checks is linked from the workspace's preinstalled copy into the ignored `node_modules/`; nothing was added to the repository.
- Estimate details: original scope unchanged; uncertainty low; no revisions. Retrospective matches the original.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*
