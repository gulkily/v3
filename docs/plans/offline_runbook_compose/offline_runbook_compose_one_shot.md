# Correct the offline-writing runbook

Cycle: `v3/offline_runbook_compose`; mode: Railspike one-shot; [plans index](../README.md).
Debug mode: enabled (.railspike_debug_on present).
Parent: [offline compose](../offline_compose/offline_compose_step4_implementation_summary.md), implemented by [PR #173](https://github.com/gulkily/v3/pull/173).

## Scope and authorization

The maintainer authorized a narrowly verified, low-risk fix and a draft PR.
This change does not include a merge, deployment, or public forum reply. The
[historical public thread](https://zenmemes.com/threads/thread-20261115204522-f7955ad7)
asks about writing a new post offline. Current main states both that New Post
is disabled and that cached composers can queue offline text. This cycle
corrects the operator runbook, not the original runtime report
[PF-20261111-B15](../../feedback/items/PF-20261111-B15.txt); do not close or
claim to resolve that report.

Authorized files: [offline runbook](../../runbooks/offline_reading.md) and
this cycle's plan, plans index, debug log and effort ledger. No changes to
runtime, tests, schema, dependencies, browser caches, or production. Do not
claim an offline link works from a fresh Board launch without evidence for that
specific entry.

## Approach, reader outcome and checks

Use the existing health-check, Outbox and privacy sections; avoid duplicating
instructions. Clarify the difference between cached public composer pages,
device-local drafts, and text queued for the normal signed send when online.
Check the [worker](../../../public/service_worker.js),
[composer submit handler](../../../public/assets/browser_signing.js),
[Outbox capture](../../../public/assets/outbox_compose.js) and
[delivery path](../../../public/assets/outbox_sender.js) against each claim.
If the old reader is stale, retain the existing refresh and the distinction
between missing controls and stale public content.

Run the relevant worker, snapshot presentation, Outbox compose and delivery
checks with PHP smoke; compare repeated failures against unmodified main.
Attempt the existing disposable
[browser fixture](../../../tests/browser/offline_compose.mjs). If it cannot
launch, document that limitation and keep claims to code and executed component
checks. Verify local links, scope and whitespace. Handoff is a draft PR backed
by those checks, no production change.

Concepts touched: [Outbox](../../concepts/outbox.md) and [Shared composer](../../concepts/shared_composer.md), used unchanged.

## Effort checkpoint

Start: `09a15f6a5a7113da6aeff0e93e11a1af444a4d1f`; scope v1; P2/R1; single exposed dot rater.
Pristine intake missing: runbook discrepancy and existing browser reproduction
were already inspected. First projection at execution entry: **0.15** for
evidence-based runbook correction plus verification. R1 frozen hashes were
not re-verified for this cycle. No scope alternatives or extended estimates.

*dot · 2026-11-21 UTC*
