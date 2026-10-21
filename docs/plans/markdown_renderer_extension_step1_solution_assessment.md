> **Feature plan:** [Step 1](./markdown_renderer_extension_step1_solution_assessment.md) · [Step 2](./markdown_renderer_extension_step2_feature_description.md) · [Step 3](./markdown_renderer_extension_step3_development_plan.md) · [Step 4](./markdown_renderer_extension_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Option C: I want to do a test render with metrics which record use counts for each formatting type/syntax, then do Option B, and extend it using these metrics. For infrequently-used syntax, make a table so that we can decide what to implement or not implement. Set aside the actual implementation-extension as another Railspike (write Step 1, then continue with this one).

## Understood Intent

This is the separate, deferred renderer-extension cycle requested by [removal Option C](./markdown_dependency_removal/markdown_dependency_removal_step1_solution_assessment.md). The removal cycle owns the test render, syntax-use metrics, rare-syntax decision table, earlier-renderer restoration and third-party/history cleanup. This cycle later extends that restored project-owned renderer using those results. Draft Step 1 now; return to removal without advancing this cycle. No branch reuse is requested.

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

## Recommendation and viable slice

Choose B. On resumption, consume the removal cycle's saved metrics and decision table, confirm their corpus/revision still represents deployed docs, and resolve pending decisions before defining extension scope. No priorities, rarity threshold or implement/omit decisions are fabricated before the test render. Missing or stale evidence must be resolved before dependent planning.

Deliver an approved set of improvements through ordinary Docs URLs: readable selected syntax, working safe links/anchors, matching dynamic/static output, mobile/keyboard usability and recoverable publication. Retain HTML escaping and unsafe-URL/file-boundary protections regardless of syntax frequency. Verify representative source examples and adversarial cases; keep omitted syntax readable through its agreed fallback. Use project-owned code and existing approved platform facilities, with no copied vendor parser or replacement external dependency.

Keep the slice within Railspike's day/eight-stage limit; split further extension cycles if measured needs exceed it. Dependency/history removal remains the predecessor's responsibility and is not delayed by this cycle.

## Request disposition and approval

- **Request:** draft this extension Step 1 now and set implementation aside while continuing removal.
- **Disposition:** Step 1 drafted; planning beyond Step 1 and all extensions deferred. Resume only when the user returns to this cycle and its own approval gates are satisfied.
- **Approval:** awaiting independent `Approved Step 1`; no implementation or branch authorization. The active task returns to the removal assessment.
