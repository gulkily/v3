# Markdown work checklist

Updated 2026-10-23. Ordered by current relevance. Checked means the named work is complete within its stated boundary; it does not imply deployment or full Markdown compatibility. Optional syntax entries are decisions to implement/defer/omit, not approved implementation requirements. Keep historical evidence intact and update this checklist when work changes.

## 1. Current task-checkbox acceptance — highest priority

- [x] Fix barely distinguishable checked/unchecked appearance with hollow outlines versus solid fills and contrasting ticks; retain native disabled checkbox semantics, accessible task names and working links. Initial native-paint acceptance failed user UAT and was corrected.
- [x] Verify corrected appearance in Chromium across all 16 registered themes, phone/desktop, Auto light/dark, LCD palettes and forced colors: 46 dynamic + 46 generated cases; inspect all state-pair screenshots. Physical-device/AT acceptance remains below.
- [x] Complete user UAT of the corrected checkbox slice: user accepted it and authorized merge on 2026-10-23.
- [x] Merge the accepted task-checkbox slice into main. Production deployment/refresh remains a separate item below.
- [x] Implement checked/unchecked display for supported unordered task items, including uppercase X, wrapped task labels and nested items.
- [x] Keep task display immutable: disabled, skipped by Tab, no source writes, submission or editing controls.
- [x] Preserve literal markers in ordered items, code/table examples, malformed/empty labels and unsupported whole-list source blocks.
- [x] Verify task names/links, source downloads, dynamic/static parity and publication failure/recovery. Initial corpus: 1,109 displayed controls and 73 retained source candidates; corrected appearance has separate verification below.

Evidence: [task implementation](markdown_task_lists/markdown_task_lists_step4_implementation_summary.md), [task corpus/acceptance](markdown_task_lists/task_render_acceptance.md), [contrast correction evidence](markdown_task_lists/task_contrast_verification.json).

## 2. Explicitly requested usability follow-ups

- [ ] Implement an in-page Markdown checkbox counter showing checked/total; decide whether the count covers rendered controls or other source markers during its own planning.
- [ ] Relax the narrow Docs column on wider screens to reduce avoidable table/deep-list scrolling; retain phone containment and keyboard scrolling when content really is wide.
- [x] Record the counter in `todo.txt` and the wider-column request in the renderer handoff.

These are separate future slices, not part of the current checkbox correction.

## 3. Core renderer work already delivered

- [x] Restore the project-owned dependency-free renderer; remove the eight-package Markdown dependency bundle and its runtime imports.
- [x] Preserve headings, duplicate-safe heading IDs/fragments, ordinary emphasis, inline code, safe inline links, quotes and thematic breaks.
- [x] Render standalone pipe tables: header/body cells, alignment, optional outer pipes, empty cells, escaped pipes and supported inline-code pipes.
- [x] Provide keyboard-accessible table scrolling and whole-block source fallback for uncertain tables.
- [x] Preserve ordered/unordered nesting, mixed child types and ordinary continuation/loose paragraphs within the supported indentation rules, through eight levels.
- [x] Preserve explicit source step numbers, including starts, repeats, restarts and gaps.
- [x] Keep unsupported/ambiguous list blocks readable as source, without consuming following standalone content.
- [x] Retain fenced example bytes, language captions, keyboard source/code regions and readable Mermaid source.
- [x] Retain collapsed escaped metadata, hidden prose comments, escaped HTML, image alternatives/no fetching and traversal/symlink/unsafe-link protections.
- [x] Deliver the same renderer through ordinary PHP Docs and full/shared static publication, with stale refresh, failure retention and restoration/retry evidence.
- [x] Freeze source metrics, classify measured tables/lists/tasks and retain per-document hashes without restoring a reference-parser dependency.
- [x] Keep Docs discovery, browsing/search, safe downloads and root-document reading integrated with the existing reader.

Evidence: [table slice](markdown_renderer_extension/markdown_renderer_extension_step4_implementation_summary.md), [list slice](markdown_list_structure/markdown_list_structure_step4_implementation_summary.md), [renderer runbook](../runbooks/markdown_renderer.md).

## 4. Optional compatibility decisions — prioritize by real document needs

- [ ] Decide reference-style links/definitions: explicit, collapsed and shortcut forms, preserving document-boundary safety.
- [ ] Decide URL autolinks: bare URLs, angle-bracket URLs and email autolinks.
- [ ] Decide underscore emphasis/strong emphasis and strikethrough.
- [ ] Decide hard line breaks from trailing spaces or backslashes.
- [ ] Decide Setext headings, grouped/nested blockquotes and other currently unsupported block forms.
- [ ] Decide tilde fences, standalone indented code and more complete inline-code delimiter/escape handling.
- [ ] Decide broader list support: embedded tables/quotes/fenced or indented code, tabs/other indentation conventions and nesting beyond the current bound.
- [ ] Decide task presentation in ordered lists; retain numbering and read-only behavior if selected.
- [ ] Decide richer table forms: tables in lists/quotes, multiline/merged cells, nested tables, uneven rows and more complex code-span boundaries.
- [ ] Decide rare or currently unused extensions such as footnotes and admonition-style alerts.
- [ ] Decide whether Mermaid/math rendering or actual image loading is wanted; each needs a separate scope/security/dependency review. Labeled source/text alternatives remain the current behavior.
- [ ] Reconcile the historical rare-syntax decision table with completed slices and record explicit implement/defer/omit decisions, including zero-use variants; do not replace historical counts with invented current measurements.

Evidence: [historical rare-syntax table](markdown_dependency_removal/rare_syntax_decisions.md), [original formatting inventory](markdown_renderer_inventory.md), [latest task-slice metrics](markdown_task_lists/render_current_summary.json). Candidate counts are heuristics, not conformance guarantees.

## 5. Verification and documentation closeout

- [x] Correct runbook verification wording to expect supported rendered tables and keyboard table/source/code regions.
- [ ] Complete physical-device and assistive-technology acceptance for the recent renderer slices; browser accessibility checks are not a substitute.
- [ ] Run/classify an appropriate current full-suite result before claiming full-suite verification; preserve previously classified unrelated baseline failures.
- [ ] Deploy the accepted renderer to the intended production instance, refresh generated Docs and verify live reading/recovery there.
- [x] Complete focused renderer/catalog/static checks, copied-package recovery and representative dynamic/static phone/theme scenarios for table, list and initial task slices; checkbox painting was corrected and locally reverified above; user UAT remains pending.
- [x] Preserve the rule requiring explicit approval for any new external dependency; none was added by these extension slices.

## 6. Original dependency/history-removal closeout — separate from renderer features

- [x] Publish the reviewed rewritten main/refs and verify a fresh independent clone contains none of the targeted paths/blobs.
- [x] Synchronize the seven inventoried clones, retained refs, eight stashes and fourteen worktree registrations while preserving local work/configuration and the publication audit.
- [ ] Review and explicitly authorize any disposal of retained old objects, non-stash reflogs, stale worktree registrations or private recovery bundles. They remain intentionally preserved.
- [ ] Account for other deployment, release/archive and external copies, plus host-retained objects/caches; current evidence does not establish universal erasure.
- [ ] Record final maintainer acceptance of the full removal outcome and its retention limits.

Evidence: [removal implementation](markdown_dependency_removal/markdown_dependency_removal_step4_implementation_summary.md), [clone/history handoff](markdown_dependency_removal/history_rewrite_clone_sync.md).

## Current policy, not unfinished implementation

Raw HTML stays escaped, arbitrary images are not fetched, Mermaid stays source, and checkboxes remain read-only. Changing these policies is not implied by an unchecked optional decision. Do not delete recovery data or modify `todo.txt` without the required explicit user authorization.
