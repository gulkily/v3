> **Feature plan:** [Step 1](./markdown_list_structure_step1_solution_assessment.md) · [Step 2](./markdown_list_structure_step2_feature_description.md) · [Step 3](./markdown_list_structure_step3_development_plan.md) · [Step 4](./markdown_list_structure_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Looks great, you can merge this and move to the next step. Please note somewhere that at some point we want to unconstrain docs from a narrow column, as this will make tables more accessible (less likely to have horizontal scrolling).

## Understood Intent

The [table slice](../markdown_renderer_extension/markdown_renderer_extension_step4_implementation_summary.md) is complete and accepted for merge. Interpret “next step” as assessing the next measured renderer gap, list structure, under a new cycle. Wider Docs layout is recorded as a separate future follow-up, not this syntax slice. No reuse of the merged table branch.

## Problem and evidence

Docs still flatten nested lists and split continuation paragraphs away from their steps. The [latest tracked-corpus report](../markdown_renderer_extension/render_current_summary.json), at `483ec05bb`, counts 9,716 nested-item candidates in 453 documents, 15,770 continuation candidates in 601, and 6,123 loose-boundary candidates in 932. Candidates overlap and are not semantic failure totals. Source examples include the retraction guide's six multiline steps and account-key plans' nested risk bullets.

## Option A — Read-only task presentation

Improve checked/unchecked task markers while keeping existing list structure.

- Pros: bounded presentation change; 626 checked and 542 unchecked candidates.
- Cons: leaves hierarchy and procedure grouping broken; task totals overlap list use.

## Option B — Preserve list structure in guides (recommended)

Support nested unordered/ordered items and their ordinary continuation/loose paragraphs, keeping source procedure numbers and safe inline content.

- Pros: addresses the largest measured structural gap and improves real guide reading.
- Cons: indentation and item boundaries need careful validation; broader embedded blocks need explicit fallbacks.

## Option C — General Markdown list compatibility

Support arbitrary list-contained blocks, task presentation and complex nesting together.

- Pros: broader compatibility.
- Cons: a substantially larger parser/security contract; unsuitable as the next bounded slice.

## Recommendation and viable slice

Choose B: through normal Docs URLs, preserve parent/child relationships, paragraphs attached to the right item and source step numbers. Reuse the canonical renderer/link policy, with matching dynamic/static output, phone/keyboard readability and existing publication recovery. Start with the measured ordinary list forms; leave task presentation, list-contained tables/quotes/fences and ambiguous indentation on explicitly reviewed readable-source boundaries. Keep working standalone tables, code, fragments and safety behavior intact. No external parser, source-document rewrite or wider-column CSS change.

Aim for a day/eight-stage maximum; Step 2 must bound supported indentation and embedded-block recovery before development planning. If that cannot fit, split before implementation rather than expanding silently.

## Requests and approval

Table merge is published as `b9d1cc874`. Wider Docs layout is recorded in the [table handoff](../markdown_renderer_extension/markdown_renderer_extension_step4_implementation_summary.md#merge-handoff-and-follow-up); deferred for future layout work, with scrolling retained where necessary. User explicitly approved Step 1; continue to Step 2. Implementation remains unapproved.

## Study checkpoint

`railspike-effort` / `v3/markdown_list_structure`, scope v1/R1/P2, merged baseline `b9d1cc874`, Codex single exposed rater. **0.90 total projection** for the bounded recommendation, including all planning, parser boundaries, reader/corpus/theme and copied-publication/recovery checks. Larger than the table slice's 0.65 because indentation/paragraph nesting changes block ownership; below startup recovery's 1.00 because existing delivery and safety infrastructure is reusable. All frozen R1 hashes verified; missing intake numeric value is retained, not backfilled. [Intake and diagnostics](./markdown_list_structure_debug_log.md). No implementation, measured labor, independent rating, acceptance or accepted-count change.
