> **Feature plan:** [Step 1](./checkout_overview_step1_solution_assessment.md) · [Step 2](./checkout_overview_step2_feature_description.md) · [Step 3](./checkout_overview_step3_development_plan.md) · [Step 4](./checkout_overview_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/checkout_overview`; [ledger](../effort_estimation_trial.md), v1/R1/P2, Codex single exposed rater. Step 3 approved; branch `feat/checkout-overview`, baseline `1edbe534d`, planning `db8ea0009`. Entry total projection reaffirmed unavailable pending exact frozen R1 feedback recovery; no score backfilled or measured effort inferred. Seven planned stages; implementation begins after this entry.

## Stage 1 - Local cached overview

- Changes: default overview reads local registry/cache before manager lock; versioned selection/generation cache keys, unchecked/role-excluded fallbacks, safe corrupted-cache handling, no signed preview or Git work in the overview read path.
- Verification: PHP syntax passed; 3 focused real-Git tests passed (offline cold view while manager lock held under 1s, corrupt cache/registry invalidation and role selection, explicit preview still fetches without changing HEAD).
- Notes: worker launch and cache population follow in later stages. No live registry/checkout mutations.
