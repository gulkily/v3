> **Feature plan:** [Step 1](./markdown_renderer_step1_solution_assessment.md) · [Step 2](./markdown_renderer_step2_feature_description.md) · [Step 3](./markdown_renderer_step3_development_plan.md) · [Step 4](./markdown_renderer_step4_implementation_summary.md)

Debug mode: enabled

## Problem
Existing documentation is reachable, but the renderer loses tables, list structure and section links. Readers cannot reliably follow instructions in the post-retraction guide and other existing documents.

## User stories
- As a reader, I want readable documentation and working document/section links so that I can follow a workflow.
- As an operator, I want consistent rendering in dynamic and generated pages so that publishing documentation does not change its meaning.

## Core requirements
- Support the inventoried formatting: nested, multiline and loose lists; tables; noninteractive task lists; emphasis variants; inline/reference/autolinks; strikethrough; blockquotes; labeled code fences; and stable, duplicate-safe heading anchors.
- Preserve safe output: escape or disable arbitrary HTML, reject unsafe URLs, resolve document-relative links within approved boundaries, and clearly handle unavailable sources/assets. Provide readable Mermaid and Marp/front-matter fallbacks without executing embedded styles; safely display the existing logo or an explicit alternative.
- Use one rendering contract for dynamic and generated documents. Preserve existing document URLs, support reading without JavaScript, and keep tables/code usable on mobile and with a keyboard.
- Adopt an established PHP parser only after verifying PHP 8.1 compatibility, required extensions, licensing and reproducible dependency packaging. A deployed application must include its runtime dependencies without an unexpected host installation step.
- Provide a documented regeneration/recovery path for stale generated pages and a diagnosable failure path for incomplete packages; never fall back to emitting untrusted HTML.

## Delivery scope
Application change: shared rendering, necessary reader styling, dependency packaging, verification and operator guidance. Docs Discovery remains set aside. Search, catalog redesign, table-of-contents/copy controls, broad document rewrites, diagram/slideshow engines, math rendering and syntax highlighting are excluded. Step 1 is approved; this Step 2 awaits approval.

## Completion boundary and known environment
Readers enter through existing Platform Docs links or a direct document URL, read the instructions, and follow supported document/section links. Operators publish the complete package, regenerate static documentation and verify the same result; stale pages require regeneration and retry, with rollback guidance if verification fails. Release requires rendering/security checks, packaged PHP 8.1 verification and actual browser checks of dynamic and generated delivery.

The reported entry is `http://127.0.0.1:8007/docs/reference/author_post_retraction.md`. Verify that page on desktop and mobile: tables render, numbered instructions stay together and section links reach their headings. If stale output remains, the documented regeneration action and reload must restore those results. Capture local browser evidence; local success does not claim production deployment.

## Risks and early validation
- **Dependency fit:** incompatibility or missing extensions could break deployment. Before finalizing Step 3, check candidate documentation and a representative PHP 8.1 packaging/rendering probe; reject unsuitable candidates.
- **Unsafe output/links:** parser defaults could expose executable content or unintended files. Before Step 3, confirm safe configuration and URL-policy support; plan adversarial fixtures and preserve catalog boundaries.
- **Rendering drift:** anchor changes or stale static pages could break navigation. Before Step 3, establish representative inventory fixtures and generation/recovery paths; require parity checks and deterministic anchors.

## Shared component inventory
Extend the existing `PublicMarkdownRenderer` boundary, reused by `PlatformDocsController` and `StaticArtifactBuilder` through the application. Reuse the document template and `platform_docs.css` for presentation, and `PlatformDocsCatalog` for routing/file boundaries. The Docs index links into these readers; it needs no new renderer or navigation destination.

## User flow
1. Open an existing documentation link.
2. Read formatted content and follow document or heading links.
3. After publishing changes, regenerate static pages and verify; use documented recovery if output is stale.

## Success criteria
The retraction guide renders its four tables and six-step procedure correctly; representative inventory fixtures preserve nested lists, task lists, reference links and duplicate heading anchors. Dynamic and generated output share formatting and anchor behavior. Security fixtures reject unsafe content/URLs. Desktop/mobile and keyboard checks show usable tables/code without page-wide overflow. A complete PHP 8.1 package renders without a production dependency-install step, and documented regeneration restores stale output.
