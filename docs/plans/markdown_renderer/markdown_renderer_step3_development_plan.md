> **Feature plan:** [Step 1](./markdown_renderer_step1_solution_assessment.md) · [Step 2](./markdown_renderer_step2_feature_description.md) · [Step 3](./markdown_renderer_step3_development_plan.md) · [Step 4](./markdown_renderer_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Existing Docs links/direct URLs deliver readable documents and working section/document links, without JavaScript, dynamically and statically. Replay the reported retraction-page failures at `http://127.0.0.1:8007/docs/reference/author_post_retraction.md` on desktop/mobile; verify tables, six-step list, emphasis, CLI fragment and unavailable source handling.
- Release requires focused security/parity checks, keyboard/mobile browser evidence, a complete PHP 8.1 deployment copy without Composer, and demonstrated stale-page regeneration/retry plus rollback guidance. Production deployment is outside this execution; record that boundary. Docs Discovery stays set aside; no schema/navigation changes.

## Key Risks
- **High risk:** unsafe content or file exposure. Explicit parser settings plus document-context policy; adversarial tests precede acceptance.
- **High risk:** stale static output or incomplete packages. Preserve atomic publishing; verify regeneration, failed-build retention and complete-package rollback.
- Dependency fit validated before planning: [evaluation](../markdown_renderer_parser_evaluation.md) selects CommonMark 2.10.4; PHP 8.1 install/platform checks, representative formatting and clean lock reconstruction passed. Application/browser checks remain required.

## Stage 1
- Goal: deliver core formatting through existing document URLs.
- Dependencies: approved Step 3; evaluated parser.
- Expected changes: tracked isolated production bundle with manifest/lock/notices; replace handwritten parser with safe core/GFM/heading configuration, limits and document-scoped anchors. Keep `render(string $markdown): string` compatibility initially.
- Verification approach: copied PHP 8.1 package without Composer; actual guide tables/list, duplicate anchors, disabled tasks, raw HTML and unsafe URL checks.
- Risks or open questions: package drift; validate lock/notices and minimal probe before wiring; retain pinned bundle and existing application autoloading.
- Canonical components/API contracts touched: `PublicMarkdownRenderer`, isolated dependency autoload.

## Stage 2
- Goal: readers follow supported links safely.
- Dependencies: Stage 1.
- Expected changes: optional document context on `render`, supplied by controller; normalize allowed document links/fragments against catalog boundaries. Unsupported sources/assets become labeled plain text; no arbitrary file route.
- Verification approach: relative/reference/autolinks, encoded traversal, symlinks, unsafe/protocol-relative URLs, cross-document CLI fragment and static parity fixtures.
- Risks or open questions: exposure or dead links; adversarial fixtures first; validate resolved targets and preserve labels.
- Canonical components/API contracts touched: renderer policy, `PlatformDocsController`, `PlatformDocsCatalog` resolver.

## Stage 3
- Goal: unusual inventoried documents remain readable.
- Dependencies: Stage 2.
- Expected changes: readable front-matter and Mermaid source fallbacks, language labels, safe logo alternative; no embedded CSS/HTML execution.
- Verification approach: actual Marp, Mermaid, logo and reference-heavy inventory examples; escaped-content checks.
- Risks or open questions: source misclassification; probe real examples first; preserve source in escaped blocks rather than silently discard it.
- Canonical components/API contracts touched: shared renderer and document template.

## Stage 4
- Goal: mobile and keyboard readers can use tables/code.
- Dependencies: Stage 3.
- Expected changes: scoped table/code overflow, preserved command tokens, accessible scroll regions and minimal fallback text; record copy cleanup.
- Verification approach: desktop and 390px browser checks, keyboard scrolling/focus, long commands, existing themes, JavaScript disabled.
- Risks or open questions: overflow/focus regressions; browser checks before acceptance; scope styles to Docs.
- Canonical components/API contracts touched: document template, `platform_docs.css`, UI copy checklist.

## Stage 5
- Goal: complete publication, recovery and reported-page acceptance.
- Dependencies: Stage 4.
- Expected changes: controlled missing-bundle docs error; packaging/update/regeneration/rollback guidance; focused integration coverage and acceptance evidence.
- Verification approach: replay every Completion Contract failure locally; compare dynamic/generated output; use `./v3 build-static --shared-only` on isolated stale fixtures (full build if no active release), verify failed builds preserve active output, then regenerate/retry; run focused Docs tests and full suite once, classify known baseline failures.
- Risks or open questions: environment mismatch or partial deployment; verify target URL and copied complete package; restore prior complete package and regenerate for rollback.
- Canonical components/API contracts touched: controller failure path, existing static builder/publisher and CLI, Docs tests/runbook.

Each stage targets at most one hour of authored work, excluding mechanical dependency files; split before implementation if larger. Planning files remain uncommitted until Step 3 approval.
