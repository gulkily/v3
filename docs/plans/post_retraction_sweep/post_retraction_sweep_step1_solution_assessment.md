> **Feature plan:** [Step 1](./post_retraction_sweep_step1_solution_assessment.md) · [Step 2](./post_retraction_sweep_step2_feature_description.md) · [Step 3](./post_retraction_sweep_step3_development_plan.md) · [Step 4](./post_retraction_sweep_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

We have implemented basic post retraction, but the content still stays in multiple places. Let's work on the next step, having a sweep process. It can either be immediately run and/or in the cron job. Let's explore both options and any other obvious ones.

## Problem

Retraction suppresses served content but does not systematically remove retained local copies or reconcile interrupted cleanup.

## Evidence and boundary

The write flow already refreshes derived state and retires generated HTML/offline copies, with partial-result retries ([write service](../../../src/ForumRewrite/Write/LocalWriteService.php)). LLM exchanges are withheld rather than erased ([visibility](../../../src/ForumRewrite/Llm/ExchangeVisibility.php)); canonical files and Git history remain ([reference](../../reference/author_post_retraction.md)). The existing queue has a cron worker, coalescing, and failure status ([CLI](../../reference/v3_cli.md#manage-the-background-task-queue)). Current effective-retraction checks require the original post; simply deleting it would require preserving suppression and attribution another way. Operator acceptance also depends on trusted Git history.

## Option A — Inline sweep

Run cleanup inside the accepting retraction request, returning its result immediately.

- Pros: shortest cleanup delay; straightforward immediate feedback.
- Cons: increases request latency and lock duration; interruption still requires recovery; unsuitable for large scans or history rewriting.

## Option B — Scheduled reconciliation

Cron periodically discovers effective retractions and sweeps managed local copies, including missed or interrupted cleanup.

- Pros: catches historical retractions and imports; bounded work and retries; independent of browser completion.
- Cons: cleanup waits for scheduling and competing jobs; requires discovery, since running a queue alone does not create sweep work.

## Option C — Immediate queue trigger plus scheduled reconciliation (recommended)

Keep immediate suppression, enqueue cleanup on acceptance, and periodically reconcile all effective retractions through the same sweep. Permit an operator to invoke that sweep directly for immediate execution or preview.

- Pros: fast trigger, durable recovery, and manual remediation share one policy; avoids broad scans in HTTP requests.
- Cons: enqueueing is not immediate execution; queue backlog delays removal; acceptance-to-enqueue failures and concurrent artifact writers must be handled.

## Option D — Sweep during rebuild/import/publication

Add cleanup to existing content lifecycle operations.

- Pros: convenient coverage for reimports and regenerated artifacts; reuses existing entry points.
- Cons: dormant copies persist until those operations run; broad cleanup slows unrelated operations. Useful supplement to C, weak as the only trigger.

## Recommendation and viable slice

Choose C: one repeatable, bounded local sweep with preview, manual execution, acceptance-triggered queueing, periodic discovery, and visible retryable failures. Keep existing suppression effective throughout; report pending cleanup separately from completed retraction. Verify recovery after interruption and that concurrent writers cannot restore removed copies.

First scope: inventory managed local stores, retire/rebuild stale presentation and snapshot artifacts, and remove content-bearing LLM payloads with known retracted dependencies while retaining minimal non-content status. Unknown historical dependencies need an explicit retention policy: withholding is not proof that destructive deletion is justified. Review other retained stores during inventory rather than claim exhaustive erasure.

Canonical-file removal, Git-history rewriting, backups, shared attachments, and copies in others' posts need separate policies; do not automatically delete them. Git rewriting can destroy operator-acceptance evidence. If canonical/history erasure is the intended next outcome, revise this slice before approval. Completion must name covered stores and remaining copies; deletion is not a secure-erasure guarantee.
