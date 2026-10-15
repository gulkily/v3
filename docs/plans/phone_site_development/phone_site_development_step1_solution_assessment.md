> **Feature plan:** [Step 1](./phone_site_development_step1_solution_assessment.md) · [Step 2](./phone_site_development_step2_feature_description.md) · [Step 3](./phone_site_development_step3_development_plan.md) · [Step 4](./phone_site_development_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

For this one:

- Finish the web-based site-development workflow for use from a phone.
- Identify the missing pieces and the end-to-end workflow.
- Let a user submit a site enhancement request and hand it off to Railspike Step 1.
- See https://zenmemes.com/threads/thread-20261015034434-3bf73ef7
- And https://zenmemes.com/posts/reply-20261015034618-3a1bc284
- And https://zenmemes.com/posts/reply-20261015034710-032917cf

Please ask me questions before we write Step 1 of docs/railspike/RAILSPIKE.md.

## Understood Intent

Approved operators use zenmemes.com from their phones. A post's `#railspike` tag, or a thread-wide label added through a reply, exposes a development action under **…**. Selecting it immediately queues agent work on the zenmemes instance. The original thread hosts clarification, progress, artifacts, and explicit Railspike approval/change-request buttons, followed by implementation, review, deploy, and rollback actions. Track the full goal in a [progress checklist](../phone_site_development_checklist.md) if multiple releases are needed. Thread-label eligibility extending to all replies is a proposed interpretation.

## Problem Statement

Existing local handoffs do not provide an operator-controlled, resumable phone workflow from a forum request through reviewed deployment and rollback.

## Missing Pieces and End-to-End Workflow

Existing pieces include operator authorization, thread labels, handoff previews/statuses, a separate Codex runner, and a background task queue. The current assessment generator produces generic handoff options rather than assessing the requested enhancement; runner success records process completion rather than verified delivery.

Required path: tagged post/reply → operator starts request → instance queue/worker → clarification → request-specific Step 1 → explicit Step 1/2/3 approvals or revisions → implementation and tests → phone review → explicit deploy → verified outcome or explicit rollback. Missing links include remote operator access, reply eligibility, durable pause/resume and artifacts, approval-to-revision binding, isolated development/preview, release verification, and recovery without duplicate work. These are proposed requirements, not verified production capabilities.

## Option A: Extend Handoffs into a Complete Workflow

Expand the existing handoff experience and runner to own the entire lifecycle, using the instance task queue to execute background work.

- **Pros:** Reuses visible handoff controls and status records; fewer new concepts.
- **Cons:** Current one-shot handoffs mix draft approval with execution; extending them risks confusing process completion, Railspike approval, and release readiness.

## Option B: Thread-Centered Workflow Using Existing Services

Introduce a durable Railspike lifecycle presented in the original thread, reusing operator identity, tags, queue execution, and useful handoff components. The instance worker performs bounded work and resumes after questions or approvals.

- **Pros:** Clear approval boundaries, interruption recovery, and deploy/rollback history; supports the full phone experience.
- **Cons:** More lifecycle coordination; requires an isolated development environment on the instance and decisions about preview capacity and rollback coverage.

## Recommendation and Vertical-Slice Viability

Recommend **Option B**, retaining compatible handoff components. Start immediately on operator action, but pause at every Railspike approval gate; bind approval to the reviewed revision. Develop separately from the serving checkout. Deployment and rollback each require an explicit operator action and a verified target; application rollback must distinguish code from mutable data.

Treat the full scope as a multi-release goal unless Step 3 demonstrates it fits one day/eight stages. First usable release: tagged post or thread reply → queued, request-specific Step 1 with clarification → thread review/approval and visible failure/retry. Subsequent independently approved releases complete planning/implementation/review and then deploy/rollback. The checklist preserves the full goal without authorizing later work; do not present the initial slice as the completed workflow.
