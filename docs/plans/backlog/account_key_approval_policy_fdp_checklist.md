# Account key approval policy FDP checklist

Status: Stage 1 enforcement implemented in the approved `account_key_approval_safety` cycle. Stages 2–6 and release handoff remain pending. This checklist records progress; it does not grant new FDP approvals or establish production deployment.

This checklist supplies the flag requirements within the approved combined cycle for priorities 1–3 of the [visibility and safety checklist](account_key_approval_visibility_and_safety_checklist.md). The flag controls whether an approved non-operator may approve an additional key for an existing account with a different username. The combined cycle supersedes the earlier standalone flag proposal; its approval wording, durable evidence, and history requirements also remain part of the release. Follow the [Feature Development Process](../../fdp/FEATURE_DEVELOPMENT_PROCESS.md); broader future ideas remain in the [findings and recommendations](account_key_approval_policy_findings_and_recommendations.md).

## First release behavior

Implemented flag: `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`.

Registered label: **Allow members to approve keys for other existing accounts**.

The flag adds a restriction to the existing approval rules. Enabling it preserves current permissions; it does not bypass signature checks, approved-actor requirements, or other existing validation.

| Actor and target | Flag enabled | Flag disabled |
| --- | --- | --- |
| Approved non-operator approving the first key for a new username | Allowed | Allowed |
| Approved non-operator approving a different key for their own username | Allowed | Allowed |
| Approved non-operator approving another key for an existing different username | Allowed | Denied |
| Root-approved operator approving a key for any username | Allowed | Allowed |
| Unapproved actor | Denied | Denied |

All allowed entries remain subject to existing approval validation. The same key cannot approve itself.

For this limited release, use the existing canonical `username_token` to compare usernames. An existing account means that another approved key already has the target username when the new approval is accepted. A pending key alone does not make it an existing account. This uses the current account representation; it does not establish a new ownership model.

Approved and implemented policy-toggle behavior: apply the restriction to newly accepted approvals and invitation redemptions, leaving already accepted grants approved. Turning the flag off is not a revocation operation. Durable claims after future key revocation require a separate design before adding revocation support.

## Approved default

- [x] Default enabled for compatibility, as approved in the combined Step 2. Operators can disable the flag to restrict future cross-account approvals.

Social recovery is outside the first release, so recovery eligibility and threshold questions do not block this cycle. Historical reapproval and migration review are also outside the approved scope. Step 2 confirms prospective-only restrictions; expiry-independent invitation history remains Stage 3 work.

## One FDP cycle

Active feature name: `account_key_approval_safety` (supersedes the proposed `non_operator_cross_account_approval_flag` cycle).

Outcome: an operator can change the flag through the existing feature-flags interface, and subsequent approval attempts consistently follow the table above.

- [ ] Read and reprint the current FDP step instructions before starting that step, resolving FDP references relative to `docs/fdp/`.
- [x] Step 1: combined [solution assessment](../account_key_approval_safety/account_key_approval_safety_step1_solution_assessment.md) approved for Option B.
- [x] Step 2: combined [feature description](../account_key_approval_safety/account_key_approval_safety_step2_feature_description.md) approved, including the default-enabled flag and prospective behavior.
- [x] Step 3: combined [development plan](../account_key_approval_safety/account_key_approval_safety_step3_development_plan.md) approved with six stages.
- [x] Step 4 started on `feature/account-key-approval-safety`, with planning commit `febc1e03` and Stage 1 implementation commit `c7ba748b`. See the [implementation summary](../account_key_approval_safety/account_key_approval_safety_step4_implementation_summary.md); this is not Step 4 completion.
- [ ] Follow the Step 3 and Step 4 phase files in order. During implementation, verify each stage, update its Step 4 summary, and commit the stage and summary before beginning the next stage.
- [ ] Retain stage commits without squashing, rebasing, or amending during active execution; expect at least one planning commit plus one commit per stage.
- [ ] Deliver the required completion review and request the applicable FDP approval. Link artifacts and evidence here.

Keep separate `{feature_name}_step1_solution_assessment.md`, `_step2_feature_description.md`, `_step3_development_plan.md`, and `_step4_implementation_summary.md` artifacts, creating each only when its step is authorized. Keep Step 1–3 drafts uncommitted until the Step 4 planning commit. Include the FDP navigation bar in each step artifact. Once the feature has four planning artifacts, place them in `docs/plans/{feature_name}/` and update the plan index and links.

## Implementation scope checklist

- [x] Register one mutable flag in the existing Access and identity group. Reuse the current operator UI, environment precedence, signed change records, and reset behavior.
- [x] Resolve operator status from trusted root approval of the actual signing key. Do not infer it from a username or a user-controllable attribution label; do not create a new role-management system for this release.
- [x] Centralize the additional permission check and use it in approval preparation and final acceptance. Recheck the effective flag, signer, and target username state under the write lock.
- [x] Apply the restriction to invitation redemption. An invitation issued before the flag changes or before a username gains its first approved key must be checked against current conditions at redemption.
- [ ] Inspect other approval-producing paths, including CLI and canonical imports. Document which are ordinary member actions and which are trusted root/repository administration; an ordinary member must not gain a bypass through another endpoint.
- [x] Keep checks consistent across presentation profiles sharing the same identities and approval state.
- [x] Return denial messages explaining that an existing account's key needs approval from the same username or a root-approved operator when the flag is disabled.
- [ ] Update approval controls with action-specific wording and consequence review; the existing UI still uses “Approve user.”
- [x] Preserve existing accepted grants when the flag changes and after full or incremental rebuilds. Do not filter historical approvals using only the current flag value.
- [ ] Assess how canonical ingestion preserves the distinction between already accepted history and new requests. Prefer the smallest compatible change; document the trusted repository boundary rather than promising a redesign of historical authorization.

The flag enforcement stage does not require stable account IDs, a general policy engine, presets, key revocation, or social recovery. The approved combined cycle does require signed review context and durable acceptance evidence in later stages. If enforcement reveals a prerequisite beyond this scope, record the concrete blocker and revisit the cycle boundary before implementation.

Stage 1 also blocks generic identity-protocol writes and validates canonical target/bootstrap matches. Root seeds and canonical imports remain trusted repository administration; the complete path inventory and release documentation above remain open. Shared writer checks cover both presentation profiles, but browser verification remains pending.

## Acceptance and regression checklist

Checked items have implementation/test evidence. Unchecked items may have partial coverage; they still require the complete verification described, including normalization, signatures, UI, configuration, and rebuild cases. Policy-toggle retention does not establish expiry-independent invitation replay.

- [x] Flag enabled preserves current approval behavior.
- [x] Flag disabled still permits a member to sponsor a new username.
- [x] Flag disabled still permits a member to approve a different key with their own username.
- [x] Flag disabled rejects a member approving another key for an existing different username.
- [x] Flag disabled still permits a root-approved operator to approve any eligible target key.
- [x] An operator's approval of a member does not make that member an operator.
- [ ] Unapproved actors, invalid signatures, and approval of the signing key itself remain rejected.
- [ ] Username comparisons use canonical tokens consistently, including existing normalization cases.
- [ ] Two ordinary cross-username sponsors racing to approve different first keys for the same username cannot both pass the new-account exception when the flag is disabled.
- [x] Prepared requests are rechecked after a flag change or a competing approval.
- [x] Invitation redemption cannot bypass the restriction, including invitations issued before a flag change or before the username became established.
- [ ] Direct API calls receive the same decision as the UI, and stale UI state cannot authorize a denied write.
- [ ] Existing grants survive disabling the flag, incremental refresh, and full rebuild without unexpected loss of access.
- [ ] Flag toggle, reset, environment override, and invalid configuration handling follow the approved default/fallback contract and expose the effective state correctly.

## Release handoff

- [ ] Record the final flag name and default, operator instructions, and examples for both values.
- [x] Explain that disabling the flag restricts future approvals and does not remove already approved keys.
- [x] Link approved artifacts, implementation summary, verification results, and stage commits.
- [ ] Record any supported approval/import path outside ordinary member enforcement and its required root/repository authority.
- [ ] Mark implementation complete only after the full allowed/denied matrix and relevant alternate paths pass verification.

## Deferred future work

These are candidates for later FDP cycles, not prerequisites or approved commitments for the flag release:

- [ ] Independently configurable approval permissions and named presets.
- [ ] Stable account IDs, durable username claims, and rename/name-reuse rules.
- [x] Historical key review and effective operator-controlled ordinary-key/session-access revocation. See the [revocation cycle](../account_key_revocation/account_key_revocation_step4_implementation_summary.md); owner self-service and root-key recovery remain deferred.
- [ ] Recovery approver configuration after choosing guardians or a site-wide recovery group.
- [ ] Quorum recovery with expiry, cancellation, and explicit old-key handling.
- [ ] Broader operator-role lifecycle and historical policy/audit redesign.

## Progress

The approved combined cycle includes the default-enabled flag plus approval review and history. Stage 1 is implemented in `c7ba748b`; stages 2–6 remain. The linked Step 4 summary is the implementation record and documents earlier targeted verification and unresolved broad-suite failures.

Checkout review verification passed: `php tests/run.php ApprovalPolicyTest` (6/6) and `php tests/run.php WriteApiSmokeTest::testSignedInvitationRedemptionRechecksPolicyBeforeAcceptance` (1/1). The tests cover the permission matrix, live toggles, competing claims, stale state, canonical seed authority, protocol bypass attempts, invitation restrictions, same-second replay order, and signed redemption with grant retention after a restricted rebuild. They do not establish completion of the full acceptance checklist or production deployment.
