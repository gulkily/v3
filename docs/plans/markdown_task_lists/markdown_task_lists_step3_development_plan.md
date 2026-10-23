> **Feature plan:** [Step 1](./markdown_task_lists_step1_solution_assessment.md) · [Step 2](./markdown_task_lists_step2_feature_description.md) · [Step 3](./markdown_task_lists_step3_development_plan.md) · [Step 4](./markdown_task_lists_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Ordinary Docs discovery/direct checklist URLs show named, immutable checked/unchecked state in PHP and copied full/shared releases. Task links, list ownership and literal/source fallbacks remain usable without JavaScript on phone/desktop. Require boundary/security, accessible-state/contrast, corpus and actual publication-failure retention/restoration/retry evidence. Live deployment, physical assistive-technology/device acceptance, counters and wider Docs layout remain outside scope; report limits explicitly.

## Key Risks

- **High risk:** marker conversion changes examples or fallback source; validate exact item/spacing/text boundaries first and convert only eligible unordered items.
- **High risk:** native disabled state is faint or unnamed; inspect real theme output and browser accessibility state/name early, block delivery until visual and semantic checks pass.
- **High risk:** labels swallow links, descendants or paragraphs; test naming boundaries and independent link activation before publication, reuse canonical inline/list ownership.

## Stage 1 — Canonical task rendering and boundaries

- Goal: show correct read-only state in ordinary checklist readers.
- Dependencies: Step 3 approval, fresh branch and planning commit.
- Expected changes: one leading-marker conversion on supported unordered items, labeled disabled checkbox, task-specific bullet replacement; retain existing renderer signature and inline/list contracts.
- Verification approach: checked/unchecked/uppercase markers, nested and mixed siblings, wrapped labels, later paragraphs, repeats/unique identifiers, safe links/HTML, image alternatives and existing table/list regressions.
- Risks or open questions: false conversion or wrong name; test ordered/empty/malformed/escaped/code/table/source-fallback negatives first, preserve literal text outside the boundary.
- Canonical components/API contracts touched: `PublicMarkdownRenderer`, `PlatformDocsCatalog` policy, registered focused task/reader tests.

## Stage 2 — Accessible immutable reader

- Goal: scan state and follow links without an editing affordance.
- Dependencies: Stage 1 verified and committed.
- Expected changes: scoped task alignment/contrast styling and existing browser-harness fixtures; no visible legend or additional actions.
- Verification approach: Tools→Docs→checklist, JavaScript-off reader, desktop/390px × default/Word97/Chicago/Midnight; checkbox name/checked/disabled state, no pointer/keyboard changes or checkbox Tab stops, independent links, nesting/scroll focus and no page overflow.
- Risks or open questions: faint controls or label interference; inspect loaded screenshots and accessibility names before proceeding, retain native disabled semantics and task-text association.
- Canonical components/API contracts touched: canonical reader markup, `platform_docs.css`, existing browser harness; no navigation changes.

## Stage 3 — Portable publication and recovery

- Goal: deliver matching state through normal published Docs.
- Dependencies: Stage 2 verified and committed.
- Expected changes: existing static/copied-package assertions and durable task/fallback delivery evidence.
- Verification approach: actual full/shared CLI publication, dynamic/static task/state/name/link parity, stale/deleted-reader refresh, injected renderer-failure 503, retained active release and restoration/retry; generated reader/theme matrix and source-download preservation.
- Risks or open questions: stale state or partial activation; exercise actual FrontController/publication paths with documentation stable during build snapshots.
- Canonical components/API contracts touched: existing controller/static builder, static tests and publication/recovery harness; no new API or persistence.

## Stage 4 — Corpus acceptance and closeout

- Goal: document supported tasks versus deliberate literal/source retention.
- Dependencies: Stage 3 verified and committed.
- Expected changes: frozen/refreshed metrics, classification of all 47 measured task documents, runbook boundaries and cycle/study-ledger closeout.
- Verification approach: frozen source hashes, zero-error corpus renders, displayed checked/unchecked counts versus literal/source candidates; focused task/list/table/catalog/static checks, metrics fixture, representative browser visuals and syntax/link/dependency checks.
- Risks or open questions: candidate counts imply false coverage; classify actual output/state and source preservation, record unsupported forms and visual/assistive-technology review limits.
- Canonical components/API contracts touched: existing metrics/list-audit evidence, runbook, cycle artifacts and host ledger.

## Execution and study checkpoint

Four stages target ≤1 hour each; split oversized work before starting and return to Step 2 for changed scope. After approval create a fresh branch from reconciled main, organize this cycle and commit approved Steps 1–3/debug log plus necessary index/reference fixes first. Metrics, substantive ledger and summary belong in stage commits; commit each verified stage/summary before the next. Zero external dependency/schema/nav changes; preserve local work/recovery storage.

Steps 1–3 explicitly approved; implementation authorized. Stable `v3/markdown_task_lists`, v1/R1/P2, baseline `682ab5b40`, same exposed single rater; **0.35 total reaffirmed**, including verification/recovery. [Checkpoint/diagnostics](./markdown_task_lists_debug_log.md#step-3). No implementation or accepted outcome.

## Separate follow-up request

User explicitly requested an in-page Markdown-checkbox counter be added to `todo.txt`. Recorded checked/total count as a deferred follow-up in commit `ee0f5b173`; only that file was committed. This authorizes the backlog edit, not counter implementation. It remains outside this slice, as does wider Docs layout.
