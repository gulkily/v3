> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

I want to clean up the top-level directory for this project and possibly other second-level directories, so that the project looks neater on GitHub. Please write Step 1 of docs/railspike/RAILSPIKE.md.

## Problem

Loose project notes and reference documents clutter the GitHub root, while any wider directory cleanup must preserve discoverability and existing path dependencies.

## Option A — Root-only cleanup

Move loose root notes and specifications into the existing documentation categories, preserving their content and updating references; retain conventional project metadata and application entry points.

- Pros: Immediate visible improvement; small scope and low disruption.
- Cons: Leaves crowded second-level directories unchanged; moved documents still need reference checks.

## Option B — Root cleanup plus selective second-level organization

Apply Option A, then organize only clearly misplaced or loosely grouped files immediately inside existing directories where the benefit is concrete, with destinations and exclusions agreed during planning.

- Pros: Improves the root and selected browsing paths; uses the existing structure without imposing a new repository layout.
- Cons: Requires a bounded inventory; executable or served-file moves carry more risk than document moves.

## Option C — Broad repository restructuring

Redesign the root and second-level layout around a uniform directory convention, including regrouping scripts, tests, and other application support files.

- Pros: Greater consistency and room for future growth.
- Cons: Largest reference, command, and deployment risk; more review work than the presentation goal warrants.

## Recommendation

Choose **Option B**, with root documentation cleanup as the required outcome and second-level changes limited to a reviewed set of useful moves. Candidates include `todo.txt`, `qdb_todo.txt`, `improve_messaging.txt`, `docs_page_changes_revised.txt`, `related_feature_requests.txt`, `sfenc.md`, and `zenmemes_rules.txt`; classify each by purpose rather than placing everything in one miscellaneous folder.

- Preserve root `README.md`, `CONTRIBUTING.md`, `BLESSING.md`, `.gitignore`, `package.json`, `autoload.php`, and `v3`; existing code relies extensively on the root autoloader.
- Start second-level review with loose files in `docs/`; keep runtime directories and vendored Railspike/FDP contents outside the default move scope.
- GitHub cleanliness concerns tracked files: ignored `state/`, `node_modules/`, and `.idea/` need no local deletion for this goal.
- Treat this as bounded internal maintenance. A complete slice lets a visitor browse the cleaner root, find relocated material through updated links, and run the same documented commands; preserve content and make moves reversible through Git history.
- Steps 2–3 should settle the exact destinations and affected references before implementation; retain existing plan-organization rules and avoid unrelated content rewrites or deletions.
