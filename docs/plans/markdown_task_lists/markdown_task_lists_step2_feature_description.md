> **Feature plan:** [Step 1](./markdown_task_lists_step1_solution_assessment.md) · [Step 2](./markdown_task_lists_step2_feature_description.md) · [Step 3](./markdown_task_lists_step3_development_plan.md) · [Step 4](./markdown_task_lists_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Literal task markers obscure checklist completion state. The refreshed corpus has 1,182 unordered-task candidates across 47 documents; readers need clear state without suggesting that Docs can edit the source.

## User stories

- As a checklist reader, I want checked/unchecked tasks to be easy to distinguish so that I can scan completion state.
- As an assistive-technology reader, I want each state associated with its task text so that the status has a meaningful name.
- As a keyboard or phone reader, I want links, nested items and source fallbacks to remain usable in live and published Docs.

## Core requirements

- Recognize `[ ]`, `[x]` and `[X]` immediately after the spacing of a supported unordered item, followed by at least one space and nonempty task text. Convert that leading marker once; ordinary siblings retain their bullets. Ordered items, escaped/code-wrapped markers, malformed markers, markers later in text/paragraphs and empty task labels retain literal text.
- Show native disabled checkboxes with accurate checked state, replacing the redundant unordered bullet for that item. Each checkbox is named by its first paragraph's task text, including wrapped text; child items/later paragraphs are not part of its name. Repeated tasks must not create duplicate identifiers. No toggling by pointer/keyboard, tab stop, submission, persistence or source modification; add no explanatory legend.
- Preserve safe inline formatting and independently usable links in task text, list hierarchy/paragraph ownership, ordinary siblings and source-numbered procedures. Code examples, table cells and unsupported whole-list source regions remain literal under existing escaping, comment/metadata, image/no-fetch and catalog-link policies.
- Keep text alignment, checked/unchecked legibility and visible link/scroll focus on desktop/390px in default/Word97/Chicago/Midnight, without page-wide overflow. Task state works without JavaScript; Tab continues to links and existing deep/source regions, skipping disabled checkboxes.
- Match PHP and full/shared generated readers; retain publication failure responses, the usable prior release after a failed build, and restoration/retry. Source Markdown stays authoritative.

## Delivery scope and completion boundary

Application change extending project-owned rendering/presentation, with focused regressions, corpus classification, runbook/support-boundary updates, cycle artifacts and study-ledger bookkeeping. Dependency delta: **zero** external additions, upgrades or copied code. No task editing, ordered-task extension, broader list support, source-document rewrite, wider Docs layout, schema/navigation change, deployment or history/backup disposal.

Complete ordinary Docs discovery → checklist → readable named state → safe task link dynamically and in a copied published package. Unsupported forms remain readable; existing publication recovery must work. Release handoff requires boundary, accessibility and delivery evidence; full-suite, physical-device or live deployment success is not implied.

## Shared component inventory

Extend `PublicMarkdownRenderer`'s canonical supported-list path and reuse `PlatformDocsCatalog` inline/link policy. `PlatformDocsController`, reader templates and `platform_docs.css` serve ordinary/generated readers; reuse static publication, metrics/list-audit and browser/recovery harnesses. Search/discovery, downloads and non-Markdown previews retain existing contracts. No new UI surface or second renderer.

## Risks

- Marker false positives can change literal examples: validate beginning-of-item, malformed/escaped/empty markers and source fallbacks first; convert only the exact supported boundary.
- Disabled state can be faint or unnamed: validate real theme screenshots and browser accessibility name/checked/disabled state early; block acceptance until both visual and semantic checks pass.
- Label association can disrupt links or nesting: test independent task-link activation, repeated tasks, wrapped paragraphs and mixed siblings before publication; preserve canonical inline rendering and ownership.

## Flow and success criteria

1. Open the agent API onboarding or UI-copy audit checklist through Platform Docs.
2. Scan completion states, read wrapped/nested task text and follow a task link.
3. Read unsupported content as source; recover publication through existing retry paths.

Corpus evidence distinguishes displayed tasks from literal/source candidates. Boundary/security tests, accessible names/states, pointer/keyboard immutability, representative theme/phone screenshots, dynamic/static parity and copied-publication failure/recovery must pass; record visual/assistive-technology coverage limits.

## Approval and study checkpoint

Steps 1–2 explicitly approved; continue to Step 3. Stable `v3/markdown_task_lists`, v1 clarified/R1/P2, baseline `682ab5b40`, same exposed single rater; **0.35 total reaffirmed**. Frozen R1 hashes reverified; [checkpoint/diagnostics](./markdown_task_lists_debug_log.md#step-2). No implementation or accepted outcome.
