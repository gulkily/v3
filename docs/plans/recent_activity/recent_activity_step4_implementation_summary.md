> **Feature plan:** [Step 1](./recent_activity_step1_solution_assessment.md) · [Step 2](./recent_activity_step2_feature_description.md) · [Step 3](./recent_activity_step3_development_plan.md) · [Step 4](./recent_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/recent_activity; [ledger](../effort_estimation_trial.md#cycle-v3recent_activity)
Extended estimates: enabled
**Cycle estimate: 0.85**

Entry: 2026-10-30, Approved Step 3 received; planning commit de4ba1433 on feature/recent-activity. Scope v1/A, original baseline 3a21fab67708c07ef77712790218e12a92c61bcd, R1/P2, single exposed Codex rater. Step 3 projection 0.85 reaffirmed; earlier 0.75 and inherited 0.5 preserved. Original S1–S7 stage work: 0.10/0.18/0.15/0.10/0.15/0.15/0.05, non-additive. Shared acceptance belongs to S6. Measured labor unknown.

Request: Delete mockups rather than save them in repository. Disposition: Entire untracked mockup directory deleted before planning commit; links removed. No mockups committed. Product implementation authorized, deployment not included.

## Stage 1 - Public-contribution contract

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Reusable fixture for six recent contributions, agent/unsigned posts, hidden/retracted/bootstrap/expansion exclusions, absent/hidden root context, title/image-only entries. Existing readers allow visible replies independently of root visibility; Recent will retain those replies with neutral context, never hidden root text.
- Verification: PHP syntax and fixture insertion into actual in-memory schema passed (16 posts). Inspected ThreadRepository post visibility, ThreadRowSupport identity tag, profile-history expansion exclusion and shared attribution rendering.
- Notes: No approval/count restriction on feed membership. No changed deletion policy. No dependency changes; runtime route acceptance belongs to subsequent stages. Estimates unchanged.

*— Codex · 2026-10-30 UTC*
