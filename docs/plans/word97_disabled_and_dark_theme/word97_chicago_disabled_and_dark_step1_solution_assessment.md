> **Feature plan:** [Step 1](./word97_chicago_disabled_and_dark_step1_solution_assessment.md) · [Step 2](./word97_chicago_disabled_and_dark_step2_feature_description.md) · [Step 3](./word97_chicago_disabled_and_dark_step3_development_plan.md) · [Step 4](./word97_chicago_disabled_and_dark_step4_implementation_summary.md)

Debug mode: enabled

# Step 1: Solution Assessment — Word 97 disabled buttons and dark Chicago/Word 97

## Original Query
From todo.txt:

In word97 theme, disabled buttons don't look disabled. The text should be a more gray color.

Make a dark version of chicago/word97.

Can we combine these into one RAILSPIKE.md cycle, please?

## Understood Intent
- Fix: disabled buttons in the Word 97 theme must be visibly disabled (grayer text). `theme-word97.css` already has a `button[disabled]` rule (`#808080` text on a `#c0c0c0` face), so part of the work is finding why it reads as enabled.
- Add: a dark variant of the Chicago and Word 97 themes. "Chicago/word97" is read as both themes, which share nav-icon rules. The todo is ambiguous about whether that means one dark theme or one per theme.
- The disabled-button fix should carry into any dark variant so the two items stay consistent.

## Problem
Word 97 disabled buttons are not distinguishable from enabled ones, and users who like the Win9x look have no dark option.

## Option A — Fix disabled state, then one shared dark theme
Fix Word 97 disabled styling first. Then add a single new registry theme (e.g. "Win Dark") with its own stylesheet, borrowing Chicago/Word 97 chrome (bevels, title bars) re-colored for a dark palette.
- Pros: one new CSS file and registry entry; smallest test and menu footprint; simple to review
- Cons: a "dark Chicago" and a "dark Word 97" are not distinct (Word 97 serif body font and navy active color are lost or arbitrarily chosen)

## Option B — Fix disabled state, then two dark themes (dark Chicago, dark Word 97)
Same fix, then add `chicago-dark` and `word97-dark` as separate themes, each a copy of its light sibling with dark values.
- Pros: faithful to each theme's identity; matches the registry's one-theme-per-file convention
- Cons: roughly doubles CSS (word97 is ~520 lines), two menu entries and swatches, and duplicated scoped rules that can drift; the nav-icon rules, smoke-test allow-list and other `:is([data-theme="word97"], [data-theme="chicago"])` selectors need extending

## Option C — Fix disabled state, then dark as a variant of the existing themes
Fix the disabled state, then make the dark look a variant switch (e.g. an attribute layered on `word97`/`chicago`) that re-defines only the color custom properties and a few hardcoded hex overrides, so the light themes stay the single source of structure.
- Pros: one source of truth for chrome; no duplicated rules; Auto/System mode could pair light and dark
- Cons: needs new theme-selection plumbing (registry, popover, cycle button, anti-FOUC, `layout.php`) that the guide says themes normally avoid; word97 hardcodes many hex values that must be converted to variables first, which is larger and riskier

## Revised Direction (user review)
- One dark theme for now, not two.
- It combines the best of Chicago and Word 97 (e.g. Chicago's lighter shell and teal-derived palette feel with Word 97's title bars, bevels and navy active color), re-colored dark.
- The disabled-button fix applies to Word 97 only; Chicago is left unchanged.

## Recommendation
Go with **Option A**, now explicitly a blend: one new registry theme with its own stylesheet, borrowing from both existing themes, plus the Word 97-only disabled-button fix. Option B is deferred; Option C is not needed.
- Vertical slice: (1) Word 97 disabled-button fix, then (2) the dark theme reachable from the theme menu with swatch, cycle and anti-FOUC allow-list support. Fits within the eight-stage limit.
- Risk to settle in Step 2: which elements come from which theme, and the theme's name/label.
