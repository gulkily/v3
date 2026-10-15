> **Feature plan:** [Step 1](./phone_site_development_step1_solution_assessment.md) · [Step 2](./phone_site_development_step2_feature_description.md) · [Step 3](./phone_site_development_step3_development_plan.md) · [Step 4](./phone_site_development_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Application change: operator uses **… → Start development** on a directly tagged post or any ordinary reply in a `#railspike`-labeled thread; instance worker clarifies, assesses, revises, and records approval of the exact Step 1 revision in the original thread. Approval stops at a visible next-planning handoff.
- Recover interruptions/repeated actions without lost decisions or duplicate revisions. Keep workflow material operator-only, preserve legacy handoffs, and leave the serving checkout unchanged.
- Release requires signed-action/API checks, rebuild/crash recovery, 390px phone journeys, and a configured-instance real-agent acceptance run. Record production readiness/access gaps as pending external verification, never as a completed release. Later steps/deploy/rollback remain in the checklist.

## Key Risks

- **High risk: authority/privacy.** Identity-hint handoffs are unsuitable remotely. Validate existing session/signature/operator contracts first; authenticate reads, sign scoped mutations, recheck current privilege, and exclude artifacts from public/static/offline output.
- **High risk: durability.** Normal rebuild preserves legacy handoff tables, but downloadable read models and pruned queue history cannot own private workflow evidence. Use additive workflow/revision/action records in the private queue database; preserve legacy fields; reconcile task creation transactionally.
- **High risk: availability.** Shared queue locking and one-shot execution can stall maintenance. Validate executor readiness before dependent wiring; enforce timeout/output limits, isolated assessment workspace, and structured results. Never hold a transaction during agent execution.
- Sizing: eight stages, 45–60 minutes each, at most eight hours including checks. Split an oversized stage before implementing it; return to Step 2 if the release no longer fits. External acceptance depends on instance access.

## Stage 1
- Goal: durable, authorized request lifecycle (60 min).
- Dependencies: approved scope; validate risks above.
- Expected changes: private additive records; states queued/running/questions/review/approved/failed; signed, replay-safe actions; atomic revision checks.
- Verification approach: operator/non-operator, forged hints/signatures, replay, duplicate-start, and stale-action tests.
- Risks or open questions: reject unavailable private storage; scope signatures to instance, action, target, and expected version.
- Canonical components/API contracts touched: authentication/signing, `ApprovalPolicy`, private queue store; new workflow service `start(postId, actor): array`, `act(id, version, action, payload, actor): array`.

## Stage 2
- Goal: request reaches instance assessment worker (60 min).
- Dependencies: Stage 1; isolated executor readiness.
- Expected changes: allowlisted task type, worker/CLI wiring, immutable request/thread snapshot, bounded isolated agent adapter; validate questions or request-specific assessment output.
- Verification approach: stub questions/assessment, real-adapter readiness probe, timeout, malformed output, and unchanged serving checkout.
- Risks or open questions: missing credentials/executable/isolation fails visibly; request content cannot grant tools, transitions, or later-step execution.
- Canonical components/API contracts touched: `TaskQueueWorker`, `scripts/task_queue.php`, execution helpers; `run(workflowId, version): array`.

## Stage 3
- Goal: phone starts and follows assessments (60 min).
- Dependencies: Stages 1–2.
- Expected changes: shared **…** action and private thread panel; authenticated no-store read/start API, thread/reply eligibility, bounded polling and reload restoration.
- Verification approach: direct tag and reply-added label; thread/standalone pages; no horizontal overflow; deny public access.
- Risks or open questions: public/static HTML cannot embed operator data; render artifacts as inert text.
- Canonical components/API contracts touched: root/reply cards, page controllers, browser handoff presentation; separate `/api/railspike_workflow` contract preserving legacy APIs.

## Stage 4
- Goal: clarification and requested revisions work end to end (60 min).
- Dependencies: Stage 3.
- Expected changes: signed answer/change actions, explicit pending-question association, revision history, requeue/resume against saved context; shared browser signing adapter.
- Verification approach: questions → answer → assessment → changes → new assessment; concurrent/stale answers cannot overwrite work.
- Risks or open questions: ordinary replies never auto-authorize/resume; changed context is captured explicitly.
- Canonical components/API contracts touched: workflow `act`, thread panel, task adapter, browser signing; action payload binds workflow/version/question IDs.

## Stage 5
- Goal: reviewed Step 1 approval ends this release's flow (45 min).
- Dependencies: Stage 4.
- Expected changes: **Approve Step 1** records actor, artifact digest/version, and handoff; approval invalidated by revision; no legacy runner dispatch.
- Verification approach: stale/cross-workflow approval, revoked operator, repeat tap, and approval/revision race; verify zero Step 2/implementation tasks.
- Risks or open questions: only current review state accepts approval; agent text is never an approval.
- Canonical components/API contracts touched: workflow transitions, signed actions, shared review panel.

## Stage 6
- Goal: interruption and retry preserve one result (60 min).
- Dependencies: Stage 5.
- Expected changes: reconcile committed results before retry; recover abandoned tasks; bounded retries and explicit retry action; retain context/history outside queue pruning.
- Verification approach: crash before/after result persistence, rebuild/replacement of read model, pruning, duplicate claims, and unavailable executor.
- Risks or open questions: use run/version fences to reject late results; waiting for answers consumes no worker.
- Canonical components/API contracts touched: workflow store, queue recovery/reconciliation, status panel.

## Stage 7
- Goal: visibility and legacy compatibility remain coherent (45 min).
- Dependencies: Stage 6.
- Expected changes: operator-only activity/status projection and CLI workflow identity; preserve legacy handoff events, previews, and `fdp_step1` responses.
- Verification approach: existing handoff/agent-reply/queue tests; unauthorized activity/static/offline artifact checks; rebuild preserves links.
- Risks or open questions: derive private projections at authorized read time; never copy artifacts into public activity records.
- Canonical components/API contracts touched: activity rendering, queue status, PHP/browser previews, API catalog.

## Stage 8
- Goal: demonstrate release readiness (60 min).
- Dependencies: Stage 7; target-instance access/configuration.
- Expected changes: worker/readiness/recovery runbook, private backup/config guidance, browser fixture/journey, evidence and checklist update for this slice only.
- Verification approach: targeted PHP suites and 390px browser journey covering answers, revisions, approval, retry, refresh; configured-instance real-agent smoke and unchanged serving-checkout check.
- Risks or open questions: use actual worker identity, private paths, and cron; missing access/readiness leaves external acceptance pending. Do not enable later capabilities.
- Canonical components/API contracts touched: test runner/browser harness, production/operator runbooks, full-goal checklist.

Each stage includes its meaningful tests and summary update and is committed before the next stage; organize and commit approved plans first under Railspike's Step 4 gate. Preserve the unrelated `todo-new.txt`.
