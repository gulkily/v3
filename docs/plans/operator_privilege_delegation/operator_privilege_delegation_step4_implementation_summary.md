> **Feature plan:** [Step 1](./operator_privilege_delegation_step1_solution_assessment.md) · [Step 2](./operator_privilege_delegation_step2_feature_description.md) · [Step 3](./operator_privilege_delegation_step3_development_plan.md) · [Step 4](./operator_privilege_delegation_step4_implementation_summary.md)

## Stage 1 - Canonical authority contract
- Changes: Added strict identity/operator bootstrap-reply parsing and pure graph derivation. Signed actions carry an acceptance sequence, target identity and canonical username token; revocations reference a particular grant. Accounts derive from approved profile keys and canonical root seeds. Grant history protects suspended accounts.
- Verification: `php tests/run.php OperatorGraphTest` — 4 passed, covering cascading restoration, alternate paths, disconnected cycles, duplicate/missing sequences, unauthorized actors and approved/pending account keys.
- Notes: Signature verification belongs to the repository loader in Stage 2; pure graph inputs must already be verified. Replay validates authority at each action in acceptance order, then computes current reachability. No application schema change is required: operator replies reuse indexed posts, and operator state will be derived from them.

## Stage 2 - Shared effective authority
- Changes: Added canonical-seed and signature-verified operator authority derived from indexed posts; wired statistics, feature-flag page/write checks and operator listings to it. Feature-flag routes now resolve authenticated session identities. Stale metadata/markers fail closed; incremental writes maintain non-Git content fingerprints.
- Verification: `PATH=/tmp/operator-node/node-v22.16.0-linux-x64/bin:$PATH php tests/run.php OperatorAuthorityTest OperatorGraphTest FeatureFlagsBehaviorTest VisitorStatisticsPageTest` — 8 passed; `git diff --check` passed.
- Notes: Authority is computed from the existing post projection rather than a new roles table, preserving live/rebuild parity without schema changes. A temporary Node runtime was downloaded outside the repository because Node was missing from PATH. Signed write integration and full reconstruction tests follow in later stages.

## Stage 3 - Protected account key admission
- Changes: Enforced shared account-key eligibility in direct/prepared membership approval and invitation preparation/final acceptance; approved own-account keys and current operators remain eligible during suspension. Generic prepared posts cannot finalize privileged actions and generic posts cannot forge privileged tags. Profile approval controls respect eligibility.
- Verification: Focused graph/key-policy, signed approval and authenticated feature-flag regressions — 11 passed; `git diff --check` passed. Broad write suite initially reported four tests assuming cookie-only operator access; updated them to authenticate explicitly. Its remaining QDB vote-caption failure reproduces unchanged in a detached baseline worktree at planning commit `3a3c6b6f`.
- Notes: Pending-directory and other presentation wiring completes in Stage 7. Baseline QDB failure is unrelated to operator work and remains outside this feature's scope.

## Stage 4 - Signed grants through user pages
- Changes: Added authenticated operator preparation/finalization endpoints, signed tagged bootstrap replies with exact identity/account/sequence binding, shared classic/Forte profile and username role controls, confirmation/browser signing, and attributed activity. Operator changes refresh derived state and retire generated presentations.
- Verification: `php tests/run.php WriteApiSmokeTest::testSignedOperatorGrantFromUserPageAllowsOnwardDelegation` passed using real GPG keys/signatures, both site user entries, invalid-signature rejection, onward delegation, anonymous denial and full-rebuild parity. PHP/JavaScript syntax and `git diff --check` passed.
- Notes: Preparation captures the complete account/operator revision; finalization rechecks it under the write lock. Grants are additional account relationships rather than transfers. Revocation controls follow in Stage 5.

## Stage 5 - Previewed cascading revocation
- Changes: Added signed grant-specific revocation by any current operator, shared classic/Forte controls, affected-account confirmation, alternate-path preservation and unchanged-state finalization. Root grants are not removable through this flow; accepted action replay is rejected.
- Verification: Graph plus real-signed UI/API integration — 6 passed, including cascading preview, an intervening alternate grant causing stale-confirmation rejection, subsequent alternate-path preservation, cascading loss and replay rejection. JavaScript/PHP syntax and `git diff --check` passed.
- Notes: Revocation preserves outgoing grants as suspended relationships; restoration and refresh-failure recovery follow in Stage 6.

## Stage 6 - Restoration and persisted-write recovery
- Changes: Confirmed restoration reactivates unrevoked descendants while preserving explicit revocations; root seeding of a previously delegated account no longer invalidates prior grants. Prepared actions are retired and presentations invalidated before refresh, so committed-but-failed refreshes cannot be resubmitted. Added operator recovery runbook guidance.
- Verification: Real-signed grant/revoke/restore integration, injected refresh failure, rebuild repair, previously delegated root seeding, graph and approval-seed tests — 20 passed; `git diff --check` passed.
- Notes: Administrator recovery uses the existing `./v3 approval seed` path. Canonical history survives failures; authorization remains closed while metadata/markers indicate stale state.

## Stage 7 - Consistent account and operator presentation
- Changes: Added shared role/provenance to directories, profile API, Forte summary/detail panes and account-key views; pending approval controls reflect protected-account eligibility. Updated tool messages and Codebase State listing to include delegated operators. Active, suspended and revoked grants link to their signed records. Signature verification caches exact key/record/signature evidence within the process; account/action revision order is deterministic.
- Verification: Presentation, authority, browser cancellation/confirmation/stale-retry and signed multi-surface integration — 19 passed; follow-up browser/integration/statistics checks after provenance changes — 5 passed; PHP/JS syntax and `git diff --check` passed. Real integration confirms profile API, directories, Forte detail fragments and disabled protected-account approval controls agree with server enforcement.
- Notes: Temporary Node provisioning was repeated in the user cache after `/tmp` was cleared. Operator actions require both identity and operator tags, preserving ordinary boards named operator. Full-suite and operational handoff verification follow in Stage 8.
