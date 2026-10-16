> **Feature plan:** [Step 1](./word97_chicago_disabled_and_dark_step1_solution_assessment.md) · [Step 2](./word97_chicago_disabled_and_dark_step2_feature_description.md) · [Step 3](./word97_chicago_disabled_and_dark_step3_development_plan.md) · [Step 4](./word97_chicago_disabled_and_dark_step4_implementation_summary.md)

Debug mode: enabled

# Step 3: Development Plan — Word 97 disabled buttons and dark Win9x theme

Working name for the new theme: **Win Dark** (`windark`, mode `dark`); rename at review if preferred.

## Completion Contract
- Entry: theme menu (and cycle button) lists Win Dark; Word 97 is selected for the fix.
- Outcome: Word 97 disabled buttons look grayed; Win Dark renders all checked pages themed, persists on reload, no light flash.
- Recovery: switching themes restores prior looks; reverting the feature branch removes the theme. Chicago and other themes unchanged.
- Verification: `LocalAppSmokeTest` passes; manual browser check of board, thread, account, tools in Word 97 and Win Dark. No deployment or data steps.
- Release: merge to `main` only when explicitly asked.

## Key Risks
- **Diagnosis:** existing `button[disabled]` rule may be overridden or the affected controls may be non-`button`. Validation: inspect computed styles in Stage 1. Mitigation: fix the actual cause only.
- **Blend subjectivity:** Stage 2 fixes the borrowed-element table; review it before Stage 3 styling.
- **Coverage:** missed light surfaces. Mitigation: Stage 5 page checklist.
- **Shared selectors:** extending `:is(...)` lists must not alter existing themes. Mitigation: smoke tests, diff review.

## Stage 1
- Goal: Word 97 disabled buttons visibly grayed.
- Dependencies: none.
- Expected changes: inspect disabled controls in the browser; adjust the Word 97 disabled rule(s) (color, specificity, emboss, any overriding opacity) in `theme-word97.css`.
- Verification approach: browser computed-style check on a real disabled button and disabled nav link; Chicago visually unchanged.
- Risks or open questions:
  - Impact: wrong cause fixed.
  - Early warning / validation: computed color on the disabled element.
  - Mitigation: change only what inspection shows.
- Canonical components/API contracts touched: `theme-word97.css`.

## Stage 2
- Goal: Win Dark selectable with palette, swatch and menu row, plus a recorded blend table.
- Dependencies: Stage 1.
- Expected changes: add registry entry; add to `SiteProfileRegistry` permitted lists; new `theme-windark.css` with variable block, swatch, menu-row; update smoke-test allow-list and option assertions; blend table in the Step 4 summary.
- Verification approach: smoke tests; theme appears in menu and persists.
- Risks or open questions:
  - Impact: anti-FOUC allow-list mismatch.
  - Early warning / validation: smoke-test allow-list assertion.
  - Mitigation: follow `theme_development_guide.md` checklist.
- Canonical components/API contracts touched: `ThemeRegistry.php`, `SiteProfileRegistry.php`, `tests/LocalAppSmokeTest.php`.

## Stage 3
- Goal: core dark chrome.
- Dependencies: Stage 2.
- Expected changes: header, shell, cards, feedback, forms, buttons (incl. disabled and focus), nav links in `theme-windark.css`, modeled on the Word 97/Chicago rules with dark values.
- Verification approach: browser check of board and account pages.
- Risks or open questions:
  - Impact: contrast failures.
  - Early warning / validation: visual check of text on panels.
  - Mitigation: adjust `--ink`/`--panel` values.
- Canonical components/API contracts touched: `theme-windark.css`, `site.css` base rules (read-only).

## Stage 4
- Goal: remaining Word 97 specifics in dark.
- Dependencies: Stage 3.
- Expected changes: title-bar headings, composer bar, theme popover, scrollbars (`html[data-theme]` form), compact thread density rules.
- Verification approach: browser check of thread page, compact mode, popover, scrollbars.
- Risks or open questions:
  - Impact: scrollbar or popover rules leaking onto menu rows.
  - Early warning / validation: popover rows still show own themes.
  - Mitigation: keep `:not(.theme-menu__option)` guards.
- Canonical components/API contracts touched: `theme-windark.css`.

## Stage 5
- Goal: icons, coverage sweep, and wrap-up.
- Dependencies: Stage 4.
- Expected changes: add `windark` to the `word97`/`chicago` selectors in `theme-nav-icons.css` and matching smoke assertions; fix gaps found in a page-by-page sweep; remove the two items from `todo.txt`.
- Verification approach: full smoke test; sweep of board, thread, profile, tags, account, tools, about in Win Dark and Word 97.
- Risks or open questions:
  - Impact: existing theme icons altered.
  - Early warning / validation: smoke assertions on existing selectors.
  - Mitigation: extend lists only.
- Canonical components/API contracts touched: `theme-nav-icons.css`, `tests/LocalAppSmokeTest.php`, `todo.txt`.
