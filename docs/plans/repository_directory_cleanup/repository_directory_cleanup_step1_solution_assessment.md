> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
As developer and maintainer, I want to clean up the project's top-level directory, and perhaps second-level. Please write Step 1 of docs/railspike/RAILSPIKE.md.

## Understood Intent
Make the repository easier to navigate and maintain by assessing directory organization before moving files; second-level cleanup is optional.

## Problem
The root mixes essential project files with specifications, product copy, and loose planning notes, while some second-level directories already have established organization worth preserving.

## Option A — Root-only cleanup
Relocate loose documentation and notes into the existing documentation categories, keeping conventional root files and application entry points in place.

- Pros: Small, reviewable scope; directly reduces clutter; uses existing `docs/specs/`, `docs/product/`, and `docs/plans/` categories.
- Cons: Leaves crowded second-level directories untouched; still requires checking references and distinguishing current notes from historical material.

## Option B — Root cleanup with selective second-level organization
Apply Option A and include only second-level improvements that give displaced files a clear home or resolve an obvious mixed purpose, using existing categories wherever possible.

- Pros: Improves discovery beyond the root without a broad restructure; accommodates specifications such as `sfenc.md` and notes such as `qdb_todo.txt` according to their purpose.
- Cons: Needs an explicit move inventory and stopping boundary; moving `sfenc.md` or `zenmemes_rules.txt` requires repairing known documentation links.

## Option C — Broad directory redesign
Reorganize root and second-level application, scripts, tests, and documentation around a new consistent taxonomy.

- Pros: Could address larger navigation problems, including the flat scripts and tests directories.
- Cons: Much larger reference and tooling impact; higher regression risk; difficult to keep within one day or eight development stages.

## Recommendation
Choose **Option B**, with root cleanup mandatory and second-level changes limited to clear, individually justified improvements. Retain conventional root metadata, `README.md`, `CONTRIBUTING.md`, `BLESSING.md`, `v3`, `autoload.php`, and package configuration; preserve the root debug marker. Treat loose notes as relocation candidates, not disposable or necessarily obsolete content. Preserve application structure, vendored workflow trees, and local/generated data such as `state/` and `node_modules/`.

This is an internal-maintenance slice: a maintainer can enter through the README, find relocated material in predictable categories, and follow working references while normal startup and development commands still work. Complete the slice with a bounded move inventory, reference repair, and proportionate verification; defer broad scripts/tests restructuring and content consolidation or deletion. Later planning should settle exact destinations and any second-level inclusions before implementation.
