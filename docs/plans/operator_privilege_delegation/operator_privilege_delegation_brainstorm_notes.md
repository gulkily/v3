# Operator privilege delegation brainstorm notes

For a low-value community instance, favor operator status attached to the username, with explicit grants and every approved key for that username able to use it. This matches how people think: “make Alice an operator,” rather than “make Alice's laptop key an operator.” These are proposed choices for discussion, not an approved feature specification.

Related assessment: [Step 1](./operator_privilege_delegation_step1_solution_assessment.md), now updated to recommend account-wide authority with cascading revocation and restoration.

## Current behavior and proposed change

Current operator authority belongs to an individual identity/key approved directly by root. User-directory grouping by username does not extend that authority to all keys.

Existing membership approval creates a signed reply to the target identity's bootstrap record, tagged `identity approval`; it does not mutate tags on the bootstrap record. Reuse that mechanism with a distinct operator-grant meaning. A tag alone must not authorize a grant: the signer must have operator authority.

Under the proposed account-wide model, the reply records a grant to the account even though it is anchored to one bootstrap record. Signing identifies the acting key; the target username determines who receives the role. The account binding must be defined before implementation.

## Convenient user flow

- **Grant:** An operator opens someone's user page and clicks “Make operator.” One confirmation explains that the person can manage instance settings and appoint other operators.
- **Use:** All approved keys belonging to that username gain operator access. Moving between devices requires no separate role grant.
- **Delegate:** Every operator can appoint another operator. Membership approval remains a separate action.
- **Revoke:** An authorized operator revokes a specific grant. Preview which accounts and their approved keys will lose access, including downstream accounts without another active path to root. Who may revoke whose grants remains to be defined.
- **Recover:** Root-approved operators retain their authority through ordinary role removals; the administrator has a server-side recovery path.
- **Audit:** Grants and removals appear in activity, naming the signer and target username.

## Key approval and recovery tradeoff

Whoever can approve a new key for Alice can effectively give that key Alice's operator access. The key-approval policy therefore matters as much as the operator-grant policy.

| Decision | Suggested rule |
|---|---|
| Can an ordinary member add a key to an operator account? | No; require an operator or administrator. |
| Can an approved key add another key to its own operator account? | Yes, through the existing signed approval flow. |
| Does loss of operator authority affect downstream grants? | Yes; downstream access disappears unless another active path to root exists. The grants remain stored. |
| Does restoring an operator restore their outgoing grants? | Yes; all unrevoked grants become effective again, including downstream chains reachable through them. Confirmed by the user. |
| Can replaying a revoked grant restore it? | No; explicit revocation remains effective. A new grant is required. |
| Does an operator grant approve pending keys automatically? | No; account access and operator status stay separate. |

## Cascading revocation and restoration

Derive live operator authority from the stored grant graph: an account is an operator if it is a root operator or is reachable from a root operator through active grants. Compute reachability starting from root anchors; disconnected cycles cannot sustain their own authority.

For Root → Alice → Bob → Carol, revoking Alice's grant to Bob removes authority from Bob and Carol. If Alice also grants directly to Carol, Carol retains access through that alternate path.

**Confirmed restoration rule:** Restoring Bob's authority restores all of Bob's unrevoked grants. Carol and any further descendants regain authority when reachable through those grants. Loss of the grantor's authority suspends the grants' effect rather than deleting or revoking them. Explicitly revoked grants remain revoked.

Authority must be checked when a grant is accepted. Retain signed grants and revocations as history, and recompute effective access after changes. Replaying an old action must not undo a newer revocation.

Revoking a particular grant differs from removing all authority from an account with multiple incoming grants. Favor grant-specific revocation to mirror the stored relationships. Account-wide removal behavior and revocation permissions remain open policy questions.

Compared with independent removal, this adds moderate complexity: graph traversal, alternate paths and cycles, consistent updates to authorization, and a preview of downstream effects. It fits the preference for the live site to mirror stored data structures.

## Alternative and recommendation

Key-specific roles are easier to contain, but each new device or recovery needs another operator grant. Choose that approach if new keys can be approved too freely to support account-wide authority safely.

The proposed direction is account-wide roles, explicit signed grants, transitive appointment, cascading grant revocation and restoration, and stricter key approval for operator accounts. This gives users a familiar workflow while deriving live authority from the stored relationships.
