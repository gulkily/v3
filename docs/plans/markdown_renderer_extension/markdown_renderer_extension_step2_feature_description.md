> **Feature plan:** [Step 1](./markdown_renderer_extension_step1_solution_assessment.md) · [Step 2](./markdown_renderer_extension_step2_feature_description.md) · [Step 3](./markdown_renderer_extension_step3_development_plan.md) · [Step 4](./markdown_renderer_extension_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Pipe tables remain source text in Docs, obscuring comparisons and leaving cell links unclickable. Refreshed measurements identify 117 separator candidates across 66 documents; these heuristic counts establish opportunity, not correctness.

## User stories

- As a Docs reader, I want readable comparisons and safe cell links so that I can use guides without interpreting table markup.
- As a phone or keyboard reader, I want wide tables to scroll within the reader so that every column stays accessible.
- As a publisher, I want matching dynamic/static readers and recoverable failures so that improved formatting reaches ordinary releases reliably.

## Core requirements

- Support standalone pipe tables with one header row, a matching delimiter row, at least two columns and optional outer pipes. Delimiters use three or more hyphens with optional alignment colons; preserve left/center/right alignment. Body rows must match the header width; empty cells are valid. Support escaped pipes and pipes inside the renderer's existing single-backtick inline code spans.
- Render semantic column headers and reuse current inline code, emphasis and safe link behavior within cells. Preserve escaping, documentation/fragment boundaries, unavailable-link recovery and the prohibition on image fetching or executable HTML.
- Keep malformed, uneven or ambiguous table blocks readable as escaped source without losing adjacent prose. Multiline cells, merged cells, nested tables and tables inside lists/quotes retain readable source behavior. Broader list, task and inline syntax extensions remain deferred.
- Tables work without JavaScript, retain visible keyboard focus and horizontal scrolling, and cause no page-wide overflow on phones. Preserve document navigation and surrounding code/procedure behavior.
- Use the same rendering contract for PHP and full/shared static publication; retain failure responses, the previous active release after a failed build, and successful retry after recovery.

## Delivery scope and completion boundary

Application change using project-owned code and existing PHP facilities. Extend the canonical reader and existing table presentation; include focused tests, runbook/support-boundary documentation, cycle artifacts and study-ledger bookkeeping. Dependency delta: **zero** external components, upgrades or copied parser code. No source-document rewrite, navigation change, schema change, deployment or history/backup disposal.

Completion requires ordinary Docs discovery → reader → table/cell destination to work dynamically and in a copied published package, with keyboard/phone access and publication recovery verified. Unsupported forms keep their readable fallback; this does not claim general Markdown compatibility.

## Shared component inventory

`PublicMarkdownRenderer` and `PlatformDocsCatalog` remain canonical for rendering and link safety. `PlatformDocsController` supplies both ordinary readers and static generation; reuse that path. Existing reader templates and `platform_docs.css` table/scroll/focus styles provide presentation across themes. Library/search, downloads and non-Markdown previews retain their existing contracts. No second renderer or new UI surface.

## Risks

- Wrong cell boundaries can change meaning: validate escaped/code pipes and malformed blocks first; require whole-block fallback when ambiguous.
- Cell markup can bypass safety: validate hostile links/HTML and document boundaries early through the canonical inline path.
- Wide cells can hide content: check real desktop/390px readers, keyboard scrolling and default/Word97/Chicago/Midnight themes; contain overflow before release.

## Flow and success criteria

1. Open a guide through Platform Docs or its existing URL.
2. Read aligned headers/cells, scroll wide tables and follow a safe cell link.
3. For unsupported syntax, read preserved source; after publication failure, retain the prior release and retry using existing recovery.

Representative architecture/cookbook guides and adversarial fixtures must pass dynamic/static parity, source-fallback, safety and accessibility checks. Refresh the corpus render report with zero errors and review the measured table documents for supported rendering versus explicit fallback; a render-error count alone cannot establish success.

## Approval and study checkpoint

Steps 1 and 2 explicitly approved; continue to Step 3. Scope v1 clarified, `v3/markdown_renderer_extension` / R1/P2; **0.65 total reaffirmed**. Same baseline and exposed single rater; frozen references reverified. Detailed checkpoint in the [debug log](./markdown_renderer_extension_debug_log.md#step-2--2026-10-23). No implementation or outcome acceptance.
