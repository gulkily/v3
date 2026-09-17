# Compact Mode Cleanup Step 3 Development Plan

## Stage 1
- Goal: Establish the current compact-mode layout and focused regression contract before changing styles.
- Dependencies: Approved Step 2; clean target files; existing compact-mode implementation remains the baseline.
- Expected changes: Inspect the board controls, compact composer, thread-list structure, and current stylesheet cascade; extend focused assertions only where the intended scoped selectors need protection.
- Verification approach: Run the focused smoke test and capture a baseline screenshot of compact board mode.
- Risks or open questions: Existing theme-specific card rules may affect selector ordering or require a narrowly scoped exception.
- Canonical components/API contracts touched: `public/assets/site.css`, `tests/LocalAppSmokeTest.php`, existing board/thread-list templates.

## Stage 2
- Goal: Remove unnecessary outer chrome and spacing around compact controls and the compact composer while preserving their internal appearance.
- Dependencies: Stage 1 baseline.
- Expected changes: Add or adjust compact-only stylesheet rules for the board controls container and the compact thread composer so they align with the surrounding list surface without changing button, field, or action styling.
- Verification approach: Run the focused smoke test, check stylesheet formatting, and manually compare the controls/composer against the baseline screenshot.
- Risks or open questions: Responsive card padding rules may override earlier compact declarations; final selectors must be scoped and ordered to win only in compact mode.
- Canonical components/API contracts touched: `public/assets/site.css`, existing board controls and compact composer markup.

## Stage 3
- Goal: Remove compact thread-list side rails while retaining intended row separators and finish regression verification.
- Dependencies: Stage 2 committed and visually verified.
- Expected changes: Remove only the left/right borders from direct compact thread-list cards; preserve top/bottom separators, comfortable mode, and non-board pages. Update focused CSS assertions and the Step 4 implementation summary.
- Verification approach: Run the focused smoke test, `git diff --check`, the full test suite, and a manual screenshot review confirming no vertical rails and no unintended layout changes.
- Risks or open questions: Theme-specific borders may differ; if so, keep any exception limited to the compact thread-list selector rather than changing shared card styling.
- Canonical components/API contracts touched: `public/assets/site.css`, `tests/LocalAppSmokeTest.php`, `docs/plans/compact_mode_menu_buttons_step4_implementation_summary.md`.

## Approval Gate

- Create the Step 4 feature branch only after explicit `Approved Step 3`.
- Commit the approved Step 1–3 planning documents as the first Step 4 commit, then implement one stage at a time with a summary update and stage-scoped commit.
