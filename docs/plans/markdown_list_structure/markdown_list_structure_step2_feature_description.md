> **Feature plan:** [Step 1](./markdown_list_structure_step1_solution_assessment.md) · [Step 2](./markdown_list_structure_step2_feature_description.md) · [Step 3](./markdown_list_structure_step3_development_plan.md) · [Step 4](./markdown_list_structure_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Flattened nesting and detached continuation paragraphs obscure which guidance belongs to which list item. Source-numbered procedures and nested risk bullets need structural rendering through ordinary Docs reading.

## User stories

- As a guide reader, I want subordinate items and continuation paragraphs grouped with their parent so that instructions retain their meaning.
- As a procedure reader, I want visible source step numbers so that restarts and deliberate gaps remain recognizable.
- As a phone or keyboard reader, I want readable nesting and accessible fallbacks in both live and generated Docs.

## Core requirements

- Support space-indented unordered (`-`, `+`, `*`) and ordered (`.` or `)` suffix) items, including mixed nested types, up to eight list levels. Top-level markers begin at column zero. Child markers align at or beyond their parent's content column; siblings/dedents return to an established marker column. Tabs or indentation that cannot establish unambiguous ownership use fallback.
- Keep ordinary wrapped text and blank-line-separated paragraphs inside the owning item when aligned with its content column. Blank lines before a valid sibling preserve the list; unindented ordinary prose ends it. Preserve each explicit ordered number, including starts, restarts, repeated numbers and gaps. Marker-type changes at the same level start a sibling list.
- List-contained tables, quotes, fenced/indented code, excessive nesting and ambiguous indentation remain unsupported. Preserve the affected contiguous list block as keyboard-scrollable escaped source with its markers and content, respecting existing comment/metadata safety. Stop before following standalone content; do not silently flatten uncertain relationships or discard text.
- Reuse existing inline formatting, escaping and catalog link safety. Retain task markers as text, prose-comment hiding, code-example bytes, image alternatives/no fetching, heading fragments and working standalone table/fallback behavior. No broader inline or block compatibility.
- Match PHP and full/shared static readers without JavaScript. Nesting and fallback regions remain readable on desktop/390px in default/Word97/Chicago/Midnight, without page-wide overflow; retain keyboard link/fallback access and existing publication failure/retry behavior.

## Delivery scope and completion boundary

Application change using project-owned code and existing PHP facilities, with focused tests, corpus evidence, runbook/support-boundary updates and cycle/study-ledger bookkeeping. Dependency delta: **zero** external additions, upgrades or copied parser code. Wider Docs layout stays a separately recorded future follow-up. No source-document rewrites, schema/navigation changes, deployment or history/backup disposal.

Complete ordinary Docs discovery → guide → correctly grouped procedure/nested items → safe link destination, dynamically and in a copied published package. Unsupported blocks remain readable; failed publication retains the previous release and restoration/retry succeeds. Local acceptance must establish these outcomes before handoff; full-suite or live-deployment success is not implied.

## Shared component inventory

Extend canonical `PublicMarkdownRenderer` and reuse `PlatformDocsCatalog` link policy. `PlatformDocsController` supplies dynamic and generated readers; reuse existing reader templates, `platform_docs.css`, static builder/publication and browser/recovery harnesses. Search/discovery, downloads and non-Markdown previews retain existing contracts. No second renderer or new UI surface.

## Risks

- Wrong indentation ownership can change instructions: validate retraction-guide continuations, nested plan bullets, dedents and blank lines early; fallback the complete uncertain block.
- Fallback boundaries can absorb code or following tables: test unsupported embedded blocks and neighboring content first; require preserved example bytes and explicit boundaries before broader delivery checks.
- Deep nesting can hide content or unsafe links: validate eight-level/over-limit fixtures, hostile inline input and real phone/keyboard flows; preserve containment and canonical safety.

## Flow and success criteria

1. Open the retraction guide or a nested plan from Platform Docs.
2. Read step continuations/subordinate bullets and follow a safe link.
3. Read unsupported content in its source region; retry publication through existing recovery if needed.

Semantic parent/child and paragraph assertions, literal numbering, source-preservation/adversarial fixtures, refreshed zero-error corpus renders, representative theme screenshots, dynamic/static parity and copied-publication failure/recovery must pass. Source metrics are candidates, not proof that every document renders correctly; record unsupported forms and visual-review limits.

## Approval and study checkpoint

Steps 1–2 explicitly approved; continue to Step 3. `v3/markdown_list_structure`, v1 clarified/R1/P2, same merged baseline/exposed single rater; **0.90 total reaffirmed**. R1 hashes reverified; [checkpoint/diagnostics](./markdown_list_structure_debug_log.md#step-2). No implementation or acceptance inferred.
