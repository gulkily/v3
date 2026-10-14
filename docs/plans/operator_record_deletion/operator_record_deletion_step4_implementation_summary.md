> **Feature plan:** [Step 1](./operator_record_deletion_step1_solution_assessment.md) · [Step 2](./operator_record_deletion_step2_feature_description.md) · [Step 3](./operator_record_deletion_step3_development_plan.md) · [Step 4](./operator_record_deletion_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Durable authority evidence

- Changes: optional actor/classification headers preserve the legacy author format. Operator acceptance requires identical record bytes in a local addition commit with the retraction subject and a matching, regular-file operator seed in its sole parent revision. Git history is the local trust boundary; archive claims cannot manufacture acceptance.
- Verification: signed fixture test passed (1/1), covering uncommitted rejection, accepted authority, live seed removal, tampered history claims, and legacy parsing.
- Notes: missing/pruned history fails closed for operator actions. Preserve the canonical repository history during backup/restore; arbitrary server-side Git writes remain privileged operations. Signature enforcement is wired into readers in Stage 3.

