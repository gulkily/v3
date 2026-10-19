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
