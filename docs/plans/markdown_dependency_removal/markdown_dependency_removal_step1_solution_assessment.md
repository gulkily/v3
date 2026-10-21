> **Feature plan:** [Step 1](./markdown_dependency_removal_step1_solution_assessment.md) · [Step 2](./markdown_dependency_removal_step2_feature_description.md) · [Step 3](./markdown_dependency_removal_step3_development_plan.md) · [Step 4](./markdown_dependency_removal_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

We recently introduced a bunch of third-party components without us being aware of it while trying out Markdown formatting. This should not have happened. Please investigate what instructions allowed this to happen and how we can patch them to make sure it does not happen next time. Save these findings to a file. Then write Step 1 of a plan to remove these third-party components from the repo completely (including history).

## Understood Intent

[Findings and proposed policy patches](.././markdown_dependency_incident_findings.md) are saved separately. Remove the eight Markdown packages, their vendored infrastructure and historical source copies; preserve unrelated dependencies and project-authored audit evidence. This is planning only, not authorization to delete files, rewrite/publish history or advance beyond Step 1.

## Problem

The bundled CommonMark tree is embedded in both the current Docs runtime and Git history, so removal must restore a working dependency-free reading flow and eliminate retained source copies within an explicitly controlled repository boundary.

## Option A — Replace the renderer, then purge targeted history

Use project-owned rendering with an explicit supported syntax contract, retain safe document/link behavior, then remove the Markdown bundle from all controlled historical refs and coordinate repository replacement/cleanup.

- Pros: preserves later unrelated changes and existing Docs entry points; gives both runtime and history removal verifiable outcomes.
- Cons: project owns parsing/security maintenance; some formatting may require agreed fallback behavior; rewritten commit IDs require coordinated clone/host cleanup.

## Option B — Restore the earlier renderer, then purge targeted history

Restore the pre-dependency renderer and reconcile later Docs integration before purging the same historical component inventory.

- Pros: smaller replacement surface; reuses existing project-owned code without importing another library.
- Cons: restores known formatting limitations; later Docs discovery/publication changes may depend on the newer contract and require adaptation; history coordination remains necessary.

## Option C — Measure, restore, then extend in a separate cycle (recommended)

First test-render a fixed documentation corpus with the current renderer and record use counts for each formatting type and syntax variant. Then restore the earlier project-owned renderer as in B, retaining necessary integration and safety fixes, and purge the targeted dependencies/history. Use the saved metrics to scope later extensions in a separate Railspike cycle.

- Pros: preserves evidence before removing the reference renderer; bounds removal work; makes extension priorities and rare-syntax tradeoffs explicit.
- Cons: accepts interim formatting limitations; measurement needs validation; low counts can still represent critical instructions or links.

## Recommendation and viable slice

Choose C. Before restoration or history cleanup, save reproducible test-render evidence: corpus/revision, per-syntax occurrence counts and distinct-document counts, representative source locations, render outcomes and failures. Separate syntax variants, nested constructs and code examples; include zero-use, unsupported and ambiguous cases rather than counting only successful output. Compare the restored renderer against that baseline. The existing [inventory](.././markdown_renderer_inventory.md) is useful input, but its document/matching-line counts are not the requested render-based occurrence metrics.

Produce an infrequently-used-syntax decision table with syntax/variant, uses, affected documents/examples, restored-renderer behavior, importance, fallback, effort/risk and implement/defer/omit decision. Agree the rarity threshold during later planning; leave decisions open for user review. Frequency alone must not discard security handling, essential procedures or navigation.

This cycle owns measurement, the decision table, restoration/integration, the proposed instruction gate and full targeted dependency/history cleanup. Existing Docs entry points, safe readable source/fallbacks, dynamic/static publication and recovery must work; formatting regressions are explicitly recorded and reviewed. New syntax support belongs to [the separate extension Step 1](.././markdown_renderer_extension_step1_solution_assessment.md), which is drafted and set aside. Removal must not wait for that cycle or silently implement its extensions.

History completion still requires an agreed inventory of targeted paths/blobs and controlled refs, objects, hosts, artifacts and clones. Validate in isolation before coordinated publication; retain a recovery copy only through validation and dispose of contaminated backups within the agreed boundary. Preserve unrelated changes/dependencies and project-authored incident/metrics evidence. Uncontrolled copies and inaccessible host storage prevent a universal-erasure claim.

## Request disposition and approval

- **Request (2026-10-21):** add Option C, test-render syntax counts, a rare-syntax decision table and a separate extension Railspike Step 1, then return here.
- **Disposition:** incorporated into this assessment; extension assessment drafted and deferred. Actual measurements/table population occur in the removal implementation after its planning approvals. No counts or render results are claimed yet.
- **Approval:** user explicitly approved Step 1 (Option C). Removal proceeds to Step 2; the extension cycle remains deferred with independent pending approvals.
