# Authenticated users on the visitor statistics graph

Cycle v3/visitor_stats_user_line · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/visitor_stats_user_line`; host ledger [visitor_stats_user_line.md](../effort_ledger/visitor_stats_user_line.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: implemented, PR open; next permitted action: maintainer merge and a look on production.

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

Starting revision and branch: `da68f9ae`, `feat/visitor-stats-user-line`. Planning commit: `5140de1`.

## Study history

- 2026-11-25 UTC, intake and entry: **0.10**. Claude, single exposed rater (store and template read before scoring). R1 not reverified this session.
- 2026-11-25 UTC, closeout: retrospective **0.10**. Same rater.

## Execution history

- 2026-11-25, correction: the plan named `--link` for the line colour; no theme defines it as a variable. Switched to `--active-line`, which the base and each theme set.
- 2026-11-25, correction: the page test first expected a peak of 1; the test's signed-in root visit adds a second authenticated user, so the peak is 2.

## Outcome

The trend graph draws a line of estimated authenticated users per bucket over the request bars, with a point and hover title per bucket and "Authenticated users · peak N" in the top-right corner. The line is hidden when no bucket has an authenticated user. The SVG `desc` mentions the line and its scale. Changed: the template, `visitor_statistics.css`, the page test.

Limitation: like the bars, only buckets with traffic are drawn, so the line joins neighbouring observed buckets across empty hours or days rather than dropping to zero.

## Verification

- Passed: `./v3 test --smoke VisitorStatisticsPageTest VisitorStatisticsStoreTest` (smoke set plus those; 492 run, 0 failed on the final run). The page test checks the line, a point at the top with its title, and the peak label.
- Passed, headless Chrome with the real stylesheets and 21 days of seeded data: Gloss, Gloss Dark, Word97 and Bubblegum at 900px, and Gloss at 390px for the 24-hour view (the graph scrolls sideways as before).
- Not verified: production data, the maintainer's phone. Not run: the full suite.

## Final review

Diff: template, stylesheet, page test, this record, the index, the ledger and the debug log. No store, controller, schema or collection change; nothing new is retained.

Commit, merge and publication: pushed to `feat/visitor-stats-user-line` with an open PR; not merged.

— Claude · 2026-11-25 UTC · Model: claude-opus-5-5
