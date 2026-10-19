> **Feature plan:** [Step 1](./nav_active_theme_override_step1_solution_assessment.md) · [Step 2](./nav_active_theme_override_step2_feature_description.md) · [Step 3](./nav_active_theme_override_step3_development_plan.md) · [Step 4](./nav_active_theme_override_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

We seem to have experienced a regression in the Word 97 and Midnight themes. The styling for a selected navbar button is correct sometimes, but usually incorrect. It should be a pressed-down state and a light gray in the Word 97 theme (I forgot the correct color in Midnight), but it's currently dark blue on the pressed-down state.

## Understood Intent

Restore each theme's own selected-nav appearance on every page load: Word 97 sunken light gray (`#dfdfdf`), and Midnight sunken mid gray (`#3c3c3c`, per its stylesheet).

## Problem

Since theme stylesheets were split out (2026-09-23), the theme sheet loads *before* `site.css`. The shared `:root:not([data-theme="chicago"]) .nav-link.is-active` rule has the same specificity as the Word 97/Midnight rules, so it wins by source order and applies the blue `--active-*` style. It looks correct only when a runtime theme switch appends the theme sheet after `site.css`. Chicago is unaffected because the shared rule excludes it.

## Option A — Exclude the affected themes from the shared rule

Add Word 97 and Midnight to the shared rule's `:not(...)` list, as Chicago already is.

- Pros: smallest change; obvious intent
- Cons: `site.css` must list theme names; each future theme with its own active style needs the same edit; this class of collision stays possible elsewhere

## Option B — Make the shared rule a lower-priority default

Lower the shared rule's specificity (for example, with `:where()`) so any theme that defines its own active nav style wins regardless of load order. Add a regression test for the specificity relationship.

- Pros: fixes both themes and any other theme with its own active style; no theme list; independent of load order; small diff
- Cons: only fixes this rule; other shared rules after the critical-CSS marker could have the same issue (needs a quick audit)

## Option C — Restore theme-after-shared cascade order

Load or layer stylesheets so theme CSS always overrides `site.css` (load order or CSS cascade layers).

- Pros: fixes every equal-specificity collision introduced by the split
- Cons: conflicts with the first-paint/async loading design; layers would also flip higher-specificity shared rules, so the visual blast radius spans all 16 themes

## Recommendation

**Option B**, plus a brief audit of other shared rules that compete with icon-theme overrides (fix only confirmed nav/button collisions; record the rest as follow-up). It's a releasable slice: selected nav renders correctly in Word 97 and Midnight on a normal page load, with each theme visually checked, including nav icons. Option C should be its own cycle if the audit finds widespread collisions.
