> **Feature plan:** [Step 1](./bookmarklets_page_redesign_step1_solution_assessment.md) · [Step 2](./bookmarklets_page_redesign_step2_feature_description.md) · [Step 3](./bookmarklets_page_redesign_step3_development_plan.md) · [Step 4](./bookmarklets_page_redesign_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract
- Normal entry: visitor opens `/tools/bookmarklets/` from the tools nav in any theme.
- Outcome: redesigned hierarchy (title, lead, drag instruction, two labeled variant groups, bookmarklets as button+copy+description units); drag and copy work exactly as before.
- Recovery: copy failure feedback and disabled-until-ready behavior unchanged; revert is a template and CSS revert.
- Verification: page-scoped styles only; `/tools/` and other `.tool-launcher-*` pages unchanged; screenshots in all listed themes at desktop and 375 px; smoke tests pass.
- Release condition: all stages complete and checks below recorded in the Step 4 summary. No deployment or external verification beyond the normal asset fingerprint build.

## Key Risks
- **High risk:** Shared `.tool-launcher-*` rules regress `/tools/` and theme overrides. Validate by auditing selectors in Stage 1 and viewing `/tools/` after each CSS stage; mitigate with a page-scoped class.
- **High risk:** Drag or copy breaks if JS hooks move. Validate with the smoke test and manual drag/copy in Stage 2; mitigate by keeping all `data-bookmarklet*` attributes and the feedback element.
- Theme rendering varies (word97, chicago, qdb, vapor). Validate with screenshots in Stage 5; mitigate with tokens and existing nav-icon patterns.
- `.meta` contrast is weak in chicago and vapor. Validate in Stage 5; fix via tokens.

## Stage 1
- Goal: Audit before changing anything.
- Dependencies: Approved Step 2.
- Expected changes: None; record the list of `.tool-launcher-*` and `.bookmarklet-*` selectors, theme overrides, and pages using them in the Step 4 summary; capture baseline screenshots of `/tools/` and `/tools/bookmarklets/`.
- Verification approach: Selector list reviewed; baseline screenshots saved.
- Risks or open questions:
  - Impact: missed override causes a later regression.
  - Early warning / validation: grep results versus the theme CSS files.
  - Mitigation: scope all new rules to the page class.
- Canonical components/API contracts touched: none.

## Stage 2
- Goal: Restructure the template with hierarchy and grouping.
- Dependencies: Stage 1.
- Expected changes: Add a page class, lead sentence, and drag instruction; group each variant under heading, note, and list; keep each item as one unit (link, copy, feedback, description); keep all data attributes.
- Verification approach: Render the page; existing smoke assertions for `data-bookmarklet-kind` and nav pass; drag and copy work unstyled.
- Risks or open questions:
  - Impact: JS hooks lost.
  - Early warning / validation: copy button enables and feedback appears.
  - Mitigation: leave attributes and ids untouched.
- Canonical components/API contracts touched: `templates/pages/bookmarklets.php`; `tools_bookmarklets.js` (unchanged).

## Stage 3
- Goal: Page-scoped type scale, spacing, and chip styling.
- Dependencies: Stage 2.
- Expected changes: Scoped rules for heading/lead/note typography, section and item spacing, content-sized chip with drag glyph and grab cursor, matched-height copy button, focus rings; theme tokens only.
- Verification approach: Check in dark and light at desktop; `/tools/` unchanged against baseline.
- Risks or open questions:
  - Impact: leakage to other tool pages.
  - Early warning / validation: compare `/tools/` screenshots.
  - Mitigation: every selector begins with the page class.
- Canonical components/API contracts touched: `public/assets/tools.css`.

## Stage 4
- Goal: Responsive layout.
- Dependencies: Stage 3.
- Expected changes: Row layout (button and copy left, description right) at 640 px and up; stacked below; feedback bubble stays visible.
- Verification approach: View at 375 px, 640 px, and desktop; no horizontal scroll.
- Risks or open questions:
  - Impact: feedback bubble clipped on narrow screens.
  - Early warning / validation: trigger copy at 375 px.
  - Mitigation: adjust bubble placement within the scoped rules.
- Canonical components/API contracts touched: `public/assets/tools.css`.

## Stage 5
- Goal: Theme fidelity and contrast.
- Dependencies: Stage 4.
- Expected changes: Page-scoped overrides only where a theme breaks (word97, chicago, qdb, vapor, console, sticker, lcd, forge, arena, thermal, whitehot); reuse existing nav-icon glyph patterns; fix `.meta` contrast with tokens.
- Verification approach: Screenshots in all themes at desktop and 375 px; contrast checked in chicago and vapor.
- Risks or open questions:
  - Impact: glyph or button treatment off-theme.
  - Early warning / validation: side-by-side screenshots.
  - Mitigation: fall back to the plain chip for that theme.
- Canonical components/API contracts touched: `theme-*.css`, `theme-nav-icons.css`.

## Stage 6
- Goal: Regression pass and release readiness.
- Dependencies: Stage 5.
- Expected changes: Update smoke assertions for new page markup if needed; rebuild fingerprinted assets.
- Verification approach: Run the smoke suite; compare `/tools/` and other `.tool-launcher-*` pages with baseline; manual drag and copy on desktop and mobile width; record results in the Step 4 summary.
- Risks or open questions:
  - Impact: stale asset fingerprint.
  - Early warning / validation: asset assertion in smoke test.
  - Mitigation: run the project's asset build step.
- Canonical components/API contracts touched: `tests/LocalAppSmokeTest.php`, built assets.
