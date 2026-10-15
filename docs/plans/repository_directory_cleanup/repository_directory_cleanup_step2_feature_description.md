> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Loose notes and reference documents obscure the project's structure on GitHub. Readers need a tidy root and predictable documentation locations without losing content or breaking references.

## User stories

- As a visitor, I want a focused repository root so that I can identify the project and its entry points quickly.
- As a contributor, I want related documents grouped and linked so that I can find existing guidance and proposals.

## Core requirements

- Relocate the six root candidates (excluding `todo.txt` per the approval) from Step 1 into existing purpose-based documentation categories, preserving filenames and substantive content.
- Group the loose `docs/feature-flags-redesign-spec.md` and `docs/feature-flags-redesign.html` together under an appropriate documentation category; preserve their relationship.
- Repair affected document links, relative references, and root README discovery links; distinguish live references from historical examples.
- Preserve root metadata and application entry points, documented commands, and existing runtime behavior; delete no project material.

## Delivery scope

**Work type: documentation-only internal maintenance.** Allowed moves: `qdb_todo.txt`, `improve_messaging.txt`, `docs_page_changes_revised.txt`, `related_feature_requests.txt`, `sfenc.md`, `zenmemes_rules.txt`, and the two feature-flags documents above. Destinations are limited to `docs/plans/backlog/`, `docs/product/`, and `docs/specs/`; Step 3 must specify the exact mapping. The static HTML mockup is a documentation artifact, not a served application asset.

Allowed edits: `README.md`, affected host-project documentation under `docs/` for mechanical reference repairs, this feature's planning artifacts/debug log, and `docs/plans/README.md` for required plan organization. Any index additions must directly support this cleanup. Vendored `docs/railspike/` and `docs/fdp/`, unrelated plan organization, and substantive document rewrites are excluded. Application/runtime, tests, CI, deployment, generated artifacts, configuration, and ignored local directories may not be changed.

## Completion boundary

Finish when a GitHub visitor can navigate from the root README to relocated material, all eight moves preserve content apart from necessary reference repairs, and affected live document links resolve. Correct errors through scoped reference fixes or reverse the affected move; any required application change returns to Step 2/3 for approval. Handoff requires scope, content, and link verification plus the Railspike implementation summary and required commits.

## Risks

- **Broken references:** readers lose access; search tracked references before Step 3, distinguish literal examples, and include all live-link repairs in the approved plan.
- **Behavior or exposure changes:** moving Markdown beneath `docs/` can make it readable through the existing document viewer; review moved material and the viewer's path policy before Step 3, and resolve unsuitable destinations before approval.
- **Misclassification or collisions:** documents become harder to find or overwrite material; inspect destination contents before Step 3 and use explicit mappings with no overwrites.

## Shared component inventory

Reuse the root README and existing documentation categories/indexes for discovery. The in-app `PlatformDocsCatalog` and document viewer already serve documentation; preserve their catalog, policy, and runtime implementation. No new UI or API is needed.

## Simple user flow

1. Open the GitHub repository and read the focused root listing.
2. Follow README links or browse the existing documentation categories.
3. Open the relocated document and follow its repaired references.

## Success criteria

Eight approved files relocated; six fewer tracked root files; no remaining loose feature-flags pair in `docs/`; no lost substantive content, destination collisions, or broken affected live links; changes confined to the allowed documentation scope.
