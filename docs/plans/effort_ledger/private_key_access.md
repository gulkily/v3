# Cycle v3/private_key_access

| Stable cycle ID | Status | Scope / reference / protocol | Projected total | Canonical checkpoints | Parent / acceptance |
| --- | --- | --- | --- | --- | --- |
| `v3/private_key_access` | Step 4 in progress: Stage 1 committed | v2 / R1 / P2 | 0.95 (intake 0.80; Step 1 0.80; Revision 1 0.90; Step 2 0.90; Step 3 0.95) | [Step 1](../private_key_access/private_key_access_step1_solution_assessment.md#study-snapshots), [Step 2](../private_key_access/private_key_access_step2_feature_description.md#study-snapshots), [Step 3](../private_key_access/private_key_access_step3_development_plan.md#study-checkpoint), [Step 4](../private_key_access/private_key_access_step4_implementation_summary.md#step-4-entry) | New cycle; no parent; prerequisite for `v3/private_group_chats` |

2026-11-24T17:10:00Z — v3/private_key_access Step 1 handoff (standing P2 enrollment, no parent): [assessment/checkpoint](../private_key_access/private_key_access_step1_solution_assessment.md#study-snapshots). Scope v1, recommended Option A (private access worked out from approval receipts plus a signed confirm record; existing failing keys downgraded). Starting revision `2d7805c8`, R1/P2; R1 hashes not re-verified. Pristine intake missing; intake and projected total **0.80**; options A 0.80, B 0.90, C 0.50, D 0.05, non-additive. Single exposed Claude rater. Await Approved Step 1.

2026-11-24T17:20:00Z — v3/private_key_access Step 1 Revision 1: maintainer asked how an owner learns a key is waiting; added the device line, short code, QR confirm link, Confirm/Not me card and operator path. Scope v2; projected total 0.80 → **0.90** (changed scope). Same R1/P2, single exposed Claude rater. Await Approved Step 1.

2026-11-24T17:35:00Z — v3/private_key_access Step 2 handoff: Step 1 (Option A with Revision 1) approved. [Description/checkpoint](../private_key_access/private_key_access_step2_feature_description.md#study-snapshots). Projected total **0.90** reaffirmed, scope v2. Same R1/P2, single exposed Claude rater. Await Approved Step 2.

2026-11-24T17:55:00Z — v3/private_key_access Step 3 handoff: Step 2 approved. [Plan/checkpoint](../private_key_access/private_key_access_step3_development_plan.md#study-checkpoint). Eight stages. Projected total 0.90 → **0.95** (new understanding, same scope v2: report command, confirm route and QR landing). Stage work 0.15/0.15/0.15/0.10/0.12/0.08/0.05/0.05, non-additive. Same R1/P2, single exposed Claude rater. Await Approved Step 3.

2026-11-24T17:40:00Z — v3/private_key_access Step 4 entry: Step 3 approved; branch `feat/private-key-access` from `2d7805c8`, planning `14c370810`. [Summary](../private_key_access/private_key_access_step4_implementation_summary.md#step-4-entry). Projection **0.95** reaffirmed; stage originals 0.15/0.15/0.15/0.10/0.12/0.08/0.05/0.05, non-additive. Stage 1 retrospective 0.15.
