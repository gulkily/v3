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
- Notes: counts label heuristic/ambiguous candidates explicitly; reference AST counts provide independent semantic totals. They are not a complete syntax-conformance guarantee. Runtime/UI/release checks are pending later stages, not established by this probe. Stage commit recorded in the next stage update.
