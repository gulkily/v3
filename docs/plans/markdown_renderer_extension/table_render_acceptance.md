# Pipe-table rendering acceptance

The approved standalone-table slice is verified locally on `feature/markdown-renderer-tables`. No external parser or component was added. Live deployment, physical devices and maintainer acceptance are pending; the full test suite was not run.

## Corpus and method

| Snapshot | Documents | Render errors | Rendered tables |
| --- | ---: | ---: | ---: |
| Frozen intake, `ad7e8f5f` | 1,402 | 0 | 116 |
| Refreshed tracked corpus, `483ec05bb` | 1,405 | 0 | 118 |

Existing `scripts/markdown_metrics.py` measured both snapshots with the canonical renderer at `483ec05bb`. The frozen run reuses the original manifest/revision and verifies every source hash. Reports retain source/HTML hashes, per-document tags, candidate counts and error fields without source/HTML dumps: [frozen](./render_frozen_summary.json), [refreshed](./render_current_summary.json). Full local reports and hashes are indexed inside them. Later report/runbook edits are outside these frozen snapshots.

All **66 originally measured table documents** are classified in [the per-document audit](./table_corpus_classification.json). Parse emitted table structure, require matching semantic header/body widths and compare measured separator totals with rendered tables plus recognized table fallback blocks; compare fallback text to original source. This structural audit does not mean all 66 documents received individual screenshot review.

- **116 of 117 separator candidates** render as tables. Sixty-four documents have supported measured tables without extra pipe-source regions.
- One further document, `docs/plans/forte_fdp_cycles.md`, renders its table and retains two orphan rows separated by blank lines as exact source. They lack their own headers/delimiters; a focused regression verifies this boundary.
- `docs/plans/page_asset_performance_inventory.md` retains its one table as complete escaped source because it is indented beneath a checklist item. Nested/list tables remain outside this slice.

Candidates remain heuristic source counts, distinct from independent semantic reference counts archived by the removal cycle. Zero render errors alone do not establish formatting correctness.

## Reader and delivery evidence

[Dynamic](./dynamic_browser_evidence.json) and [generated](./static_browser_evidence.json) measurements cover desktop/390px × default/Word97/Chicago/Midnight with JavaScript disabled. Real Tools→Docs→guide entry, Tab/arrow scrolling, focus, semantic tables, alignment, readable columns, no page-wide overflow, malformed fallback and actual table-cell navigation pass. Representative fixture screenshots were inspected on all four phone themes and desktop theme coverage; normal guide/procedure/code behavior remains checked. An initial long-code column squeeze was corrected and its dimension regression added.

[Copied-package acceptance](./release_recovery_evidence.json) verifies actual full/shared publication, table/fallback parity, deleted readers/downloads, stale refresh, injected renderer-failure 503, retention of the usable active release and restoration/rebuild. [Static discovery](./static_discovery_evidence.json) adds eight theme/viewport scenarios. Application source is unchanged after the tested Stage 4 revision.

Focused PHP checks cover catalog, ordinary readers, adversarial tables and full static inventory/on-demand generation: **37 distinct cases passed across stage runs**. The hand-counted metrics fixture, PHP/Node/Python syntax and whitespace checks passed. These results are scoped verification, not a full-suite or production-deployment claim.
