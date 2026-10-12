# Operator privilege delegation

Operators can grant the same instance role to other users from their user or
profile page, in either the classic or Forte presentation. The role belongs to
the username account: every approved key for that username can use operator
tools and appoint other operators. Pending keys have no operator access.

## Grant and revoke access

Sign in with an approved operator key, open the recipient's user page, and select
**Make operator**. Review the account-wide scope and confirm. Your browser signs
the exact prepared action; the server verifies the signature and your current
operator authority before accepting it. You retain your own role.

An account already holding the role offers **Add operator grant**, which creates
an additional path to root. Each grant displays its source account and links to
the signed record. Grants and revocations also appear in activity.

Any current operator can select **Revoke grant from …** for a particular incoming
grant. The confirmation lists the accounts that will lose access. Another active
path to root preserves access, so revoking one grant does not necessarily remove
a person's role. If account or grant state changes before submission, review and
confirm the action again.

## Cascading loss and restoration

Live authority follows unrevoked grants from root accounts. A disconnected cycle
of grants cannot maintain its own authority.

For Root → Alice → Bob → Carol, revoking Alice's grant to Bob suspends Bob and
Carol unless another path reaches them. Bob's grant to Carol remains recorded.
Granting Bob access again restores Carol and all further unrevoked descendants.
An explicitly revoked grant stays revoked; issue a new grant to replace it.

Root accounts are established by administrator approval seeds and cannot lose
root authority through these grant controls. Administrator recovery can seed an
approved identity of a previously delegated account without deleting its history.

## Adding account keys

Approving a key of an operator account gives that key privileged access when the
account has an active path to root. Only a current operator/administrator or an
already approved key of that same account can approve such a key. The protection
persists for accounts with operator-grant history even while their role is
suspended, and applies to invitation redemption as well as direct approvals.
Ordinary member approval and operator grants remain separate actions.

## Canonical records and recovery

Operator actions reuse signed replies to the target identity's bootstrap record,
with `identity operator internal` tags. They name the canonical account token,
target identity, acting identity and acceptance sequence. Revocations additionally
reference the grant they revoke. The detached signature covers the exact record.
Operator roles are derived from these records and canonical root seeds; there is
no separately editable roles table.

Keep bootstrap threads containing operator actions in live canonical storage.
Removing an action or its signature invalidates the ordered history and closes
operator access; restore the missing signed records from backup before rebuilding.
Adding a root seed does not bypass an invalid action history.

Authorization checks verify recorded signatures and action ordering and fail
closed when the read model is stale or the operator history is invalid. If a
write commits but refresh fails, rebuild derived state rather than resubmitting
the signed action. See the [operator recovery runbook](../runbooks/operator_recovery.md#operator-grant-recovery).

## Deployment and rollback

Deploy the application, rebuild its read model using the existing production
procedure, and retire/rebuild generated presentations. Verify grant, onward
delegation, cascading revocation, restoration and protected key approval on both
site presentations. No database migration or remote deployment is part of this
change.

Older application versions do not enforce username-wide delegated authority or
its key protections. Do not downgrade an active instance while relying on these
rules. Preserve canonical grant/revocation/signature history and backups during
repair; reconstruct authority with this version or a compatible successor.
