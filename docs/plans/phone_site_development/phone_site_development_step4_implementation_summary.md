> **Feature plan:** [Step 1](./phone_site_development_step1_solution_assessment.md) · [Step 2](./phone_site_development_step2_feature_description.md) · [Step 3](./phone_site_development_step3_development_plan.md) · [Step 4](./phone_site_development_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress. Live server acceptance is pending.

## Stage 1 - Private authorized lifecycle
- Changes: additive private workflow/action/instance records; signed, instance-scoped expiring actions; authenticated operator enforcement; immutable request/thread snapshot; deduplicated starts and version checks.
- Verification: `php tests/run.php RailspikeWorkflowTest` — 2 passed, covering invalid signatures, hints, non-operators, expired/foreign actions, repeats, eligibility, and stale versions. `git diff --check` passed.
- Notes: records will share the private queue database; legacy/public read-model schema is unchanged. Later actions deliberately unavailable until their stages.

## Stage 2 - Queue and bounded Codex assessment
- Changes: allowlisted assessment task; atomic request/task insertion in the private queue database; CLI worker wiring; read-only isolated checkout runner with stdin prompts, explicit model, structured output, 90-second maximum and output cap; validated private revision persistence.
- Verification: `php tests/run.php RailspikeWorkflowTest TaskQueueWorkerTest TaskQueueStoreTest` — 28 passed. PHP syntax checks and diff whitespace checks passed.
- Notes: missing executable/workspace/model/home yields visible failure. Actual zenmemes credentials and sandbox support remain external acceptance requirements. No real model call was made during unit verification.

## Stage 3 - Phone request and status controls
- Changes: shared public-safe mount in root/reply cards; authenticated operator-only no-store workflow API; same-origin JSON and signature enforcement; shared browser menu/status/history renderer, explicit refresh and bounded polling; standalone post and thread script wiring.
- Verification: PHP/JavaScript syntax checks passed; `php tests/run.php RailspikeWorkflowTest CodexHandoffDraftServiceTest` — 7 passed; diff whitespace checks passed. Full browser journey is scheduled in Stage 8.
- Notes: no workflow data is embedded in public HTML; private data is fetched after operator authentication. Legacy endpoints and preview contracts remain separate and unchanged.

## Stage 4 - Clarification and revisions
- Changes: signed answers bound to pending question revision; signed change requests bound to current assessment; retained actor/question/answer/change history; atomic requeue; shared thread controls for both journeys.
- Verification: `php tests/run.php RailspikeWorkflowTest` — 4 passed, including question/answer/assessment/change flow and stale revision rejection. JavaScript syntax and diff whitespace checks passed.
- Notes: ordinary forum replies never resume work automatically; only explicit signed workflow actions do so.

## Stage 5 - Revision-bound approval and handoff
- Changes: Approve Step 1 records the exact revision/digest and operator; approved workflow visibly stops at the Step 2 handoff. Subsequent requested changes invalidate the active approval while preserving evidence.
- Verification: `php tests/run.php RailspikeWorkflowTest` — 5 passed, including wrong digest, replay, operator revocation, approval invalidation, and zero extra tasks on approval. JavaScript syntax and diff checks passed.
- Notes: no Step 2, implementation, legacy-runner, or deployment dispatch is attached to approval.

## Stage 6 - Crash recovery and explicit retry
- Changes: reconcile missing/terminal queue tasks against private workflow evidence after abandoned-task recovery; signed retry; retained revision evidence independent of queue pruning; late-result rejection via run token and version.
- Verification: `php tests/run.php RailspikeWorkflowTest TaskQueueWorkerTest TaskQueueStoreTest` — 32 passed, including expired claim, retry, repeated result delivery, history deletion, and late result. Diff checks passed.
- Notes: initial crash fixture used a fresh claim and correctly did not trigger recovery; corrected it to an expired claim. Existing queue abandonment window is 900 seconds. Waiting questions/review never occupy a task.

## Stage 7 - Private visibility and compatibility
- Changes: operator-only recent workflow activity fetched at read time; thread links and CLI queue status share workflow IDs; API discovery lists the signed workflow contract. No private workflow projection is written to canonical/public activity or offline snapshots.
- Verification: 62 targeted tests passed across workflow, handoff draft/store/runner, queue command/store/worker, and agent task suites. PHP/JavaScript syntax and whitespace checks passed; source inspection confirms public read-model/offline code is untouched.
- Notes: existing `fdp_step1`, handoff events, and agent-reply behavior remain compatible. Browser/API privacy checks are included in Stage 8.

## Stage 8 - Deployment package and local acceptance
- Changes: deployment/Codex runbook covering pinned CLI installation, API-key/device-code login, explicit environment, shared private database permissions, separate checkout, cron, limits, backup/recovery, live acceptance, and operational deployment rollback. Added real-signature 390px browser fixtures/journey and real subprocess timeout/malformed-output tests. Updated the full-goal checklist without marking live acceptance complete.
- Verification: 65 targeted PHP tests passed across workflow/auth navigation/server routing/handoff/queue suites; final focused adapter test passed after tightening missing-home validation. `node tests/browser/railspike_workflow_browser.mjs` passed, covering reply-added hashtag, signed start/answer/revise/approve, failure/retry, read-model rebuild, stale approval, inert model text, standalone direct tag, private activity, saved-key session resumption, anonymous privacy, and no overflow. Changed PHP/JavaScript syntax, local runbook links, and diff whitespace checks passed.
- Notes: browser verification found and corrected missing API session wiring and redundant reauthentication; fixture setup was corrected to rebuild approval state after adding its operator seed. Actual zenmemes deployment/access, worker credentials/model access, read-only sandbox support, and real-agent acceptance remain **pending external verification**. No production deployment or model inference was performed. Stage 8's local implementation/documentation is delivered; the release is not declared ready.

## Handoff and release status
- Branch: `feature/phone-site-development`. Approved scope is implemented locally through Step 1 approval; subsequent planning/implementation/deploy/rollback UI remains separately scoped.
- Follow [deployment and Codex configuration](../../runbooks/phone_site_development.md) to provision the server worker and perform the required live acceptance checks. Keep the first full-goal checklist item open until those checks pass.
- No public read-model schema migration; private queue additions are additive. Preserve the private task/workflow database when deploying or reverting application code.
- Unrelated `todo-new.txt` is untouched. No server credentials were requested or copied into the repository.
