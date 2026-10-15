> **Feature plan:** [Step 1](./phone_site_development_step1_solution_assessment.md) · [Step 2](./phone_site_development_step2_feature_description.md) · [Step 3](./phone_site_development_step3_development_plan.md) · [Step 4](./phone_site_development_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Approved operators cannot start and review genuine enhancement assessments from their phones on zenmemes.com. Existing handoffs require localhost and produce generic assessments rather than evaluating the selected request.

## User Stories

- As an approved operator, I want to start development from a tagged post or reply so that an idea becomes actionable without leaving my phone.
- As an approved operator, I want to answer questions, request revisions, and approve the assessment in its original thread so that planning preserves context and human decisions.
- As an approved operator, I want durable progress and retry controls so that interruptions do not lose work or create duplicate assessments.

## Core Requirements

- Existing approved operators alone can access workflow controls/artifacts and perform authorized actions on zenmemes.com; enforce the existing identity/action-authentication conventions on every mutation.
- A post's `#railspike` tag or its thread's `railspike` label exposes **Start development** under **…**. Thread labels, including those added through replies, qualify every ordinary post/reply in the thread. The selected post identifies the request; tagging alone never starts work.
- Starting immediately queues one logical workflow on the zenmemes instance. The worker evaluates the actual request and surrounding discussion, asks necessary questions, resumes on explicit answers, and produces a Railspike-compliant Step 1 artifact. Duplicate starts return the existing workflow.
- The original thread presents status, pending questions, assessment/revision history, and **Approve Step 1**, **Request changes**, and failure/retry controls. Approval identifies the exact reviewed revision and operator; stale decisions are rejected. Discussion and agent output cannot authorize transitions.
- Workflow state survives refresh, worker interruption, and read-model rebuild. Retries reconcile existing output; unavailable execution reports an actionable failure. Assessment work is separate from the serving checkout and cannot change the running application.

## Delivery Scope and Completion Boundary

**Work type: application change.** This cycle delivers the first vertical slice recommended in Step 1: tagged post/reply → instance task queue → clarification and genuine assessment → recorded Step 1 approval. Approval ends this release's automated flow with a visible planning handoff; it does not start implementation. Later separately approved releases add Steps 2/3 approval, implementation/tests/preview, explicit deploy, and explicit rollback. Track the full goal in the [checklist](../phone_site_development_checklist.md); the first release is not full-workflow completion.

Release requires the normal phone journey and failed/interrupted-worker recovery to work on a configured instance, with no duplicate artifacts, bypassed approval, or serving-checkout changes.

## Risks

- **Authority:** Exposing local execution remotely could grant unintended access. Before Step 3, validate existing operator identity and signed-action patterns; retain server-side enforcement and treat thread content as request context, never authority.
- **Worker readiness/isolation:** Missing agent access or competing instance work could stall requests or affect availability. Before Step 3, inspect worker configuration, isolation, and queue locking; require bounded execution and explicit readiness/failure states. Production readiness remains to be demonstrated.
- **Durability:** Existing handoff state uses the rebuildable read model; interruption could lose decisions or duplicate output. Before Step 3, trace rebuild and queue recovery contracts; require durable workflow evidence and repeat-safe publication.
- **Scope:** The full lifecycle exceeds this initial slice. Before Step 3, confirm a one-day/eight-stage plan; keep later capabilities visible in the checklist and require renewed scope approval for expansion.

## Shared Component Inventory

- Extend root/reply cards and standalone post/thread pages with shared menu and workflow presentation; reuse browser handoff status rendering rather than maintaining divergent previews.
- Extend existing workflow API/service and tag/label eligibility; reuse approved-operator checks and browser action authentication. Preserve legacy handoff records and compatibility fields.
- Reuse task-queue status/recovery and agent execution/publication components where compatible; introduce a durable workflow lifecycle because one-shot handoff statuses cannot represent clarification and revision-bound approvals.
- Preserve handoff activity-feed links/status events and CLI queue visibility; use one workflow identity across these surfaces. Ordinary posts and existing agent-reply flows remain usable.

## Simple User Flow

1. An operator opens a `#railspike`-eligible post/reply and chooses **… → Start development**.
2. The thread shows queued/running progress; the instance worker asks questions when needed.
3. The operator answers or requests changes; the worker publishes the current assessment.
4. The operator approves that revision; the thread records approval and the next planning handoff. Failures offer retry with preserved context.

## Success Criteria

On a 390px-wide phone viewport, complete both directly tagged-post and reply-added thread-label journeys without horizontal scrolling. Deny anonymous/non-operator actions; reject stale approval; produce request-specific options and recommendation; preserve questions, revisions, and approval across refresh/rebuild; recover from an interrupted run and repeated taps with one workflow and one result per revision. Verify the serving checkout is unchanged and approval starts no later-step work.
