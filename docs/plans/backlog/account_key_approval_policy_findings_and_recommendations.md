# Account key approval policy findings and recommendations

Status: First release narrowed to one feature flag. The broader recommendations below are future design options, not an accepted implementation specification.

The selected first-release direction is a feature flag controlling whether an approved non-operator may approve an additional key for an existing account with a different username. Disabling it preserves approvals for new usernames, additional keys for the approver's own username, and root-approved operator approvals. The [FDP checklist](account_key_approval_policy_fdp_checklist.md) now scopes this as one cycle.

Proposed flag: `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`. Its default remains undecided; enabling it by default would preserve current behavior. The recommended restriction applies to future accepted approvals and invitation redemptions, leaving existing grants intact. For this release, an existing account means another approved key already has the target canonical `username_token` at acceptance.

Independent approval permissions, stable account IDs, key revocation, and social recovery remain possible later work. They are not prerequisites for this flag release.

Authentication proves control of a key. Approval authorizes that key to access an account and participate on the site. These policies can change without introducing a different authentication mechanism.

## Requested behavior

The desired policy permits an approved member to approve the first key for a new username, and to approve another key for their own username. An operator explicitly approved by root may approve any account's key. An ordinary member must not independently authorize a new key for somebody else's existing account.

Multiple approved members may eventually be allowed to authorize account recovery. This remains an option to design, rather than part of the initial recommendation.

For example, Alice can sponsor Bob's first key and approve a new key for her own laptop. Once Bob's account exists, Alice cannot independently approve another key claiming to be Bob. Sponsoring Bob does not give Alice continuing authority over his account.

## Current implementation findings

- Approval is attached to individual key identities. The [profile repository](../../../src/ForumRewrite/ReadModel/ProfileRepository.php) looks up multiple profiles by `username_token` and groups approved profiles by that token in the user directory.
- The [approval API](../../../src/ForumRewrite/Http/IdentityApprovalAndInvitationApiController.php) checks that the approver is approved, rejects approval of the same identity, and rejects an already approved target. It does not distinguish a new username from an additional key for an existing username.
- The [signed approval write path](../../../src/ForumRewrite/Write/LocalWriteService.php) verifies the approver's signature and checks approval state when finalizing. Its approval body identifies the target key with `Approve-Identity-ID`; it does not express account creation, adding an account key, or recovery as separate purposes.
- The [full read-model builder](../../../src/ForumRewrite/ReadModel/ReadModelBuilder.php) derives approval from seed identities, direct approvals, and invitation redemptions. It repeatedly propagates approval from approved identities. The [incremental updater](../../../src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php) also derives approval state.
- Root-seeded identities are attributed to `root`. The [feature flag controller](../../../src/ForumRewrite/Http/ToolsPageController.php) currently uses approval status and that attribution label to recognize operators. A new policy should represent operator authority explicitly rather than depend on a display label.

The existing rejection of self-approval concerns the same key identity. Approving a different key for the same account is a distinct operation and should remain possible under the proposed owner permission.

## Broader permission model for future consideration

| Action | Eligible authorizer under the recommended policy |
| --- | --- |
| Create an account with its first key | Any approved member or a root-designated operator |
| Add a key to the authorizer's own account | An existing approved key for that account, or an operator |
| Add a key to another existing account | An operator |
| Recover an account without an available approved key | An operator, or an optional qualifying recovery quorum |
| Grant operator authority | Root explicitly |

Each enabled permission provides an alternative authorization path. A request succeeds if it satisfies one path completely. Unrelated partial permissions or recovery votes must not combine into an implicit authorization.

Keep these permissions independently configurable, with named presets for common combinations. Recovery eligibility, threshold, and timing need richer configuration than a single on/off flag.

## Suggested operator presets

| Preset | New accounts | Additional keys | Recovery |
| --- | --- | --- | --- |
| Operator managed | Operator | Operator | Operator |
| Operator admission with member key management | Operator | Account owner or operator | Operator |
| Member sponsorship | Approved member or operator | Account owner or operator | Operator |
| Member sponsorship with social recovery | Approved member or operator | Account owner or operator | Operator or recovery quorum |

The flag-disabled behavior implements the member sponsorship combination using current approval flows. Additional presets and social recovery remain future options.

Existing unrestricted member approval may need an explicitly named legacy setting during migration. It should not be described as equivalent to the proposed member sponsorship policy.

## Account ownership and username claims

Define the first key as the first accepted key for a username that has never been claimed. Publishing a pending key should not indefinitely reserve that username. Losing or revoking all keys must not make an existing account available for new registration.

Use a consistent canonical username representation for claims and comparisons. Claiming a username and accepting its first key must be atomic: if two pending requests target the same unclaimed username, only one may succeed as account creation. The other must be evaluated as a request to access the now-existing account.

Prefer a stable account ID with explicit key membership. A username then names the account, while an approved key proves authority to add another key to that account. Textual username equality alone does not prove ownership. A smaller initial implementation could retain username-based account lookup, but it still needs a durable ownership record and rules for reserved names, renames, and name reuse.

Adding a key should require authorization from an existing approved account key and proof that the requester controls the new key. The initial rollout must also address usernames that already group multiple approved keys; the new policy cannot establish retroactively whether all those historical keys belong to the same person.

## Social recovery options

Counting multiple approved accounts does not necessarily count independent people. If Alice can sponsor new accounts, she can sponsor accounts she controls and use them to satisfy a recovery threshold. Counting distinct accounts instead of keys prevents duplicate votes from one account, but does not solve this broader problem.

| Model | Benefit | Limitation |
| --- | --- | --- |
| Account-selected guardians | The owner chooses whom to trust before losing access | Requires advance setup and available guardians |
| Operator-designated recovery members | Provides a site-wide recovery path without owner setup | Concentrates recovery authority in a trusted group |
| Any established approved members | Makes potential helpers widely available | Account age and waiting periods do not establish independent control |

Prefer account-selected guardians when advance setup is acceptable. A threshold such as two of three is illustrative, not a settled default. Otherwise, consider a separately designated recovery group. Ordinary sponsorship must not automatically create recovery authority.

Each vote should bind to a specific account, replacement key, recovery request ID, and expiry. Count at most one vote per eligible account. Define eligibility at request creation and recheck relevant revocations before completing recovery, so newly created accounts cannot simply join an in-progress quorum.

Recovery needs explicit pending, completed, cancelled, and expired states. Consider a waiting period and notifications through available account channels. Define how existing keys can cancel or dispute a request, including cases where an existing key may be compromised. Guardian changes should be authorized and recorded separately from recovery votes.

Decide whether recovery adds a key or replaces old keys. A lost device and a suspected compromised key call for different handling. Replacement must specify what happens to old keys, existing sessions, and outstanding requests; adding a key alone does not remove an attacker's access.

## Operator authority

An operator approving a member grants account access, not operator privileges. Adding a key to an operator's ordinary account, or recovering that account, must not automatically grant operator authority to the new key. Root should authorize that privilege explicitly.

An operator permitted to approve any key is intentionally trusted to grant access to any account. Approval screens and audit records should identify operator overrides clearly. The scope of operator power, including whether operators may designate recovery members, should be explicit in configuration.

## Broader enforcement and audit recommendations

Introduce explicit signed purposes such as `create_account`, `add_account_key`, and `recover_account`. Bind each authorization to the target key and account ID or canonical username, and record the authorizer, applicable policy version, and recovery request where relevant. Authorization to attach a key and authorization to grant an operator role should remain separate.

Use one shared policy evaluator across preparation, final submission, invitation redemption, and approval reconstruction. Final submission must recheck mutable state under the write lock; a request that was valid when prepared may no longer be valid when signed and submitted.

Invitations need an explicit scope. A member's onboarding invitation must not authorize access to an existing account. Enforce this when the invitation is redeemed, since the username may have been claimed after the invitation was issued. Review CLI, imported-record, and other canonical write paths so they either apply the same rules or represent an explicit root action.

Full rebuilds and incremental updates must reach the same result. Account claims and authorization events need a stable accepted order; repeatedly applying approval propagation without recording claim history is insufficient for deciding who obtained the first key. Define historical authorization semantics so a later role grant cannot accidentally legitimize an earlier unauthorized action.

Show users the actual action being approved: creating an account, adding a key, supplying a recovery vote, or exercising an operator override. Preserve enough history to explain why each key was accepted and which authorizers made it possible.

## Migration and policy changes

Apply a newly selected policy to future approvals by default, while retaining the policy context of accepted historical grants. Changing configuration should not silently reinterpret every historical approval and remove access during a rebuild.

Preserving historical grants also preserves any problematic keys already approved under the old policy. Provide a separate review and explicit revocation process, especially for usernames with multiple keys. Grandfathering is a compatibility decision, not evidence that prior grants were authorized by the account owner.

Record policy changes and define their effective order. Revalidate pending approvals and invitations against the policy applicable when they are accepted. For recovery requests spanning a policy change, explicitly choose whether to invalidate them or require renewed authorization; do not silently combine votes made under incompatible rules.

## Future expansion sequence

1. Define durable account claims, operator authority, policy configuration, historical ordering, and migration behavior.
2. Implement member sponsorship, owner-authorized additional keys, and operator override through a shared evaluator, including invitations and both read-model paths.
3. Add clear approval purposes and audit explanations to the operator and member interfaces.
4. Design recovery eligibility, voting, cancellation, replacement, and revocation before enabling social recovery.

Acceptance scenarios should cover account creation, owner key addition, rejection of cross-account member approval, operator override, simultaneous username claims, invitation bypass attempts, and consistent rebuild results. Recovery scenarios should additionally cover duplicate voters, ineligible sponsored accounts, expired requests, cancellation, and attempts to recover operator authority indirectly.

## Future design decisions

- Introduce stable account IDs immediately, or begin with durable claims keyed by canonical username?
- Use owner-selected guardians, a site-wide recovery group, or both?
- What recovery threshold, waiting period, eligibility rules, and dispute process fit the site?
- Does each recovery mode add a key or replace keys, and how are existing sessions invalidated?
- How should existing shared usernames, renames, reserved names, and retired usernames be handled?
- How should historical grants be reviewed, and what happens to pending requests when policy changes?

For the first release, follow the single-flag checklist. Revisit these broader decisions only when a later feature requires them.
