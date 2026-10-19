> **Feature plan:** [Step 1](./rename_windark_to_midnight_step1_solution_assessment.md) · [Step 2](./rename_windark_to_midnight_step2_feature_description.md) · [Step 3](./rename_windark_to_midnight_step3_development_plan.md) · [Step 4](./rename_windark_to_midnight_step4_implementation_summary.md)

Debug mode: enabled

# Rename the windark theme to midnight: Step 3 development plan

## Completion Contract
- Entry: a member with a saved `windark` loads any page and gets Midnight; a new member picks Midnight in the theme menu.
- Outcome: `midnight` is the only theme name in code, files, selectors, tests and `AGENTS.md`, except the alias and historical plans.
- Recovery: unknown stored values still fall back to the site default; the alias rewrites the stored value.
- Verification: renamed tests pass; browser checks of old-value and new-pick flows and of nav icons in Midnight, Word97 and Chicago pass.
- Release: no deployment or schema change; the alias-removal todo is recorded.

## Key Risks
- **High risk:** saved `windark` ignored in either the browser scripts or the server cookie hint, so Midnight users lose their theme. Validate in Stages 2 and 3 with a preloaded value; do not close Stage 4 without it.
- **Missed references:** leftover `windark` breaks the stylesheet link or selectors. Mitigate with a grep after Stage 1 and again at Stage 4.
- **Icon regression:** about 90 nav-icon rules change selectors. Verify icons visually in Stage 4, not by text checks only.

## Stage 1
- Goal: mechanical rename of the theme.
- Dependencies: none.
- Expected changes: `ThemeRegistry` name, `SiteProfileRegistry` permitted lists, `git mv` `theme-windark.css` to `theme-midnight.css`, all `data-theme="windark"` and `theme-option` selectors in the theme and nav-icon CSS, and test expectations in `LocalAppSmokeTest` and `InvitationIssuanceTest`. The change exceeds 50 lines because it is mechanical.
- Verification approach: `php tests/run.php LocalAppSmokeTest InvitationIssuanceTest`; `git grep -i windark` shows only `AGENTS.md`, `todo.txt`, and historical plans.
- Risks or open questions:
  - Impact: a stylesheet or selector missed.
  - Early warning / validation: grep and rendered theme menu contains `data-theme-option="midnight"`.
  - Mitigation: rename with one reviewed substitution, not by hand.
- Canonical components/API contracts touched: `ThemeRegistry`, `SiteProfileRegistry`, theme CSS, `theme-nav-icons.css`.

## Stage 2
- Goal: browser alias from `windark` to `midnight`.
- Dependencies: Stage 1.
- Expected changes: `layout.php` inline script and `theme_toggle.js` map a stored `windark` to `midnight` and rewrite it, following the existing zenmemes migration there.
- Verification approach: browser test preloads `windark` in localStorage and asserts `data-theme="midnight"` and stored `midnight`.
- Risks or open questions:
  - Impact: flash of the default theme before the alias applies.
  - Early warning / validation: check the first-paint attribute in the test.
  - Mitigation: apply the alias in the early inline script.
- Canonical components/API contracts touched: `layout.php`, `theme_toggle.js`.

## Stage 3
- Goal: server alias for the `theme-hint` cookie.
- Dependencies: Stage 1.
- Expected changes: `TemplateRenderer::themeHint` maps `windark` to `midnight`; the cookie is rewritten by the client alias.
- Verification approach: PHP test renders a page with `theme-hint=windark` and expects the Midnight hint.
- Risks or open questions:
  - Impact: server renders the default theme for old cookies.
  - Early warning / validation: the PHP test.
  - Mitigation: keep the map in `ThemeRegistry` as one legacy-name list.
- Canonical components/API contracts touched: `ThemeRegistry`, `TemplateRenderer`.

## Stage 4
- Goal: browser verification, docs and todo.
- Dependencies: Stages 1-3.
- Expected changes: `AGENTS.md` and `todo.txt` wording updated to Midnight; append a removal todo for the alias; final grep.
- Verification approach: browser pass in Midnight, Word97 and Chicago covering Tools launcher and subnavigation icons (active and inactive) plus theme menu pick; full `LocalAppSmokeTest`.
- Risks or open questions:
  - Impact: an icon rule renamed incorrectly.
  - Early warning / validation: compare screenshots with `main`.
  - Mitigation: fix and re-run before the commit.
- Canonical components/API contracts touched: `AGENTS.md`, `todo.txt`, `theme-nav-icons.css` (read only unless a fix is needed).
