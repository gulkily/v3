> **Feature plan:** [Step 1](./markdown_renderer_extension_step1_solution_assessment.md) · [Step 2](./markdown_renderer_extension_step2_feature_description.md) · [Step 3](./markdown_renderer_extension_step3_development_plan.md) · [Step 4](./markdown_renderer_extension_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Option C: I want to do a test render with metrics which record use counts for each formatting type/syntax, then do Option B, and extend it using these metrics. For infrequently-used syntax, make a table so that we can decide what to implement or not implement. Set aside the actual implementation-extension as another Railspike (write Step 1, then continue with this one).

## Understood Intent

This is the separate, deferred renderer-extension cycle requested by [removal Option C](../markdown_dependency_removal/markdown_dependency_removal_step1_solution_assessment.md). The removal cycle owns measurements, restoration and dependency/history cleanup. This independently gated cycle now resumes Step 1 for project-owned renderer improvements.

## Problem

The earlier renderer has known formatting gaps, but extension scope should follow measured documentation needs and explicit rare-syntax decisions without reintroducing third-party components.

## Option A — Implement a broad Markdown compatibility baseline

Extend the restored renderer across the previously inventoried syntax, using the new metrics mainly to order delivery.

- Pros: broad consistency and fewer future unsupported documents.
- Cons: substantial parsing/security maintenance; implements rare features before their value is established.

## Option B — Extend from measured use and reviewed exceptions (recommended)

Choose syntax support using occurrence/document counts, observed rendering failures and reader importance; use the rare-syntax table to approve implementation or an explicit safe fallback for each uncommon form.

- Pros: focuses work on real documents; makes omissions reviewable and limits maintenance.
- Cons: needs deliberate support boundaries; some documents retain agreed fallbacks; counts do not establish correctness or importance alone.

## Refreshed evidence — 2026-10-23

Published main is `1a358bc9`; measurements at its local publication-audit descendant `ad7e8f5f` cover **1,402 tracked, catalog-eligible documents**, versus 1,312 frozen earlier; **zero render errors**. [Metrics and refreshed rarity inventory](./markdown_renderer_extension_metrics.json) preserve candidate counts and method. No removed reference parser was restored: these are source candidates, not new semantic AST counts or proof of correct formatting. Uncommitted plans are outside this corpus.

| Unsupported form | Candidate occurrences | Documents |
| --- | ---: | ---: |
| Nested list items | 9,716 | 453 |
| Loose-list boundaries | 6,104 | 929 |
| Checked / unchecked tasks | 626 / 542 | 36 / 34 |
| Pipe-table separators | 117 | 66 |
| Pipe-table non-separator rows | 999 | 66 |

## Option C — Repair list structure first

Prioritize nested and loose lists, continuations and preserved procedure numbering through ordinary Docs reading.

- Pros: reaches the largest measured structural gap.
- Cons: mixed nesting, block boundaries and continuation ambiguity create a wider parser change; task totals overlap list counts and do not justify including task controls automatically.

## Recommendation and viable first slice

Choose B, beginning with **readable pipe tables through normal dynamic and static Docs URLs**. This bounded block form affects 66 documents (previously 55); unlike list repair, it can be isolated without redesigning all block nesting. Render supported tables with escaped inline content, safe document links and keyboard-accessible phone scrolling; retain source fallback and publication recovery. Define boundaries for escaped pipes, inline code, uneven rows and malformed separators; verify unsafe links, dynamic/static parity and phone/desktop themes.

Defer list repair, task presentation and broader inline compatibility. For every refreshed variant used in at most five documents, propose retaining its current safe behavior for this slice, without permanent omission; existing metadata, comment suppression and Mermaid source remain. The original rarity table stays historical. No external dependency or source-document rewrite is proposed. Ordinary reader examples include `docs/architecture/private_messaging.md` and `docs/examples/extension_improvement_cookbook.md`. Fit within one day/eight stages; Step 2 defines support boundaries after approval.

## Study checkpoint and approval

`railspike-effort` / `v3/markdown_renderer_extension`, v1/R1/P2, exposed single rater; projected total **0.65**. Existing cycle joins now; earlier scores missing, not reconstructed. All R1 hashes verified. [Full checkpoint and instruction diagnostics](./markdown_renderer_extension_debug_log.md#study-checkpoint--2026-10-23). User explicitly approved Step 1. Continue to Step 2; implementation remains unapproved.
