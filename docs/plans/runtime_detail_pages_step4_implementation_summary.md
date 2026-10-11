> **Feature plan:** [Step 1](./runtime_detail_pages_step1_solution_assessment.md) · [Step 2](./runtime_detail_pages_step2_feature_description.md) · [Step 3](./runtime_detail_pages_step3_development_plan.md) · [Step 4](./runtime_detail_pages_step4_implementation_summary.md)

## Stage 1 - Runtime default and static opt-in

- Changes: static detail pages default to disabled; existing flag description explains runtime rendering, opt-in, and full rebuild. Static-output tests explicitly enable details and restore the prior environment. Default CLI checks assert skipped batches, absent detail HTML, and retained offline snapshot; flag checks cover instance opt-in and environment precedence.
- Verification: focused evaluator/QDB/CLI/layout/archive/Unicode tests via `php tests/run.php`: 47 run, 45 passed; layout also passed independently. `git diff --check` passed.
- Notes: both failures reproduced on the unchanged planning-commit baseline: QDB welcome-copy assertion expects “⚑ Flag something that”; archive test fails because this PHP installation lacks the zip extension. Neither is introduced by the rendering default. No schema/signature changes or deployment performed.
