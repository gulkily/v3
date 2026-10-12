> **Feature plan:** [Step 1](./operator_privilege_delegation_step1_solution_assessment.md) · [Step 2](./operator_privilege_delegation_step2_feature_description.md) · [Step 3](./operator_privilege_delegation_step3_development_plan.md) · [Step 4](./operator_privilege_delegation_step4_implementation_summary.md)

## Stage 1 - Canonical authority contract
- Changes: Added strict identity/operator bootstrap-reply parsing and pure graph derivation. Signed actions carry an acceptance sequence, target identity and canonical username token; revocations reference a particular grant. Accounts derive from approved profile keys and canonical root seeds. Grant history protects suspended accounts.
- Verification: `php tests/run.php OperatorGraphTest` — 4 passed, covering cascading restoration, alternate paths, disconnected cycles, duplicate/missing sequences, unauthorized actors and approved/pending account keys.
- Notes: Signature verification belongs to the repository loader in Stage 2; pure graph inputs must already be verified. Replay validates authority at each action in acceptance order, then computes current reachability. No application schema change is required: operator replies reuse indexed posts, and operator state will be derived from them.

## Stage 2 - Shared effective authority
- Changes: Added canonical-seed and signature-verified operator authority derived from indexed posts; wired statistics, feature-flag page/write checks and operator listings to it. Feature-flag routes now resolve authenticated session identities. Stale metadata/markers fail closed; incremental writes maintain non-Git content fingerprints.
- Verification: `PATH=/tmp/operator-node/node-v22.16.0-linux-x64/bin:$PATH php tests/run.php OperatorAuthorityTest OperatorGraphTest FeatureFlagsBehaviorTest VisitorStatisticsPageTest` — 8 passed; `git diff --check` passed.
- Notes: Authority is computed from the existing post projection rather than a new roles table, preserving live/rebuild parity without schema changes. A temporary Node runtime was downloaded outside the repository because Node was missing from PATH. Signed write integration and full reconstruction tests follow in later stages.
