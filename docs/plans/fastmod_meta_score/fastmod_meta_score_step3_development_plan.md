> **Feature plan:** [Step 1](./fastmod_meta_score_step1_solution_assessment.md) · [Step 2](./fastmod_meta_score_step2_feature_description.md) · [Step 3](./fastmod_meta_score_step3_development_plan.md) · [Step 4](./fastmod_meta_score_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Normal entry: root-approved operator opens a Fastmod rubric dialog from Tools, saves at least one selection, and existing publication/worker scheduling records all enabled scores in one provider call. Existing scheduling flags retain their behavior.
- Outcome: independently identified 0–1 scores, CSV harness validation, compact replaceable `.txt` templates, and consistent existing UI/API/CLI reporting. No aggregate, new trigger, or score-driven action.
- Recovery/release: rejected configuration preserves the previous selection; provider/malformed-response failures use existing bounded recovery. Historical and queued revisions cannot be mislabeled. Local normal-flow and regression verification must pass; document deployment/configuration and rollback, with external live-provider verification reported separately if credentials are unavailable. Do not deploy as part of implementation.

## Key Risks

- **High risk: data identity.** Existing queue/result keys use one rubric revision. Validate every enqueue/claim/retry/backfill reader first; hash introduction, ordered identities, and enabled template contents consistently. Workers must never score old work under a new revision; stale work is visibly superseded and requires existing explicit recovery/backfill for re-evaluation.
- **High risk: configuration/rollback.** Reuse root-approved signed settings authorization and private atomic persistence; inspect save conventions before extending them. Preserve legacy single-score history as moderation-only, never as meta or an aggregate; document that old application code must not process new-format work on rollback.
- Provider risk is resolved: both production providers implement `TextChatProvider`; use `completeTextChat()` without changing structured consumers. Preserve exchange recording and one-attempt call accounting.
- Semantic risk: compare compact moderation coverage against the current prompt before switching; verify support versus ordinary topical content and intentional overlap with representative fixtures.

## Stage 1

- Goal: Establish the modular rubric contract and templates (~1 hour).
- Dependencies: Approved plan and initial planning commit.
- Expected changes: Shared introduction; compact moderation and meta `.txt` templates; generic catalog with stable filename-derived identities and deterministic order; validated effective selection and revision. Proposed contracts: `compose(array $enabledIds): array`, returning prompt, ordered IDs, and revision; configuration supports the same selection in HTTP and worker paths. Default both initial rubrics when selection is unset; explicit empty selection is invalid.
- Verification approach: Missing/unknown/empty templates, one/two/temporary third rubric, omitted text, revision changes, and moderation coverage fixtures.
- Risks or open questions: Identity drift; compare composition and revision from the same resolved inputs before Stage 2.
- Canonical components/API contracts touched: FastScoringConfig, FastScoringRubricRevision, prompt files, shared rubric catalog/composer.

## Stage 2

- Goal: Complete one-call CSV evaluation and durable identified results (~1 hour).
- Dependencies: Stage 1 verified and committed.
- Expected changes: FastPostScorer uses TextChatProvider; strict complete-result CSV parser; result `scores` map and composition metadata. Add a versioned migration in the private Fastmod store for score-set payloads; retain legacy probability only as a moderation compatibility field, never an aggregate or a positional first score. Legacy rows remain explicitly legacy moderation-only results.
- Verification approach: Provider spy proves one call; whitespace, wrong field count, prose, blank, nonnumeric/nonfinite/out-of-range output; atomic rejection; prior private-store migration preserves history and failures.
- Risks or open questions: Partial acceptance or legacy mislabeling; validate parser and migration before queue wiring. Core read-model schema changes are not planned; if necessary, apply CONTRIBUTING.md in the same change set.
- Canonical components/API contracts touched: FastScoreProvider/Result, FastPostScorer, workflow factory/service, SqliteFastScoreStore and interfaces.

## Stage 3

- Goal: Carry composition identity through normal worker and recovery paths (~1 hour).
- Dependencies: Stage 2 verified and committed.
- Expected changes: Wire identical resolved composition into publication enqueue, worker execution, audit/backfill, retry/invalidation, and result lookup. Detect revision changes before provider calls; visibly supersede stale work without an implicit re-scoring trigger. Preserve budget/call limits and failure accounting.
- Verification approach: Publication → queued work → worker → stored score set; change selection/template between enqueue and execution; restart/retry; content changes; backfill budgets and legacy history.
- Risks or open questions: Revision races; ensure the worker scores with the exact composition it validated, and never stores new output under an old revision.
- Canonical components/API contracts touched: RouteServices, sweep service, task worker/CLI, audit/backfill services, private store.

## Stage 4

- Goal: Deliver the operator rubric dialog and persistent selection (~1 hour).
- Dependencies: Stage 3 verified and committed.
- Expected changes: Tools entry and dialog using catalog-derived options; root-approved signed read/save contract; atomic private selection persistence shared with worker/CLI. Require at least one selected rubric; no required individual rubric; invalid saves retain prior configuration and show actionable errors.
- Verification approach: Authorized save/reload and worker consumption; unauthorized/unsigned rejection; last-option deselection, missing template, added/removed rubric, failed save, and keyboard dialog interaction.
- Risks or open questions: Authorization/persistence mismatch; reuse signed settings conventions and confirm server/browser validation agree before Stage 5.
- Canonical components/API contracts touched: ToolsPageController/settings authorization flow, Application routes, tools navigation, shared configuration resolver and dialog assets.

## Stage 5

- Goal: Present score sets consistently across existing result surfaces (~1 hour).
- Dependencies: Stage 4 verified and committed.
- Expected changes: Labeled independent results in post display and existing API, CLI status/smoke, worker progress, and audit/backfill reporting. Distinguish legacy, stale, unavailable, and failed results; do not introduce filtering/ranking or actions.
- Verification approach: One/two/new rubric results and legacy/failure cases across each surface; API payload and recovery command regression checks.
- Risks or open questions: Remaining scalar assumptions; search all probability/result consumers and verify labels before final verification.
- Canonical components/API contracts touched: post template/controller, PostWorkflowApiController, RouteServices, fast_score.php, task_queue.php and result readers.

## Stage 6

- Goal: Verify the complete operator workflow and document operations (~1 hour).
- Dependencies: Stage 5 verified and committed.
- Expected changes: Focused regression/smoke coverage and Fastmod reference/runbook updates for templates, selection, CSV contract, revisions, failures, deployment, and rollback. Record concise per-stage evidence in the implementation summary.
- Verification approach: Normal operator save → publication → worker → recorded/displayed scores with deterministic provider; malformed-response recovery; full existing test runner and applicable browser checks. Live-provider smoke only with existing credentials and bounded usage; otherwise explicitly report unverified. Run rebuild/startup regression and affected normal UI/API flow if a read-model change occurred.
- Risks or open questions: Integration gaps; release only after normal-flow and recovery evidence passes and unresolved checks are disclosed.
- Canonical components/API contracts touched: tests, Fastmod reference/runbooks, Step 4 summary; no deployment or new automation.

## Execution Discipline

After explicit `Approved Step 3`, create a feature branch, organize these artifacts under `docs/plans/fastmod_meta_score/`, repair navigation/index references, and commit approved planning artifacts first. Each stage ends with verification, summary update, and a stage-scoped commit before the next stage. Keep planning uncommitted until that approval; return for revised approval if scope or stage sizing materially changes.
