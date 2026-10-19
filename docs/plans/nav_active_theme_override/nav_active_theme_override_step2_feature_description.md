> **Feature plan:** [Step 1](./nav_active_theme_override_step1_solution_assessment.md) · [Step 2](./nav_active_theme_override_step2_feature_description.md) · [Step 3](./nav_active_theme_override_step3_development_plan.md) · [Step 4](./nav_active_theme_override_step4_implementation_summary.md)

Debug mode: enabled

## Problem

On a normal page load, the selected nav button in Word 97 and Midnight shows the shared dark-blue active style instead of the theme's sunken gray. The shared default outranks theme rules by load order since the theme-stylesheet split. Approved direction: Step 1 Option B.

## User Stories

- As a Word 97 user, I want the current nav button to look pressed in with light gray so that it matches the theme and shows where I am.
- As a Midnight user, I want the current nav button to look pressed in with Midnight's gray so that the theme stays consistent.
- As a user of any theme with its own selected-nav style, I want that style to apply on every page load, not only after switching themes.

## Core Requirements

- A theme's own selected-nav style always beats the shared default, whatever order the stylesheets load in.
- Themes without their own selected-nav style keep the current shared default.
- Chicago's selected-nav appearance is unchanged.
- An automated check fails if the shared default can again override a theme's own selected-nav style.
- A brief audit of other shared rules that override Word 97 or Midnight nav or button styles: fix confirmed collisions in the same nav/button scope; record anything else as follow-up.

## Delivery Scope

- Work type: application change (shared and theme CSS, plus tests). No markup, script, or loading-order changes.

## Completion Boundary

- Entry: open any page with main nav or Tools subnav in Word 97 or Midnight with a fresh page load.
- Outcome: the selected button renders sunken gray (Word 97 `#dfdfdf`, Midnight `#3c3c3c`), with its nav icon intact.
- Recovery: none needed beyond reload; a theme switch must still give the same result.
- Release: tests pass, and visual checks pass in Word 97, Midnight, Chicago, and one theme that uses the shared default.

## Risks

- **Other themes change appearance** (Arena, Console, Forge, QDB, Mitrapclub, and Sticker also define selected-nav rules that are currently overridden). Impact: visible change for those users, though it restores their intended look. Validate early by comparing them before and after. Mitigation: list each change in Step 3, and flag any that look wrong for a decision.
- **Partial cascade fix** (other shared rules may collide in the same way). Impact: similar bugs remain. Validate early with the audit. Mitigation: fix only confirmed nav/button collisions here; defer the rest (Step 1 Option C).
- **Async `site.css` timing** hides the bug in some checks. Impact: false pass. Mitigation: check after full load on a fresh page, not only after a theme switch.

## Shared Component Inventory

- Main nav (`templates/partials/nav.php`) and Tools subnav (`templates/partials/tools_nav.php`) use the canonical `.nav-link.is-active`. Reuse it; no new component.
- Board, tags, and activity page navs plus board pagination use the same class and inherit the fix.
- Nav icons (`theme-nav-icons.css`) need no mapping changes; verify only that they still render.

## User Flow

1. User selects Word 97 or Midnight.
2. User opens or reloads any page with navigation.
3. The current section's button appears pressed in with the theme's gray; other buttons stay flat.

## Success Criteria

- Word 97 and Midnight show the correct sunken gray selected button on 100% of fresh loads across the main nav and Tools subnav.
- Chicago and shared-default themes look the same as before.
- Each of the six other themes listed under Risks shows its own selected-nav style, and the changes are recorded.
- The new regression test fails against the current CSS and passes after the fix.
