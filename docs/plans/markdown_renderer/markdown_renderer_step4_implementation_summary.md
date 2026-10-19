> **Feature plan:** [Step 1](./markdown_renderer_step1_solution_assessment.md) · [Step 2](./markdown_renderer_step2_feature_description.md) · [Step 3](./markdown_renderer_step3_development_plan.md) · [Step 4](./markdown_renderer_step4_implementation_summary.md)

Debug mode: enabled

## Execution
- Branch: feature/markdown-renderer. Planning commit: fbdafc52.
- Approved Step 3 received 2026-10-19. Five sequential stages; no pending enhancements.
- Production deployment is outside scope. No read-model schema or navigation changes.

## Stage 1 - Packaged parser and core formatting
- Changes: tracked isolated CommonMark 2.10.4 production bundle, exact manifest/lock and upstream notices; safe core/GFM conversion, bounded parsing, deterministic document-scoped heading IDs. Existing application autoloader remains separate.
- Verification: PHP 8.1.2 copied-package render without Composer passed; Composer platform requirements and advisory check passed. Focused Docs suite: 14 passed. Four actual guide tables and one six-item ordered procedure verified; nested lists, disabled tasks, references, emphasis, duplicate IDs and HTML/unsafe-link fixtures passed. git diff --check passed.
- Notes: heading expectations updated to include IDs. Link destination policy follows in Stage 2. Planning evidence/inventory remain outside the planning commit as required.

## Stage 2 - Document-aware safe links
- Changes: controller supplies source/root context; catalog resolves relative Markdown targets to public routes after realpath/size validation. One AST policy covers inline/reference/autolinks. Unsupported sources and images retain labels as plain text.
- Verification: 16 focused Docs tests passed; relative parent links, CLI fragment, references, HTTP autolinks, local fragments, encoded traversal, repeated encoding, protocol-relative/unsafe schemes, missing sources and symlink escape covered. git diff --check passed.
- Notes: Stage 1 commit 0e13f9db. Local links support ordinary parent paths inside docs; encoded structural path characters are rejected. Image embedding stays disabled; readable alternatives are completed in Stage 3.

## Stage 3 - Readable special sources
- Changes: front matter preserved in a collapsed, escaped metadata block; code languages have captions, Mermaid remains readable source; standalone HTML logo uses escaped alternative text and no remote request. HTML comments suppressed; other raw markup escaped.
- Verification: actual Marp deck, all five private-message Mermaid diagrams, Railspike logo and reference-heavy Thywill review exercised. Metadata/code/image hostile content remains escaped; source languages retained. Focused Docs suite: 17 passed; git diff --check passed.
- Notes: Stage 2 commit 7d14cbe2. No diagram, slideshow, CSS or arbitrary HTML execution added.

### Stage 3 verification correction
- Correction to the preceding verification entry: commit 5247c7e2 was made after an overlooked test failure (16 passed, 1 failed), not 17 passes. No Stage 4 work began before correction.
- GFM installs its raw-HTML renderer at priority 50; the explicit escaped fallback now has priority 100. Rerun: 17 focused Docs tests passed, including actual metadata, comments, logo and hostile source. git diff --check passed. This corrective commit completes Stage 3.

## Stage 4 - Mobile and keyboard reading
- Changes: scoped scrollable tables, focusable named table/code regions, preserved whitespace, theme-aware borders/focus indicators. Captions and pre blocks use separate lines so the shared template indenter preserves code bytes. Copy audit updated with only necessary fallback labels.
- Verification: 18 focused Docs tests passed; actual template code bytes match parser output. Eight Chromium scenarios passed at 1280px/390px with JavaScript disabled, default/Word97/Chicago/Midnight CSS, keyboard Tab/focus/ArrowRight scrolling, no page-wide overflow and actual cross-document CLI-fragment navigation. Theme sheets applied directly with preference scripts disabled. Dynamic browser measurements are saved beside this summary; screenshots in /tmp/markdown-renderer-browser were visually inspected. git diff --check passed.
- Notes: Stage 3 corrective commit 4474af35. Browser setup retries corrected style injection under disabled JavaScript; they did not change application behavior. No navigation changes, so navigation-icon remapping is inapplicable.

## Stage 5 - Publication, recovery and acceptance
- Changes: lazy runtime failures become a controlled Docs-only HTTP 503 with retry guidance and private diagnostics; static Docs errors abort candidate publication. Added an isolated copied-application release test and packaging/regeneration/rollback runbook, linked from production deployment. Preserved table code tokens after mobile visual review. Corrected dflydev's notice classification to MIT (upstream license was retained throughout); repaired two remaining moved-plan links and retained original inventory/evaluation evidence.
- Verification — passed: 18 focused Docs tests; 8 dynamic and 8 generated Chromium scenarios at desktop/390px, including normal Tools → Platform Docs entry, no-JavaScript reading, actual four tables/six steps/emphasis, unavailable sources, keyboard table/code scrolling and CLI fragment. Dynamic/generated document bodies match exactly. Code bytes survive template rendering. PHP 8.1.2 copied-package operation needs no Composer; missing autoloader and missing transitive class both return 503, with Docs index still available.
- Verification — passed: real full build followed by shared-only stale-fixture refresh/reload; failed refresh retains active pointer and readable page; restoring the complete dependency package and regenerating recovers both delivery modes. Final release fixture and screenshots: /tmp/markdown-release-uu_r0ebp. Dynamic screenshots: /tmp/markdown-renderer-browser. Durable measurements: [dynamic](./markdown_renderer_dynamic_browser_evidence.json), [static](./markdown_renderer_static_browser_evidence.json), [release/recovery](./markdown_renderer_release_evidence.json).
- Verification — failed, reproduced baseline: full suite ran once, 1,366 tests, 1,359 passed, 7 failed. All seven failures independently reproduced in a detached untouched main checkout at 3726c1e7; exact failure comparisons match after checkout-path normalization. They concern approval/profile copy, static progress counts, SQLite query UI copy, read-model timing expectations and feature-flag evidence. [Failure evidence](./markdown_renderer_baseline_failures.json). No new failures; this is not an unqualified full-suite pass.
- Verification — passed: git diff --check; plan navigation/runbook paths; locked package metadata and retained license notices reviewed. Related inventory/evaluation evidence is committed here after its exclusion from the initial planning commit.
- Inapplicable: production deployment/activation (outside scope), schema migration (none), navigation-icon changes (no destination added or renamed). No claim of production acceptance.
- Notes: Stage 4 commit 6e7f5486. Docs Discovery drafts remain separate and untouched. No deferred enhancements or additional approval requests.

## Completion
All five approved stages delivered and verified in the local target environment. The reported URL is readable dynamically; the isolated generated release demonstrates regeneration/retry and rollback recovery. Browser preference scripts were disabled; theme CSS itself was exercised directly. The seven reproduced baseline failures remain outside this renderer cycle. Production deployment remains an operator action under the runbook.
