# Step 3: Development Plan — SQLite Query Panel Side-by-Side Layout

## Stage 1
- Goal: Restructure the query panel markup into distinct editor and results sub-containers without changing current visual behavior
- Dependencies: none
- Expected changes:
  - `templates/pages/sqlite_viewer.php`: wrap the existing editor controls and the existing results/pagination block each in their own container element inside `[data-role="sqlite-query-panel"]`; add a layout wrapper element carrying a `data-query-layout` hook (no value set yet, so current stacked CSS still applies unchanged)
  - No changes to existing element `id`s/`data-role`s that `sqlite_viewer.js` already selects
- Verification approach:
  - Manually run a query in the viewer at any viewport width and confirm the panel looks and behaves identically to before the change (editor above, results below)
  - Spot-check `sqlite_viewer.js` selectors (`runQuery`, `renderRows`, `renderPagination`) still resolve correctly against the new markup
- Risks or open questions:
  - Confirm no JS relies on the editor/results being direct siblings (vs. nested in new wrapper containers)
- Canonical components/API contracts touched: `[data-role="sqlite-query-panel"]` markup in `templates/pages/sqlite_viewer.php`

## Stage 2
- Goal: Add responsive CSS so the panel renders editor and results side-by-side at wide viewport widths, with the layout keyed off the new `data-query-layout` wrapper so a future toggle can override it by setting the attribute value
- Dependencies: Stage 1 (wrapper markup must exist)
- Expected changes:
  - `public/assets/site.css`: new rules for the `data-query-layout` wrapper — default (no explicit value) stays single-column/stacked; a `min-width` media query switches the default to a two-column grid (editor column, results column) at wide viewports; add (but do not wire up) selector overrides for explicit `data-query-layout="stacked"` / `="split"` values so a future toggle needs no further CSS work
  - No new breakpoint variable system introduced — a single new `min-width` breakpoint constant near the existing `min-width: 640px` block
- Verification approach:
  - Resize the browser across the new breakpoint and confirm layout switches between stacked and side-by-side with no horizontal page overflow at any width
  - Re-run the Stage 1 manual query check at both a wide and a narrow width to confirm no functional regression (run query, pagination, effective-query disclosure)
- Risks or open questions:
  - Pick a breakpoint wide enough that both editor and results remain readable (not simply reusing the existing 640px breakpoint, which is too narrow for two columns)
  - Confirm the query panel's existing full-viewport-width CSS trick (`calc(100vw - 2rem)`) still behaves correctly inside a two-column grid
- Canonical components/API contracts touched: `public/assets/site.css` rules for `[data-role="sqlite-query-panel"]` / its new layout wrapper
