> **Feature plan:** [Step 1](./repository_archive_readme_step1_solution_assessment.md) · [Step 2](./repository_archive_readme_step2_feature_description.md) · [Step 3](./repository_archive_readme_step3_development_plan.md) · [Step 4](./repository_archive_readme_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/repository_archive_readme`; [ledger](../effort_estimation_trial.md). P2 / R1; scope v2.

Extended estimates: disabled (inherited).

**Cycle estimate: 0.25**

## Approval and entry checkpoint

Step 3 explicitly approved on 2026-10-25 after plan delivery. Planning commit `832e6e140` precedes implementation. Feature baseline `2a6dfba08` includes current remote history-free export behavior; original cycle baseline `99a0def2af1f0b19b4b5cc284c1eac920da9464d` retained. Step 4 entry 2026-10-25: **0.25 reaffirmed**, same scope and R1; hashes verified at Step 3, Codex single exposed rater. No new requirements, dependencies or schema changes. Implementation occurs in an isolated worktree; unrelated local registration work is excluded.

*— Codex · 2026-10-25 UTC*

## Stage 1 — Deliver the guide in both formats

- Changes: shared recipient guide, snapshot-only preparation helper, collision-safe byte-preserving notes and one integration point before final snapshot sizing/packaging. Existing guide preparation is repeatable; fixture README is unchanged.
- Verification: `php tests/run.php RepositoryContentExportTest` passed 3/3, including real tar/ZIP extraction, missing/current/custom README, file/directory naming collisions, source non-mutation, legacy signed bytes, image-pair restoration and manifest identity. PHP lint and `git diff --check` passed.
- Notes: preservation errors are actionable and propagate through the existing backup failure contract. No dependencies or schema changes. Stage 2 adds explicit ordinary-route failure/retry and documented CLI evidence.

*— Codex · 2026-10-25 UTC*
