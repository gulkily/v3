> **Feature plan:** [Step 1](./account_key_revocation_step1_solution_assessment.md) · [Step 2](./account_key_revocation_step2_feature_description.md) · [Step 3](./account_key_revocation_step3_development_plan.md) · [Step 4](./account_key_revocation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/account_key_revocation` — [standing P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Original Query

Let's work on revocation.

## Understood Intent

Continue the account-key safety checklist with effective key revocation and loss of server access for affected sessions, while preserving account history and a practical recovery path.

## Problem

A compromised approved key needs durable loss of account privileges without erasing evidence, releasing the username, or unexpectedly disabling members it sponsored.

## Options

**Option A — Operator-controlled key revocation.** A root-approved operator reviews an exact account/key in User Access, signs its revocation, and sees the resulting access state and evidence.

- Pros: One authority model and recovery contact; useful when the owner has lost every trusted device.
- Cons: Operator availability is required; compromised operator/root keys need separate administrative recovery.

**Option B — Account-owner and operator revocation.** Add same-account revocation from account-key history alongside operator intervention.

- Pros: Members can respond immediately from a trusted device.
- Cons: A malicious approved key also represents the account; it could revoke legitimate devices first. Last-key and recovery rules substantially enlarge this cycle.

**Option C — Revoke sessions only.** End current sessions while retaining the key's approval.

- Pros: Smaller session-management feature.
- Cons: The key can authenticate and sign again; does not meet compromised-key revocation needs.

## Recommendation

Choose **Option A** as the first releasable slice: operator entry → exact-key review and signed revocation → denial of subsequent privileged requests and signed writes → retained account/operator history and a clear affected-device state. Public browsing remains available under the site's existing audience rules.

- Make revocation terminal for that key in this first cycle: old approvals, invitation redemption, fresh login, retries and rebuild/import must not restore its privileges. Recovery uses a different key with explicit operator authorization; never automatically release the username when its last active key is revoked.
- Preserve historical sponsorship, content attribution and accepted grants. Do not cascade revocation to previously sponsored members; prevent new approvals/invitation redemptions from relying on the revoked issuer. Define acceptance ordering and concurrent-write behavior before implementation.
- Initially exclude root-seeded keys from the UI action; reject them server-side and document administrative recovery separately. Allow an operator to revoke the last ordinary account key with an explicit access-loss warning and a recovery route.
- Cover existing sessions, reauthentication, direct signed requests, pending preparations and relevant private APIs; fail closed when access state cannot be trusted. Do not promise erasure of already downloaded content or private messages.
- Verify stale-state/race denial, non-cascading sponsorship, retained username claims/history, full/incremental rebuild and archive parity, and phone/desktop success/failure/retry flows. If stable ownership or historical-authority requirements cannot fit one cycle, split and return for scope approval before implementation.

## Evidence and boundaries

[Authentication](../../reference/authentication_modes.md) distinguishes identity proof from current approval. `AuthApiController` accepts proof from existing profiles; sessions retain an identity ID. `ReadModelBuilder::deriveApprovalState` and its incremental counterpart derive approval through currently approved sponsors, so simply filtering revoked identities risks cascading loss. `ApprovalHistory` currently lists approved keys only; revoked keys need retained evidence. The [policy checklist](../backlog/account_key_approval_policy_fdp_checklist.md) explicitly requires durable username claims before revocation. These are planning findings, not verified revocation behavior.

No application or dependency changes proposed at Step 1; prefer existing platform facilities. Notifications, pairing, social recovery, self-service revocation and root-key rotation remain separate. Request: work on revocation / disposition: new follow-up cycle, Option A recommended / approval: Step 1 pending / outcome: assessment only.

## Effort snapshots

- **Intake:** 2026-10-23T14:43:01Z; scope v1; baseline `82cef24f17de4b644497a7d9c38aaa54d27faf02`; R1/P2; Codex single exposed rater. Prior approval-cycle/checklist exposure and initial auth/revocation source searches preceded intake; no pristine prospective estimate claimed. Initial scope: authorization, durable key/session access loss, retained history and recovery. Numeric projection withheld pending reference verification.
- **Step 1:** 2026-10-23T14:43:38Z; same baseline/rater/R1/P2; scope v1 clarified to recommended Option A above. R1 report/diff recovered at `/home/wsl/railspike-dev/docs/research/effort_estimation/` with matching frozen hashes; current feedback SHA-256 `6f5a07426cf0624bde554b0d8034f32313481a0a8ca7387ce085386f2bdef31c` differs from frozen R1. Projected total **unavailable pending exact frozen feedback recovery**; no invented rating. Distinct follow-up to completed account-key approval safety; no measured effort or accepted outcome. Ledger updates remain uncommitted until authorized closeout. Implementation and Step 2 are unapproved.

## Approval and follow-up request

Step 1 approved by the user. Request: add Option B and possibly cross-user revocation to `todo.txt` / disposition: explicitly authorized backlog edit; retain both as separate future assessment work / approval: recording authorized, implementation not approved / outcome: follow-ups recorded; current cycle remains Option A. “Cross-user” is intentionally unresolved beyond the current root-approved operator authority; future planning must define authority and safeguards.
