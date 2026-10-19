> **Feature plan:** [Step 1](./rename_windark_to_midnight_step1_solution_assessment.md) · [Step 2](./rename_windark_to_midnight_step2_feature_description.md) · [Step 3](./rename_windark_to_midnight_step3_development_plan.md) · [Step 4](./rename_windark_to_midnight_step4_implementation_summary.md)

Debug mode: enabled

# Rename the windark theme to midnight: Step 1 solution assessment

## Original Query
Is midnight theme still called windark internally? … Let's change it all to midnight, please.

## Understood Intent
The theme users see as "Midnight" is still `windark` in code. Rename the internal name everywhere (registry, CSS file and selectors, nav-icon mappings, site profiles, tests, `AGENTS.md`) so code and label agree.

## Problem
A rename changes a stored value: browsers keep `windark` in localStorage and the `theme-hint` cookie, and an unrecognised stored theme silently falls back to the site default, so Midnight users would lose their theme.

## Option A: Rename everywhere, with a legacy alias
Rename `windark` to `midnight` throughout (including `theme-midnight.css`) and treat a stored `windark` as `midnight`, in both the browser scripts and the server cookie hint, rewriting the stored value on next read. Alias is kept until a later cleanup.
- Pro: no user loses their theme; code and label match.
- Pro: alias is small and testable.
- Con: a few lines of legacy handling remain in `theme_toggle.js`, `layout.php`, and the cookie-hint path.
- Con: touches about 200 lines, mostly `theme-nav-icons.css`.

## Option B: Rename everywhere, no alias
The same rename, with no migration; people with a saved Midnight see the site default once and re-pick it.
- Pro: cleanest code; no legacy path.
- Con: silent, visible regression for existing Midnight users; the first page paint may flash the default.

## Option C: Keep `windark` internally
Leave the name as is and document that Midnight is the label for `windark`.
- Pro: zero risk.
- Con: the mismatch you want removed stays; AGENTS.md and tests keep saying Windark.

## Recommendation
Option A. It delivers "change it all" without a silent regression. Vertical slice: viable (rename, alias, then verify in the browser with an old saved `windark` value). Per AGENTS.md, nav icons must be rechecked in Midnight, Word97 and Chicago. Historical plan docs that mention windark (3 files) and the plans' past wording stay unchanged. Open questions: (1) alias kept indefinitely, or add a removal todo? (2) is renaming the CSS file acceptable given fingerprinted asset copies?

## Enhancement Requests
- Request: rename windark to midnight everywhere / Disposition: this assessment / Approval: pending Step 1 / Outcome: pending.
