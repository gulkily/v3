> **Feature plan:** [Step 1](./markdown_dependency_removal_step1_solution_assessment.md) · [Step 2](./markdown_dependency_removal_step2_feature_description.md) · [Step 3](./markdown_dependency_removal_step3_development_plan.md) · [Step 4](./markdown_dependency_removal_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Markdown formatting work introduced eight third-party packages without a distinct dependency-consent decision. Remove their current and historical source copies while retaining a working Docs flow and the measurements needed to extend the earlier project-owned renderer later.

## User stories

- As a maintainer, I want syntax-use counts and render comparisons so that I can choose which formatting merits implementation.
- As a reader, I want existing Docs URLs to remain safe and readable after restoration so that dependency removal does not prevent access to documentation.
- As a repository owner, I want targeted code removed from controlled history and explicit dependency approval rules so that the introduction is cleaned up and does not recur silently.

## Core requirements

- Before restoration or history cleanup, test-render a fixed, revision-identified corpus of eligible Docs Markdown and preserve per-syntax/variant occurrence counts, distinct-document counts, representative locations, outcomes and failures. Distinguish prose from code examples; report zero-use, unsupported and ambiguous cases. Validate counts against representative hand-checked examples; the older matching-line inventory is supporting evidence only.
- Restore the pre-dependency project-owned renderer, adapting current integration and safety boundaries without adding syntax extensions or external components. Compare the same corpus against the saved baseline and expose formatting regressions and safe fallbacks for review.
- Deliver a rare-syntax decision table: syntax/variant, occurrences, affected documents/examples, restored behavior, importance, fallback, effort/risk and pending implement/defer/omit decision. Agree a rarity threshold before finalizing Step 3; preserve all counts so filtering can change. Low frequency never waives safety or reader-critical navigation/procedures. Decisions about extensions remain for the separate cycle.
- Remove the eight packages identified in the [findings](.././markdown_dependency_incident_findings.md), Composer bundle/generated infrastructure, runtime coupling and current instructions that would reinstall them; purge historical copies/renames across the agreed controlled repository boundary. Preserve unrelated dependencies/changes and project-authored incident and metric records; saved reports must not embed vendor source or retain a copy of the bundle.
- Apply the proposed host-project dependency-consent policy in AGENTS/CONTRIBUTING, with a concrete full dependency inventory and explicit approval required for future additions/updates. Generic feature approval does not supply that consent; existing approvals remain valid within their scope.

## Delivery scope and shared components

Application change plus repository maintenance: measurement evidence, renderer restoration/integration, focused verification, operator guidance, host instruction policy and coordinated targeted history cleanup. Reuse `PublicMarkdownRenderer` as the shared boundary for `PlatformDocsController` dynamic readers and `StaticArtifactBuilder` generated readers; adapt/remove CommonMark-specific `DocsCodeRenderer`, `DocsTableRenderer` and `DocsHtmlRenderer`. Preserve `PlatformDocsCatalog` discovery/search/link eligibility, shared reader templates/styles, existing Docs links and the static publication/recovery flow. Update affected Docs tests/release checks and the renderer runbook. No new reader surface or database change is needed. Syntax extensions stay in the [deferred extension cycle](.././markdown_renderer_extension_step1_solution_assessment.md).

## Risks and early validation

- **Misleading metrics:** incomplete syntax detection could misdirect decisions. Before finalizing Step 3, inspect counting opportunities and sample corpus coverage; specify occurrence units, variants and manual validation, reporting uncertainty separately.
- **Restoration regressions:** earlier formatting/link behavior could make essential docs unusable. Before Step 3, compare historical/current contracts and identify required integration/safety adaptations; define source fallbacks and reader acceptance. Unresolved critical behavior blocks dependent work.
- **Incomplete or disruptive erasure:** overlooked refs/copies or rewritten IDs could retain code or disrupt collaborators. Before Step 3, inventory controlled repositories, refs, worktrees, hosts, releases/artifacts, clones and recovery copies; establish owners/access, freeze, verification and recovery boundaries. Unknown control/access blocks a claim of complete removal.
- **Scope drift:** extensions or replacement dependencies could recreate the incident. Before Step 3, distinguish restoration/safety work from new syntax and retain an explicit zero-new-dependency boundary; return for approval if that boundary changes.

## Flow, completion and release boundary

1. Maintainer runs the corpus test render and reviews saved metrics; restoration produces comparable results and the rare-syntax decision table.
2. Readers follow existing `/docs/` and direct document URLs, read supported formatting or agreed safe source fallbacks, and follow supported document/section links in dynamic and generated delivery, without requiring JavaScript. Essential navigation must remain usable; previously unsupported fragments/regressions must be identified for review rather than silently declared supported.
3. Maintainer verifies desktop/mobile and keyboard reading, safety, publication parity and failed-build retention. Readers can retry failed requests; operators can regenerate/restore the validated complete application package when publication fails.
4. After isolated validation, coordinate controlled history cleanup/publication and verify the agreed refs, objects and copies. Temporary contaminated recovery copies are disposed of after acceptance. External inaccessible copies/host retention are reported as pending, never as erased; overall cleanup remains incomplete wherever an agreed target is unverified.

Success requires reproducible validated counts and comparison evidence, a populated decision table with reviewable pending choices, working ordinary Docs entry/recovery paths, zero targeted runtime components or new external dependencies, applied instruction policy, and verified historical removal for every agreed controlled target. Production activation and rewritten-history publication require their concrete coordination/authorization boundary in Step 3; no such action is authorized by this Step 2 draft.

## Request status

Option C has explicit Step 1 approval. Step 2 is explicitly approved; Step 3 is being drafted. Extension Step 1 is drafted and deferred. No test renders, restoration, policy edits, commits or history changes have run. Later planning must resolve the rarity threshold and controlled cleanup inventory before finalizing Step 3.
