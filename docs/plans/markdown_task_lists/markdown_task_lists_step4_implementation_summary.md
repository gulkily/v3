> **Feature plan:** [Step 1](./markdown_task_lists_step1_solution_assessment.md) · [Step 2](./markdown_task_lists_step2_feature_description.md) · [Step 3](./markdown_task_lists_step3_development_plan.md) · [Step 4](./markdown_task_lists_step4_implementation_summary.md)

Debug mode: enabled, inherited from approved Step 3.

## Execution state

- Steps 1–3 approved; branch `feature/markdown-task-lists`, planning `2dc733152`. Reconciled newer origin/main (`c7796cac1`) without changing the canonical renderer; preserved todo counter note `ee0f5b173` and unrelated untracked research files.
- Study entry before implementation: `railspike-effort` / `v3/markdown_task_lists`, v1/R1/P2, baseline `682ab5b40`, Codex single exposed rater; **0.35 total reaffirmed**, prior intake/checkpoints retained. No measured labor, independent rating or accepted outcome.
- Four sequential stages. Counter and wider-column follow-ups remain separate. Zero dependencies/schema/navigation changes; no deployment, merge/push or recovery-storage disposal authorized by this step.

## Stage 1 - Canonical task rendering and boundaries

- Changes: supported unordered items recognize one leading checked/unchecked marker and emit a native disabled checkbox within a first-paragraph label. Wrapped task text remains in the label; children/later paragraphs stay outside. No generated identifiers, form names, values or handlers. Canonical inline/link rendering and whole-list recovery remain the only paths. Task item/label classes provide scoped presentation hooks for Stage 2. Invisible/empty labels conservatively retain literal markers.
- Verification — passed: **43/43** task/list/table/reader PHP cases and renderer lint/whitespace. Six registered task methods cover checked/unchecked/uppercase states, nesting, mixed siblings, wrapped labels, repeated tasks, later/ordered/escaped/code/table/malformed/empty negatives, whole-list fallback/code bytes, comment hiding, safe links/HTML/image alternatives and an actual onboarding-checklist reader. Prior literal-task expectations updated to require disabled task state while retaining all safety assertions.
- Notes: planning `2dc733152`. Source-only blocks retain original markers and no inputs; native naming/immutability, bullet alignment, contrast and actual keyboard/theme checks remain Stage 2. No full-suite/UI/release claim. Saved source metrics and substantive study entries here outside the planning commit; unrelated research remains unstaged.
