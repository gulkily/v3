# Forte Shared Toolbar — Step 1: Solution Assessment

## Problem
Forte's three paned views have inconsistent toolbars — Board has the full button set, Activity has only "Board" + a disabled "Refresh", and Users has no toolbar at all (a different standalone chrome) — and none of them show which view is active or disable buttons that don't apply to it.

## Option A: Shared toolbar partial, minimal change to Users' body
Extract Board's current toolbar markup into a shared partial parameterized by the active view and a flag for whether board-thread controls apply. Users gains the same `paned-menubar` + `paned-toolbar` rows above its existing flat-list body (no change to that body's structure). New/Reply/Prev/Next get `disabled` on Activity and Users; the Board/Users/Activity destination buttons get a selected/"pressed" state on their own page.
- Pros: smallest change that satisfies exactly what was asked; one shared partial keeps all three toolbars byte-for-byte identical going forward.
- Cons: Users' toolbar will sit above a flat single-pane body rather than the three-pane layout used below Board's/Activity's toolbars — visual consistency stops at the toolbar row.

## Option B: Full chrome unification - migrate Users onto the three-pane layout
Same shared toolbar partial as Option A, plus restructure `forte_users.php` onto the same `paned-board-layout` shell (folder-tree-less list pane, etc.) that Board and Activity use, dropping the standalone-window/dialog-titlebar pattern entirely.
- Pros: total structural consistency, not just the toolbar.
- Cons: meaningfully larger scope than requested (the ask was toolbar consistency, not a Users page redesign); Users' content (a flat approved-user list) has no natural detail-pane counterpart, reopening design questions that are out of scope here.

## Option C: Per-page duplicated toolbar markup
Copy the toolbar HTML (with per-page `disabled`/selected variations) independently into all three page templates, no shared partial.
- Pros: none over Option A.
- Cons: three copies to keep in sync by hand on every future toolbar change - the exact drift risk that already showed up between Board's and Activity's toolbars in the prior feature (Activity shipped without Users/Activity buttons Board has).

## Recommendation
**Option A.** It's the smallest change that delivers exactly what was asked - toolbar consistency, a selected-state indicator, and disabling inapplicable buttons - and a shared partial eliminates the copy-drift risk Option C repeats.
