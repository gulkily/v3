> **Feature plan:** [Step 1](./markdown_dependency_removal_step1_solution_assessment.md) · [Step 2](./markdown_dependency_removal_step2_feature_description.md) · [Step 3](./markdown_dependency_removal_step3_development_plan.md) · [Step 4](./markdown_dependency_removal_step4_implementation_summary.md)

Debug mode: enabled

## Execution state

- Approved Step 3 received; Option C and five-document rarity threshold accepted. Extension cycle stays deferred with its own pending approvals.
- Branch: `feature/markdown-dependency-removal`. Planning commit: `c3f3653a`.
- External cleanup owner, copies, freeze and hosting retention remain unresolved. Stages 4–5 cannot claim complete removal or publish shared history without these prerequisites. No destructive cleanup is authorized by silence.
- Initial planning commit contains only approved plans, existing debug log and plan-index organization. Incident findings and deferred extension artifacts remain uncommitted until their applicable authorized stage.

## Stage 1 - Freeze and measure the current reading flow

- Changes: maintainer-only PHP render/reference-AST probe and dependency-free Python source-variant counter; frozen manifest and baseline JSON plus human-readable count/method report. 1,312 catalog-eligible tracked Markdown documents at planning revision; per-file hashes, independent AST totals, source candidates/locations, render tag counts/hashes and error fields. No source/vendor/HTML dumps in evidence.
- Verification — passed: all 1,312 reference renders (zero errors); `php -l scripts/markdown_render_probe.php`; `python3 -B tests/markdown_metrics_test.py` (hand-counted mixed syntax, nested overlap, repeated occurrences, code exclusion, metadata, zero use and locations); `git diff --check`.
- Notes: counts label heuristic/ambiguous candidates explicitly; reference AST counts provide independent semantic totals. They are not a complete syntax-conformance guarantee. Runtime/UI/release checks are pending later stages, not established by this probe. Stage commit: `41a958fd`.

## Stage 2 - Restore and compare the dependency-free reader

- Changes: restored parsing from project-owned `0e13f9db^`, preserving current three-argument render/error contract, catalog URL boundary, heading navigation, metadata/image safety, code captions and keyboard regions. Source-number preservation prevents repeated step 1 across split procedural lists; pipe tables use escaped scrollable source rather than implementing tables. Removed 425 bundled files and three CommonMark adapter classes. No replacement dependency or new syntax extension.
- Changes: frozen-corpus restored report/comparison and 17-row rare-syntax table; all extension implement/defer/omit choices remain pending. Source counts and independent semantic counts remain separately labeled.
- Verification — passed: all 1,312 frozen documents rendered without errors and with matching source hashes; 26 page/catalog tests; two static publication/on-demand tests including all inventory readers, browse parity and stale-reader deletion; metrics fixture; PHP lint; `git diff --check`. Code bytes, safe URL/symlink policy, source alternatives and critical guide numbering/CLI fragment have focused assertions.
- Notes: initial static run detected concurrent report changes and aborted as designed; stable-document rerun passed. Old reference-format assertions were replaced with explicit approved fallback assertions, retaining security checks. Broader browser/copied-package/full-suite verification remains Stage 3. Deferred limitations include nested/loose lists, tasks, reference/autolinks, underscore emphasis, strikethrough and tilde fences; they remain readable source. Stage 2 commit recorded in the next stage update.
