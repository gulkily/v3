# Account key approval policy FDP checklist

Status: Revised first-release scope. This checklist does not approve an FDP step or authorize implementation.

The first release adds one feature flag controlling whether an approved non-operator may approve an additional key for an existing account with a different username. This replaces the earlier six-cycle first-release proposal. Use one scoped cycle of the [Feature Development Process](../../fdp/FEATURE_DEVELOPMENT_PROCESS.md), with the broader ideas retained in the [findings and recommendations](account_key_approval_policy_findings_and_recommendations.md) as future work.

## First release behavior

Proposed flag: `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`.

Suggested label: **Allow members to approve keys for other existing accounts**.

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

Recommended historical behavior: apply the restriction to newly accepted approvals and invitation redemptions, leaving already accepted grants approved. Turning the flag off is not a revocation operation. Durable claims after future key revocation require a separate design before adding revocation support.

## Outstanding decision

- [ ] Choose the default: enabled for compatibility, or disabled for stricter behavior from installation. The recommendation is enabled, allowing operators to opt into the restriction; the user's answer is pending.

Social recovery is outside the first release, so recovery eligibility and threshold questions do not block this cycle. Historical reapproval and migration review are also outside the proposed scope; confirm the prospective-only behavior in Step 2.

## One FDP cycle

Feature name: `non_operator_cross_account_approval_flag`.

Outcome: an operator can change the flag through the existing feature-flags interface, and subsequent approval attempts consistently follow the table above.

- [ ] Read and reprint the current FDP step instructions before starting that step, resolving FDP references relative to `docs/fdp/`.
- [ ] Step 1: assess the narrow enforcement approach, trusted operator detection, invitation handling, and reconstruction behavior. Obtain `Approved Step 1` before advancing, unless explicitly skipped.
- [ ] Step 2: describe this single flag, its default, username/account definitions, historical behavior, user feedback, and success criteria. Obtain `Approved Step 2`.
- [ ] Step 3: create the development plan with Key Risks, a Completion Contract, dependencies, and verification. Keep within one day or eight stages. Obtain `Approved Step 3`.
- [ ] Step 4: create the feature branch after Step 3 approval. The first commit contains only approved Step 1–3 planning documents.
- [ ] Follow the Step 3 and Step 4 phase files in order. During implementation, verify each stage, update its Step 4 summary, and commit the stage and summary before beginning the next stage.
- [ ] Retain stage commits without squashing, rebasing, or amending during active execution; expect at least one planning commit plus one commit per stage.
- [ ] Deliver the required completion review and request the applicable FDP approval. Link artifacts and evidence here.

Keep separate `{feature_name}_step1_solution_assessment.md`, `_step2_feature_description.md`, `_step3_development_plan.md`, and `_step4_implementation_summary.md` artifacts, creating each only when its step is authorized. Keep Step 1–3 drafts uncommitted until the Step 4 planning commit. Include the FDP navigation bar in each step artifact. Once the feature has four planning artifacts, place them in `docs/plans/{feature_name}/` and update the plan index and links.

## Implementation scope checklist

- [ ] Register one mutable flag in the existing Access and identity group. Reuse the current operator UI, environment precedence, signed change records, and reset behavior.
- [ ] Resolve operator status from trusted root approval of the actual signing key. Do not infer it from a username or a user-controllable attribution label; do not create a new role-management system for this release.
- [ ] Centralize the additional permission check and use it in approval preparation and final acceptance. Recheck the effective flag, signer, and target username state under the write lock.
- [ ] Apply the restriction to invitation redemption. An invitation issued before the flag changes or before a username gains its first approved key must be checked against current conditions at redemption.
- [ ] Inspect other approval-producing paths, including CLI and canonical imports. Document which are ordinary member actions and which are trusted root/repository administration; an ordinary member must not gain a bypass through another endpoint.
- [ ] Keep checks consistent across presentation profiles sharing the same identities and approval state.
- [ ] Update approval controls and denial messages to explain that an existing account's key needs approval from the same username or a root-approved operator when the flag is disabled.
- [ ] Preserve existing accepted grants when the flag changes and after full or incremental rebuilds. Do not filter historical approvals using only the current flag value.
- [ ] Assess how canonical ingestion preserves the distinction between already accepted history and new requests. Prefer the smallest compatible change; document the trusted repository boundary rather than promising a redesign of historical authorization.

The cycle does not require stable account IDs, a new signed approval-purpose format, a general policy engine, presets, key revocation, or social recovery. If enforcement reveals a prerequisite beyond this scope, record the concrete blocker and revisit the cycle boundary before implementation.

## Acceptance and regression checklist

- [ ] Flag enabled preserves current approval behavior.
- [ ] Flag disabled still permits a member to sponsor a new username.
- [ ] Flag disabled still permits a member to approve a different key with their own username.
- [ ] Flag disabled rejects a member approving another key for an existing different username.
- [ ] Flag disabled still permits a root-approved operator to approve any eligible target key.
- [ ] An operator's approval of a member does not make that member an operator.
- [ ] Unapproved actors, invalid signatures, and approval of the signing key itself remain rejected.
- [ ] Username comparisons use canonical tokens consistently, including existing normalization cases.
- [ ] Two ordinary cross-username sponsors racing to approve different first keys for the same username cannot both pass the new-account exception when the flag is disabled.
- [ ] Prepared requests are rechecked after a flag change or a competing approval.
- [ ] Invitation redemption cannot bypass the restriction, including invitations issued before a flag change or before the username became established.
- [ ] Direct API calls receive the same decision as the UI, and stale UI state cannot authorize a denied write.
- [ ] Existing grants survive disabling the flag, incremental refresh, and full rebuild without unexpected loss of access.
- [ ] Flag toggle, reset, environment override, and invalid configuration handling follow the approved default/fallback contract and expose the effective state correctly.

## Release handoff

- [ ] Record the final flag name and default, operator instructions, and examples for both values.
- [ ] Explain that disabling the flag restricts future approvals and does not remove already approved keys.
- [ ] Link approved artifacts, implementation summary, verification results, and stage commits.
- [ ] Record any supported approval/import path outside ordinary member enforcement and its required root/repository authority.
- [ ] Mark implementation complete only after the full allowed/denied matrix and relevant alternate paths pass verification.

## Deferred future work

These are candidates for later FDP cycles, not prerequisites or approved commitments for the flag release:

- [ ] Independently configurable approval permissions and named presets.
- [ ] Stable account IDs, durable username claims, and rename/name-reuse rules.
- [ ] Historical key review and effective key/session revocation.
- [ ] Recovery approver configuration after choosing guardians or a site-wide recovery group.
- [ ] Quorum recovery with expiry, cancellation, and explicit old-key handling.
- [ ] Broader operator-role lifecycle and historical policy/audit redesign.

## Progress

The user narrowed the first release to a cross-account approval flag. The default is pending. No formal FDP step artifacts or application changes have been made for this feature.
