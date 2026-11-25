# Correct the browser invitation expiry in the deployment runbook

Cycle: v3/invitation_expiry_runbook; mode: one-shot; [Plans index](../README.md)
Debug mode: enabled (project marker)
Effort study: railspike-effort, R1/P2 scope v1; [ledger](../effort_ledger/invitation_expiry_runbook.md)

**Cycle estimate: 0.03**

Status: planned; next action: verify the runbook wording against the current browser entry.

Request: the [public discrepancy report](https://zenmemes.com/posts/reply-20261125105610-173ad76511276440a15d07b35c4bf61b) contrasts the deployment runbook’s seven days with the browser behavior. No matching feedback item, branch or PR found.

Authorization: recurring ZenMemes sweep allows small verified corrections and one draft PR. No site reply, claim, production write, merge, or deploy. This is documentation only. The only product file in scope is docs/runbooks/production_deploy.md; process records remain under docs/plans/. No application, security policy, test, or generated-file changes. Other suggestions in the source post are outside this cycle.

Reuse and scope: align the runbook with [Invitation entry](../../reference/invitation_entry.md) and the existing expiry construction in public/assets/invite_issuance.js. Make clear which entry path the duration describes; do not imply a universal lifetime. Keep existing revocation instructions. [Invitation lifecycle](../../concepts/invitation_lifecycle.md) is used unchanged; its contract does not need a revision.

Completion and verification: find the stale sentence at the normal runbook URL and confirm the new sentence and cross-reference match the existing browser source and reference. Check Markdown link, authorized file scope and git diff --check. No invitation issuance, use of credentials, or runtime behavior change. Deploy and verify the updated public doc only if the maintainer chooses to after review. Recovery for the doc is a normal revert.

Base: 1b244371cdc1832b658dd430894136e3fdf17ef8; branch docs/browser-invitation-expiry. Planning commit: pending.

## Study history

- 2026-11-25 UTC, intake and entry: 0.03, single exposed Codex rater. Source and discrepancy inspected before scoring; no pristine intake. Smaller scope than R1’s 0.10 shared-template reference because no application behavior or render regression changes.

*Codex, 2026-11-25 UTC*
