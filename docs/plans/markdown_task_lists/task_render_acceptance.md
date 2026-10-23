# Read-only task rendering acceptance

This slice is verified locally on `feature/markdown-task-lists`. Live deployment, physical assistive-technology/device testing and the full suite remain unverified. The in-page counter and wider Docs column remain separate follow-ups.

## Corpus evidence

| Source snapshot | Markdown documents | Render errors |
| --- | ---: | ---: |
| Frozen intake, `682ab5b40` | 1,420 | 0 |
| Refreshed tracked corpus, `c908ec4aa` | 1,425 | 0 |

The [frozen](./render_frozen_summary.json) and [refreshed](./render_current_summary.json) reports retain source/HTML hashes, tags, errors and candidate counts without source/HTML dumps. The frozen manifest uses tracked, catalog-eligible paths at the intake revision and verifies every source hash. Later report/runbook edits are outside these snapshots; renderer/CSS remain unchanged from the verified Stage 2 candidate.

[All 47 measured task documents](./task_corpus_classification.json) are classified against their original hashes:

| State | Source candidates | Native disabled display | Preserved source candidates |
| --- | ---: | ---: | ---: |
| Checked | 644 | 598 | 46 |
| Unchecked | 538 | 511 | 27 |
| Total | 1,182 | 1,109 | 73 |

Every document independently accounts for its checked and unchecked candidates. Forty documents contain displayed tasks without retained task-source candidates, three combine display/source, and four retain their tasks as source. Unsupported list blocks remain readable under the existing list contract; no source rewrite was used to improve these totals.

The audit inspects real HTML for disabled checkbox type, exactly one nonempty label, no generated IDs/names/submission/action attributes, no descendant/paragraph capture, bounded list structure and unchanged safety-filtered source recovery. It saves state and label-text hashes, not task text or HTML. These parsed labels are not a substitute for browser accessibility naming or physical assistive-technology review. Source candidates are heuristic, not a general syntax-conformance guarantee.

Reproduce from this cycle's checkout:

```bash
python3 -B scripts/markdown_task_corpus_audit.py --baseline docs/plans/markdown_task_lists/markdown_task_lists_metrics.json --output /tmp/task-audit.json
```

## Reader and delivery evidence

[Dynamic](./dynamic_browser_evidence.json) and [generated](./static_browser_evidence.json) readers cover desktop/390px × default/Word97/Chicago/Midnight with JavaScript disabled. Checks include ordinary Tools→Docs→browse→onboarding checklist discovery, browser role/name/checked/disabled state, repeated task names without IDs, wrapped first-paragraph labels, child/later-paragraph exclusion, pointer/Space immutability, Tab skipping and actual task-link activation. Existing list/table/code/source focus/scroll and no-overflow checks remain covered. Representative screenshots across all phone themes and desktop samples were inspected; all 47 documents were classified structurally, not individually visually reviewed.

[Copied-package verification](./release_recovery_evidence.json) covers actual full/shared CLI publication, task/list/table/fallback parity, exact Markdown downloads, stale/deleted readers, injected renderer-failure 503, retained published task page, restoration and successful retry. [Static discovery](./static_discovery_evidence.json) supplies eight further scenarios: 24 distinct reader/discovery theme/viewport scenarios overall.

Final focused task/list/table/reader/catalog tests pass **52/52**. The complete static inventory/on-demand tests pass **2/2** in an [isolated source snapshot](./static_verification_evidence.json) whose renderer/CSS/test hashes match this checkout. Metrics fixture, syntax, whitespace and artifact-link checks pass. These 54 focused cases are not a full-suite result.

No dependencies, task editing, counter, layout expansion, schema/navigation changes, deployment or recovery-storage disposal. Unrelated untracked research artifacts remain outside the commits.
