> **Feature plan:** [Step 1](./listing_query_dialog_step1_solution_assessment.md) · [Step 2](./listing_query_dialog_step2_feature_description.md) · [Step 3](./listing_query_dialog_step3_development_plan.md) · [Step 4](./listing_query_dialog_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Standard online boards and all codebase SQL displays open a retained dialog, automatically run editable read-only SQL against saved public data, support rerun, and return to the unchanged background. Preserve standalone viewer capabilities.
- Recover drafts across same-tab navigation/reload; retain results/controls/scroll across close/reopen. Missing data, query/load errors, and denied storage preserve SQL with actionable recovery. Display provenance, freshness limitations, and refresh.
- Release requires fixed-data parity, keyboard/small-screen and failure checks, cold/warm measurements, and no warm-reopen download/initialization. Verify locally with PHP/browser integration; deployment is outside scope. No schema/export changes. Broader listing families and unavailable-source explanations remain follow-ups under approved Step 2.
- Eight sequential stages, approximately one hour each; split/replan before implementing any oversized stage. After fresh `Approved Step 3`, branch, organize/commit plans, then verify and commit each stage with its summary before advancing. Preserve unrelated work.

## Key Risks

- **High risk: parity.** Board filters combine JSON labels, root-post scores, pinned priority, and tie-breaking. Compare fixed-data output before wiring each variant; reuse repository semantics.
- **High risk: lost work/responsiveness.** Loading or context switches may overwrite edits or block interaction. Validate the initial slice early; retain the mounted viewer, isolate drafts, and block dependent rollout on failures.
- **High risk: stale/incompatible saved data.** Validate source/schema before execution; retain drafts and provide explicit refresh/retry. Never substitute the offline snapshot or expand exports.

## Stage 1 — Codebase dialog slice
- Goal: open existing SQL, auto-run, edit/rerun, close/reopen.
- Dependencies: planning commit; inventory codebase SQL and standard board variants.
- Expected changes: reuse viewer in a retained shared host; wire codebase SQL, loading/retry, background/focus restoration.
- Verification approach: browser replay plus standalone viewer regression.
- Risks or open questions: editor reset; validate reopening early; reuse one mounted instance.
- Canonical components/API contracts touched: SQLite viewer/template/CSS, codebase template, asset wiring; `openQuery({key, label, source, sql})` conceptual interface.

## Stage 2 — Saved database reuse
- Goal: reopen queries from saved public data without another download.
- Dependencies: Stage 1 verified/committed.
- Expected changes: shared load promise, persisted published database, source/revision display, unavailable-data retry; preserve in-memory fallback when persistence fails.
- Verification approach: save, disable network, reopen; count initialization/download calls; reject corrupt data.
- Risks or open questions: wrong cached source; validate identity/schema; use explicit source keys.
- Canonical components/API contracts touched: viewer loader and browser storage; published download endpoint unchanged.

## Stage 3 — Recoverable editing sessions
- Goal: retain distinct drafts across listings and same-tab navigation/reload.
- Dependencies: Stage 2 verified/committed.
- Expected changes: per-context draft recovery, same-page controls/results/scroll retention, context-switch race handling; revisits restore drafts, fresh contexts run supplied SQL.
- Verification approach: edit two contexts, navigate/reload, deny storage, switch during loading.
- Risks or open questions: stale load overwrites edits; validate rapid switching; discard obsolete completions.
- Canonical components/API contracts touched: shared viewer/session host; source/context-keyed draft contract.

## Stage 4 — Standard All boards
- Goal: inspect All boards with matching ordering, including empty results.
- Dependencies: Stage 3 verified/committed; fixed-data ordering parity established.
- Expected changes: contextual action/query for newest, oldest, top, pinned priority and tie-breaking; match actual page scope, which currently has no standard-board pagination.
- Verification approach: compare full ordered IDs with repository/page output, including ties and hidden/retracted content.
- Risks or open questions: generic presets differ; validate fixtures first; derive query from canonical semantics.
- Canonical components/API contracts touched: BoardPageController, ThreadRepository, board template; conceptual `boardQueryContext(view, sort)` builder.

## Stage 5 — Standard Liked boards
- Goal: complete all standard view/sort combinations.
- Dependencies: Stage 4 verified/committed.
- Expected changes: Liked query contexts, label/root-score predicates and ordering; retain separate drafts per normalized selection.
- Verification approach: All/Liked × newest/oldest/top, empty and negative-root-score cases; check fallback sort semantics and route aliases. Forte/QDB remain excluded.
- Risks or open questions: root score versus aggregate score mismatch; validate adversarial fixtures; match controller rules.
- Canonical components/API contracts touched: board query builder, BoardViewOptions/controller, existing board query catalog where reused.

## Stage 6 — Refresh and failure recovery
- Goal: update stale saved data and recover failures without losing edits.
- Dependencies: Stage 5 verified/committed.
- Expected changes: explicit refresh, freshness notice, retry for missing/incompatible data, preserved previous usable database on failed replacement, query-error recovery.
- Verification approach: stale/missing/corrupt data, failed download/refresh, malformed SQL and rerun; confirm no offline-snapshot substitution.
- Risks or open questions: refresh destroys work; validate interrupted replacement; switch only after successful validation.
- Canonical components/API contracts touched: shared source loader, provenance/status controls, viewer query execution.

## Stage 7 — Accessible return to page
- Goal: reliably return to the same page state on keyboard and small screens.
- Dependencies: Stage 6 verified/committed.
- Expected changes: focus containment/return, Escape/close, responsive editor/results and scroll restoration; preserve page selection/forms.
- Verification approach: keyboard traversal, narrow viewport, background scroll/selection/form replay and viewer controls retention.
- Risks or open questions: focus/scroll jumps; validate native dialog behavior; restore captured state without rerendering the page.
- Canonical components/API contracts touched: shared dialog host/styles and existing viewer layout.

## Stage 8 — Release acceptance
- Goal: verify the first-release contract and record evidence.
- Dependencies: Stages 1–7 verified/committed; no unresolved material risks.
- Expected changes: focused integration corrections and final coverage/status summary; preserve follow-up inventory.
- Verification approach: audit every codebase SQL display and standard board combination/alias; replay automatic execution, drafts, recovery, accessibility and standalone behavior. Run affected PHP/query-catalog/presentation tests and browser checks; record cold/warm shell/results timings and no-download warm reopen.
- Risks or open questions: overclaiming coverage; validate checklist completeness; distinguish passed, failed, blocked, pending and inapplicable checks, including external deployment.
- Canonical components/API contracts touched: existing PHP/browser test harnesses, query catalog generator when needed, implementation summary.
