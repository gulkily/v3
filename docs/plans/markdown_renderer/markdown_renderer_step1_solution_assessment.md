> **Feature plan:** [Step 1](./markdown_renderer_step1_solution_assessment.md) · [Step 2](./markdown_renderer_step2_feature_description.md) · [Step 3](./markdown_renderer_step3_development_plan.md) · [Step 4](./markdown_renderer_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Should we make another Step 1 for improving the Markdown renderer?

## Understood Intent

Assess shared Markdown rendering improvements independently of [Docs Discovery](../docs_discovery/docs_discovery_step1_solution_assessment.md), which remains set aside. Rendering can ship first; discovery can subsequently reuse it. This is a new planning cycle with no branch reuse or implementation approval.

## Problem and evidence

The existing renderer breaks formatting already used in project documentation: the [inventory](../markdown_renderer_inventory.md) found nested lists in 435 documents, wrapped list items in 153, tables in 46, checkboxes in 42, and fragment links in 47, alongside reference links and other unsupported syntax.

[PublicMarkdownRenderer](../../../src/ForumRewrite/Docs/PublicMarkdownRenderer.php) serves both dynamic and generated documentation. PHP 8.1 compatibility is required; the project currently has no Composer dependency setup.

## Option A — Expand the custom renderer

Define the supported syntax from the inventory and extend the current parser and document styles to meet that contract.

- Pros: retains existing loading/deployment conventions; direct control over HTML and link policy.
- Cons: multiline/nested structures require substantial parsing work; the project owns Markdown edge cases, compatibility and security maintenance.

## Option B — Adopt an established PHP parser (recommended)

Use an established Markdown parser with the extensions needed by the inventory, behind the existing shared rendering boundary and explicit safe-output policy.

- Pros: broad syntax coverage and established parsing behavior; project work concentrates on document links, presentation and deployment integration.
- Cons: adds dependency/licensing/update obligations; parser/version, PHP 8.1 support, extension behavior and dependency packaging must be validated before selection. No library/version is selected by this assessment.

## Recommendation and viable slice

Choose B, subject to a small compatibility and packaging check before committing to a library. Evaluate bundled dependencies versus a build-time Composer workflow; production should receive a complete runnable application without an unplanned installation requirement.

- Deliver a complete reading flow: open existing documentation, read correctly structured lists/tables/code, follow document and section links, and use the same behavior in dynamic and static delivery. Preserve existing document URLs.
- Cover the inventoried core: multiline/nested/loose lists, tables, task lists, emphasis variants, reference/inline/autolinks, strikethrough, blockquotes, code fences with language labels, and stable duplicate-safe heading anchors. Tables and code must remain usable on mobile and by keyboard.
- Preserve HTML escaping/disabling and unsafe-link rejection; keep checkboxes non-interactive. Resolve documentation-relative links safely and handle unsupported source/asset destinations explicitly without exposing arbitrary repository files.
- Give special formats an explicit policy: safe image handling or a clear alternative for the lone HTML logo; readable Mermaid source fallback; Marp/front-matter source fallback without applying embedded CSS. Diagram/slideshow engines, syntax highlighting and mathematical rendering are deferred.
- Verify real examples from the inventory, including the retraction guide, nested checklists, reference citations and fragment links; cover safe rendering, static/dynamic parity, packaged deployment and regeneration/recovery of stale generated pages.

Search, automatic discovery, catalog redesign, table-of-contents/copy-button UI, and broad document rewrites remain separate. The rendering slice needs only the presentation changes necessary to make supported document content readable.
