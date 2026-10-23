# List-structure rendering acceptance

The approved list slice is technically complete locally on `feature/markdown-list-structure`. Live deployment, physical devices, full-suite verification and maintainer acceptance remain pending. No external component or wider Docs layout change was introduced.

## Frozen and refreshed evidence

| Source snapshot | Documents | Render errors | Standalone rendered tables |
| --- | ---: | ---: | ---: |
| Frozen measured corpus, `483ec05bb` | 1,405 | 0 | 118 |
| Refreshed tracked corpus, `4876f2337` | 1,411 | 0 | 120 |

The [frozen](./render_frozen_summary.json) and [refreshed](./render_current_summary.json) summaries retain per-document source/HTML hashes, tags, errors, source candidates and report hashes without source/HTML dumps. The frozen render verifies every original source hash; later acceptance/runbook/report edits are outside these snapshots. Renderer/CSS were unchanged after the Stage 4 delivery candidate.

The [nested-list audit](./list_corpus_classification.json) covers all **453 original measured documents / 9,716 nested-item candidates**. Actual output has balanced semantic parent/item relationships, explicit ordered-item values and maximum observed nesting depth three; adversarial fixtures separately verify eight supported levels and nine-level source recovery.

- **447 documents:** structured nested lists, no list-source regions.
- **5 documents:** structured nesting alongside unsupported source regions.
- **1 document:** source-only nested candidates (`docs/runbooks/qdb_archive_import.md`, containing an embedded fenced example).
- **9 source regions** across these six documents retain their complete safety-filtered source. Source indicators identify embedded fences/tables/quotes or remaining indentation/ownership ambiguity; they are nonexclusive clues, not universal parser diagnoses.

Source equality uses the canonical prose-comment boundary, with independent regression fixtures verifying comment privacy and example-byte preservation. Candidate counts are heuristic; classification does not prove all candidates are semantic nesting or that all 453 documents were visually reviewed. Representative real-guide and controlled-fixture checks establish the specific ownership behavior.

Reproduce from this cycle’s checkout with the recorded renderer (unchanged since `4876f2337`; later documentation commits do not alter it):

```bash
python3 scripts/markdown_metrics.py --baseline docs/plans/markdown_renderer_extension/render_current_summary.json --output /tmp/list-frozen.json
python3 -B scripts/markdown_list_corpus_audit.py --metrics /tmp/list-frozen.json --output /tmp/list-audit.json
```

## Reader and delivery

[Dynamic](./dynamic_browser_evidence.json) and [generated](./static_browser_evidence.json) scenarios cover desktop/390px × default/Word97/Chicago/Midnight with JavaScript disabled: ordinary Tools→Docs→guide entry, six owned procedure paragraphs, mixed ancestry, literal repeats/gaps, parent paragraphs after children, deep-list and source Tab/arrow/focus, code bytes, safe list/table destinations and no page overflow. Representative phone screenshots in all themes and desktop samples were inspected; this is not exhaustive per-document or physical-device review.

[Copied-package acceptance](./release_recovery_evidence.json) verifies complete full/shared CLI publication, dynamic/static lists/tables/fallback parity, stale/deleted-reader refresh, injected renderer-failure 503, retention of the active published release, restoration and successful retry. [Static discovery](./static_discovery_evidence.json) adds eight scenarios. Together, reader/discovery evidence covers 24 distinct theme/viewport scenarios.

Final catalog/reader/list/table checks pass **46/46**; the complete static inventory/on-demand checks pass **2/2**. Hand-counted metrics, PHP/Node/Python syntax and whitespace checks pass. These 48 focused cases and release checks are not a full-suite or production-deployment claim.
