# Authenticated users on the visitor statistics graph

Cycle v3/visitor_stats_user_line · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/visitor_stats_user_line`; host ledger [visitor_stats_user_line.md](../effort_ledger/visitor_stats_user_line.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: planned; next permitted action: implement.

## Original request

"for v3 visitor stats page can you please add/overlay a graph of authenticated users" (Ilya, 2026-11-25).

## Authorization

The request asks for the change directly; a bounded one-shot through an open PR. Scope: the trend graph on Tools → Visitor Statistics (template, its stylesheet, theme touch-up, page test). Not authorized: changes to what is collected or retained, the store's schema, or the table and tiles.

## Outcome and work type

UI change. The trend graph gets a line of estimated authenticated users per bucket (UTC hour for 24h, UTC day otherwise) drawn over the existing stacked request bars. The store already returns `authenticated_users` per bucket; no store change.

The line has its own vertical scale: user counts are far smaller than request counts, so on the bars' scale it would lie flat along the axis. A short label in the top-right corner names the line and its peak ("Authenticated users · peak N"), which is the only new copy. Each point carries a hover `title` with the bucket and its count, like the bars.

## Risks

- **A second scale misleads.** Mitigate with the peak in the label, so the line's top is a stated number, and an updated SVG `desc`.
- **One bucket draws no line.** A polyline with one point is invisible; points are drawn as circles too.
- **A theme hides the line.** Uses `--link` for the stroke, distinct from the green bars; check Gloss, Gloss Dark, Word97 and Bubblegum.

## Reuse decisions

- `templates/pages/visitor_statistics.php`: the existing bar loop's x positions are reused for the points (bar centre), in the same SVG.
- `public/assets/visitor_statistics.css`: new `.visitor-statistics-users-line` / `-point` / `-label` next to the bar classes; theme variables only.
- `tests/VisitorStatisticsPageTest.php` extended; no new component, controller or store change.
- Feedback: no matching item in `docs/feedback/items/` (PF-20261111-F44 is about the table, untouched).

## Completion conditions

`./v3 test --smoke VisitorStatisticsPageTest VisitorStatisticsStoreTest` passes with the page test checking the line and label. Rendered check in the four themes. PR open. Concepts touched: none.

Starting revision and branch: `da68f9ae`, `feat/visitor-stats-user-line`. Planning commit: pending.

## Study history

- 2026-11-25 UTC, intake and entry: **0.10**. Claude, single exposed rater (store and template read before scoring). R1 not reverified this session.

— Claude · 2026-11-25 UTC · Model: claude-opus-5-5
