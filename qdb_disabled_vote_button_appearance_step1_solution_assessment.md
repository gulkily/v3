> **Feature plan:** [Step 1](./qdb_disabled_vote_button_appearance_step1_solution_assessment.md) · [Step 2](./qdb_disabled_vote_button_appearance_step2_feature_description.md) · [Step 3](./qdb_disabled_vote_button_appearance_step3_development_plan.md) · [Step 4](./qdb_disabled_vote_button_appearance_step4_implementation_summary.md)

# QDB Disabled Vote Button Appearance – Solution Assessment

## Original Query
For the qdb theme/site, when a voting button becomes disabled, I want it to change appearance to a gray color / less activatable looking color.

## Problem Statement
In the qdb theme, a vote/flag button that becomes disabled after the viewer acts still looks like an active control, so viewers cannot tell it is no longer clickable.

## Option A: Qdb-theme-only gray disabled style
Add a disabled-state rule scoped to the qdb theme's quote vote buttons that switches them to a muted gray, drops any hover/pointer affordance, and keeps the applied label readable. Other themes are untouched.

- Pros: smallest change; matches the request ("qdb theme/site"); no risk to other themes
- Pros: lives beside the existing qdb vote button styling
- Cons: other themes with the same quote actions keep their current disabled look
- Cons: the gray must be chosen to work against qdb's alternating card backgrounds

## Option B: Shared disabled style for all quote vote buttons
Define the gray disabled look once for the shared quote vote button (used across themes) and let each theme inherit it, with qdb as the reference appearance.

- Pros: consistent behavior across themes; one rule to maintain
- Cons: changes themes the user did not ask about; each theme's palette may need tuning
- Cons: larger review and verification surface

## Option C: Gray via a per-theme color token
Introduce a "disabled control" color token that every theme can set, and have the shared disabled rule read it; set it to gray in qdb only.

- Pros: extensible; themes opt in with their own palette
- Cons: adds a new theming concept for a one-color change; more moving parts than the problem warrants

## Recommendation
Option A. It delivers exactly the requested outcome as a single vertical slice: a viewer votes in qdb, the vote buttons turn gray and read as inactive (the chosen one stays underlined, derived locally from the remembered vote marker), and the page behaves the same everywhere else. Cross-theme consistency (Option B/C) can be a separate follow-up if wanted.

**Awaiting approval:** reply `Approved Step 1` to proceed to Step 2.
