> **Feature plan:** [Step 1](./retraction_simplification_step1_solution_assessment.md) · [Step 2](./retraction_simplification_step2_feature_description.md) · [Step 3](./retraction_simplification_step3_development_plan.md) · Step 4

Debug mode: enabled.  
Effort study: `railspike-effort` / `v3/retraction_simplification`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_simplification). Extended estimates: enabled.

**Cycle estimate: 1.00**

## Entry — 2026-10-25 UTC

Step 3 explicitly approved. Planning commit `fd5c6849`, branch `feature/retraction-simplification`. Scope v2/release 1, original baseline `22134259876481199aacff6f6b2cd39a811236a9`, R1/P2 and single exposed Codex rater retained. Three frozen hashes reverified at activation; independently reaffirm 1.00 before implementation. Stage estimates first recorded at activation, not historical originals: S1–S8/v1 = 0.20, 0.20, 0.30, 0.25, 0.25, 0.30, 0.20, 0.25. Scopes/drivers/uncertainties inherited from Step 3; Stage 8 owns shared verification/closeout. Exact runtime model/settings and measured labor unavailable. History purge, deployment and maintainer acceptance remain outstanding. No new dependencies authorized or planned.

*— Codex · 2026-10-25 UTC*

## Stage 1 - Durable acceptance contract

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: `AcceptedRetraction` stores one JSON record with exact signed-request/signature bytes and whitelisted conversation structure; no post prose or cleanup state. It is a local acceptance record, never self-authenticating imported authority. Existing import catalog does not admit this new JSON family; Stage 4 must validate incoming claims before allowing them.
- Verification: `php tests/run.php AcceptedRetractionTest` 2/2; target mismatch rejected, signed bytes/structure round-trip, prose metadata rejected. Existing action-time signature/operator checks remain the authority boundary; Stage 2 wires them before record creation.
- Notes: existing signed text is embedded unchanged, so migration needs no replacement signature. Read does not invoke Git. No stage estimate revision; original S1/v1 scope retained. Production/ingestion integration remains pending dependent stages.

*— Codex · 2026-10-25 UTC*
