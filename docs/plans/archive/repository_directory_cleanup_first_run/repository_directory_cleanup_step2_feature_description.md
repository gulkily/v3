> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Problem
Loose specifications, product copy, and planning notes obscure the root's essential project files. Maintainers need predictable homes for this material and working navigation after relocation.

## User stories
- As a developer, I want a focused project root so that I can identify startup, configuration, and contribution entry points quickly.
- As a maintainer, I want notes and specifications grouped by purpose so that I can find them without losing content or references.

## Core requirements
- Relocate all seven loose root documents: `docs_page_changes_revised.txt`, `improve_messaging.txt`, `qdb_todo.txt`, `related_feature_requests.txt`, `sfenc.md`, `todo.txt`, and `zenmemes_rules.txt`; preserve filenames and substantive contents.
- Reuse existing documentation categories; limit second-level changes to placing these documents and making their destinations discoverable. No unrelated directory reorganization.
- Repair active repository links to moved files and relative links inside them; preserve historical requests, approved plans, and illustrative path strings as history/examples rather than rewriting their meaning.
- Retain essential root files, application entry points, package configuration, and the debug marker; preserve application structure, vendored workflows, and local/generated data.

## Delivery scope
**Work type: documentation-only internal maintenance.** Allowed scope: the seven source documents above and their destinations under `docs/specs/`, `docs/product/`, or `docs/plans/`; `README.md`; affected host-project documentation under `docs/architecture/`, `docs/examples/`, `docs/research/`, `docs/specs/`, `docs/runbooks/`, `docs/product/`, and `docs/reference/`; documentation indexes and this feature's Railspike artifacts under `docs/plans/`. Reference edits are limited to relocation and discovery. Audience: developers and maintainers. Application/runtime, tests, CI, deployment, generated artifacts, vendored workflows, content consolidation, and content deletion are outside scope. Step 3 must fix exact destinations before implementation; any required non-documentation change requires renewed scope approval.

## Completion boundary
Starting at the README, a maintainer can discover and open every relocated document. Completion requires content-preserving moves, repaired active references, and scope/link verification; incorrect placement is corrected by reverting or revising the affected move and its links before handoff.

## Risks
- **Broken navigation:** readers lose access. Early evidence identifies README links, the key-backup reference, and a product-priorities citation; require a complete reference inventory in Step 3 before moves.
- **Misclassification or data loss:** useful notes become hard to find or disappear. Preserve complete contents and filenames; Step 3 must give each document one justified destination without inferring obsolescence.
- **Scope expansion:** second-level cleanup becomes a broad refactor. Bound it to the seven documents and supporting navigation; stop for renewed approval if runtime dependencies or unrelated moves emerge.

## Shared component inventory
Reuse the root README, existing documentation categories, `docs/reference/key_backups.md`, and `docs/product/v3_feature_priorities.md` as canonical discovery/reference surfaces. No UI/API data-rendering surface is affected, and no new application component is needed.

## Simple user flow
1. Open the project root and README.
2. Follow documentation navigation to the appropriate category and document.
3. Follow its references successfully while using existing project commands unchanged.

## Success criteria
Seven loose documents leave the root; all seven remain discoverable with substantive content preserved; zero broken active links caused by the moves; zero changes outside the allowed scope. No application behavior or command-path changes.
