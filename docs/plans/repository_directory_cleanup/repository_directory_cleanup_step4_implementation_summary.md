> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Delivery
- Branch: `chore/repository-directory-cleanup`.
- Planning commit: `6af9d3ad`; approved plans and existing log organized before implementation.
- Scope: documentation-only internal maintenance; runtime, UI, deployment, migration, and release checks are not applicable because executable files and behavior are unchanged.

## Stage 1 - Specification and product copy
- Changes: Moved `sfenc.md` to `docs/specs/` and `zenmemes_rules.txt` to `docs/product/`; repaired README and key-backup links and updated the README section heading.
- Verification: Python baseline comparisons against planning commit `6af9d3ad` confirm byte-identical contents for both documents; changed local links and all four plan-navigation bars resolve. `git diff --check` and exact changed-file scope checks pass.
- Notes: Destinations were absent before moving; all seven approved sources were present. No conflicts or application dependencies found in the reference inventory.
