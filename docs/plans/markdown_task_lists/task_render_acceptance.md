# Read-only task rendering acceptance

This slice is verified locally on `feature/markdown-task-lists`. Live deployment, physical assistive-technology/device testing and the full suite remain unverified. The in-page counter and wider Docs column remain separate follow-ups.

> UAT correction: the initial native-disabled painting failed user visual acceptance. The CSS correction and renewed local evidence below supersede the earlier appearance assessment. Maintainer UAT remains pending.

## Corpus evidence

| Source snapshot | Markdown documents | Render errors |
| --- | ---: | ---: |
| Frozen intake, `682ab5b40` | 1,420 | 0 |
| Refreshed tracked corpus, `c908ec4aa` | 1,425 | 0 |

The [frozen](./render_frozen_summary.json) and [refreshed](./render_current_summary.json) reports retain source/HTML hashes, tags, errors and candidate counts without source/HTML dumps. The frozen manifest uses tracked, catalog-eligible paths at the intake revision and verifies every source hash. Later report/runbook edits are outside these snapshots; the original closeout used the verified Stage 2 renderer/CSS. The UAT correction below changes CSS only; renderer/corpus results remain applicable.

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

Final focused task/list/table/reader/catalog tests pass **52/52**. The complete static inventory/on-demand tests pass **2/2** in an [isolated source snapshot](./static_verification_evidence.json) whose hashes identify the initial tested candidate. The renderer remains unchanged; corrected CSS has separate evidence below. Metrics fixture, syntax, whitespace and artifact-link checks pass. These 54 focused cases are not a full-suite result.

No dependencies, task editing, counter, layout expansion, schema/navigation changes, deployment or recovery-storage disposal. The original closeout excluded unrelated research artifacts.

## UAT contrast correction — 2026-10-23

Native disabled semantics and first-paragraph labels remain unchanged. CSS now draws an 18px outlined unchecked box, a solid checked fill and a contrasting tick, using theme colors. Forced colors use Canvas/CanvasText. Arena's transparent panel initially hid the tick; an opaque page-color tick fixes that case without theme-specific overrides.

[Correction evidence](./task_contrast_verification.json) records 46 dynamic and 46 generated Chromium cases: all 16 registered themes, Auto light/dark, three additional LCD palettes, forced light/dark, each at 1280px and 390px with JavaScript disabled. All checked/unchecked pair screenshots were inspected. Computed unchecked border/backdrop contrast is at least 8.46:1; tick/fill is at least 3.95:1. These are fixture color measurements, not a general accessibility certification or physical-device result. Hollow/solid fill and a check shape distinguish state without relying on hue.

The actual copied-package shared CLI refresh passed, followed by eight generated reader scenarios covering names/state, pointer/Space immutability, Tab skipping, links, source downloads and existing list/table/scroll behavior. Focused task/list/table/reader/catalog PHP checks pass 52/52. The original full-build/failure/recovery and corpus evidence remains historical; renderer/source boundaries did not change. No full-suite or production claim. User retest and merge/publication remain open in the [consolidated checklist](../markdown_work_checklist.md).

Reproduce the contrast sweep against an isolated host containing `tests/fixtures/markdown_task_reader.md` at the fixture route:

```bash
DOCS_BASE_URL=http://127.0.0.1:18087 DOCS_EVIDENCE_ROOT=/tmp/docs-task-contrast node tests/browser/docs_task_contrast.mjs
```


## Maintainer acceptance — 2026-10-23

User accepted the corrected slice and requested merge into main. This supersedes the pending user-retest status above. Main integration preserves current origin/main work; production deployment, physical AT/devices and full-suite verification remain separate. See the consolidated checklist for current status.
