> **Feature plan:** [Step 1](./fdp_to_railspike_step1_solution_assessment.md) · [Step 2](./fdp_to_railspike_step2_feature_description.md) · [Step 3](./fdp_to_railspike_step3_development_plan.md) · [Step 4](./fdp_to_railspike_step4_implementation_summary.md)

Debug mode: enabled

## Execution Context
- User enabled FDP debug mode with Step 3 approval; the latest explicit request supplies state without changing approved planning content.
- Branch: `feature/fdp-to-railspike`; planning commit: `7bfd0112`.
- FDP had been updated before implementation. Its current organization allowance was applied to the planning commit; approved substantive content remains unchanged.

## Stage 1 - Vendored workflow and transition
- Changes: imported the full pinned Railspike tree, added project subtree/recovery guidance, updated active navigation and plan-index policy, labeled FDP entrypoints as frozen, and disabled legacy updates pending Stage 2.
- Verification: upstream tree equality and ordinary clone availability passed; shell syntax and whitespace checks passed. `php tests/run.php FdpSyncCommandTest PlatformDocsCatalogTest PlatformDocsPageTest PlatformDocsStaticTest`: 16/16 passed, including dynamic/static front-controller access to Railspike and legacy entrypoints.
- Notes: import helper commit precedes this stage completion commit. An initial new test incorrectly expected an absolute link; corrected it to the valid relative link emitted by the existing renderer. No runtime schema change or production deployment.
