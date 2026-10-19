> **Feature plan:** [Step 1](./testing_self_approval_step1_solution_assessment.md) · [Step 2](./testing_self_approval_step2_feature_description.md) · [Step 3](./testing_self_approval_step3_development_plan.md) · [Step 4](./testing_self_approval_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

As a maintainer who often runs new temporary localhost instances, I want an easy, one-click, "approve current account" button that I can use only during testing. It would trigger the same action as `./v3 approve`.

## Problem

Approving a browser account on a temporary instance currently requires a separate terminal command and the account's identity ID.

## Option A: Explicit testing opt-in plus localhost restriction

Expose **Approve current account** on the existing account screen only when the server explicitly enables testing self-approval and the request is local. One click approves the authenticated account using the same approval-seed operation as `./v3 approve`.

- Pros: clear testing boundary; works before the instance has any approved users; no separate identity entry or confirmation dialog.
- Cons: requires enabling the testing setting when starting an instance; local proxy arrangements need a defined policy.

## Option B: Automatic availability on localhost

Expose the same button whenever the instance receives a local request, without a separate testing setting.

- Pros: least setup for temporary instances; one-click workflow.
- Cons: localhost access also occurs on persistent instances and through proxies; locality alone does not establish testing intent.

## Recommendation

Choose **Option C: checkout-local marker file plus localhost restriction**. An ignored `.test-self-approval` file at the project root enables the button for local requests. Only file presence matters; its contents are irrelevant. Create it once with `touch .test-self-approval`, keep using ordinary startup commands, and remove it to disable the shortcut. No startup flag or environment variable is required.

- Pros: explicit testing opt-in that persists across restarts; one simple file operation per checkout; no recurring startup action.
- Cons: each new checkout needs the marker; a forgotten marker persists until removed; WSL connection behavior needs verification.

Enforce eligibility on the server as well as in the UI, and accept only a protected state-changing request for the authenticated account. Do not rely solely on the requested hostname or forwarded headers to establish locality. Reuse the approval-seed operation directly rather than invoking a shell command or introducing another approval mechanism.

The complete slice is: create the marker once, run the usual `./v3 start`, open the current account screen, click once, and see approval take effect immediately. Check marker presence on each request so removal disables the action without a restart. Preserve existing approval-seed error and recovery behavior, including a safe retry after partial persistence. Already-approved accounts need no action. Keep copy to the button and essential outcome/recovery feedback; track UI cleanup in `docs/plans/ui_copy_audit_checklist.md` during implementation. No new navigation destination or read-model schema change is expected.

## Evidence and open decisions

- `v3` maps `approve` to `scripts/inject_approval.php seed`, which calls `LocalWriteService::seedApprovedIdentity`; this is root-seeded approval, distinct from another member vouching for an account.
- The writer already handles canonical persistence, immediate derived-state refresh, matching-seed retries, and repair guidance.
- `./v3 start` defaults to `127.0.0.1:8000` and supports other ports and `--listen-all`; marker presence must not bypass the server's local-request restriction.
- Step 2 must define the supported localhost/proxy boundary, including temporary instances launched from WSL.
- Assessment only; implementation awaits the required planning approvals.

## Request record

- **Request:** less friction than enabling an environment variable for each instance. **Disposition:** replace the recommendation with automatic availability through ordinary local development startup. **Approval status:** revised Step 1 awaiting review. **Outcome:** no extra enabling step in the recommended workflow.
- **Request:** use a flag file. **Disposition:** supersede the automatic-launch recommendation with an ignored checkout-local marker, provisionally named `.test-self-approval`, retaining the local-request restriction. **Approval status:** revised Step 1 awaiting review. **Outcome:** enable once per checkout; remove the marker to disable without restarting.
