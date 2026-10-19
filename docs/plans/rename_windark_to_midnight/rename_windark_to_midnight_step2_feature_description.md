> **Feature plan:** [Step 1](./rename_windark_to_midnight_step1_solution_assessment.md) · [Step 2](./rename_windark_to_midnight_step2_feature_description.md) · [Step 3](./rename_windark_to_midnight_step3_development_plan.md) · [Step 4](./rename_windark_to_midnight_step4_implementation_summary.md)

Debug mode: enabled

# Rename the windark theme to midnight: Step 2 feature description

## Problem
The theme labelled "Midnight" is still `windark` in code, files, selectors, tests and `AGENTS.md`. Rename it to `midnight` everywhere without making existing Midnight users lose their saved theme.

## User stories
- As a member who picked Midnight, I want my theme preserved after the rename so that the site looks the same on my next visit.
- As a developer, I want code and label to agree so that searches, selectors and docs use one name.

## Core requirements
- The internal name is `midnight` in `ThemeRegistry`, `SiteProfileRegistry`, `theme-midnight.css` (renamed; rename confirmed acceptable), all `data-theme` selectors, and the nav-icon mappings; `AGENTS.md` names Midnight.
- A saved `windark` (localStorage theme value and `theme-hint` cookie) is treated as `midnight` and rewritten to `midnight` on read. The alias stays until a later cleanup, tracked by a new `todo.txt` entry (confirmed).
- Midnight looks identical to the current theme, including nav icons for Tools launcher and subnavigation (per `AGENTS.md`), checked together with Word97 and Chicago.
- Historical plan docs that mention windark are left unchanged.

## Delivery scope and completion boundary
**Work type:** application change. Normal entry: a member with a saved `windark` loads any page and sees Midnight, and a new member picks Midnight in the theme menu. Recovery: an unknown stored value still falls back to the site default. Release when the old-value, new-pick, and nav-icon checks pass. Out of scope: renaming other themes and restyling Midnight.

## Risks
- **Lost themes:** a stored `windark` not recognised falls back to default silently. Validate early with a browser test that preloads `windark` in localStorage and the cookie.
- **Missed references:** selectors or the CSS filename left behind break styling or the stylesheet link. Mitigate with a repository grep for `windark` that must leave only the alias and historical docs.
- **Icon regression:** about 90 nav-icon rules change selectors. Verify icons in Midnight, Word97 and Chicago with a browser pass, not text checks.

## Shared component inventory
- `ThemeRegistry` (name, label, stylesheet path from name) and `SiteProfileRegistry` permitted-theme lists.
- `theme_toggle.js` and the inline script in `layout.php` (stored-value reads; the existing zenmemes migration there is the precedent), plus the server cookie hint in `TemplateRenderer`.
- `theme-windark.css` becomes `theme-midnight.css`; `theme-nav-icons.css`; tests in `LocalAppSmokeTest` and `InvitationIssuanceTest`; `AGENTS.md`.

## Simple user flow
1. A member with saved `windark` opens any page.
2. The scripts and server hint resolve it to `midnight`, and the stored value is rewritten.
3. Midnight renders, nav icons included; the theme menu shows Midnight selected.

## Success criteria
- No `windark` remains except the alias code, its test, and historical plans.
- A preloaded `windark` value yields Midnight with `midnight` stored afterward; a fresh pick persists `midnight`.
- Existing theme tests pass, updated for the new name.

## Enhancement Requests
- Request: rename windark to midnight everywhere / Disposition: incorporated / Approval: Step 1 approved (Option A) / Outcome: pending.
- Request: add a todo to remove the alias later; renaming the CSS file is acceptable / Disposition: confirmed and incorporated above / Approval: given in conversation; the todo is added during implementation / Outcome: pending.
