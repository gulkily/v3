> **Feature plan:** [Step 1](./fdp_to_railspike_step1_solution_assessment.md) · [Step 2](./fdp_to_railspike_step2_feature_description.md) · [Step 3](./fdp_to_railspike_step3_development_plan.md) · [Step 4](./fdp_to_railspike_step4_implementation_summary.md)

## Completion Contract
- **Internal maintenance:** guidance leads to local Railspike instructions; both sync commands update its subtree; forum drafts use Railspike with existing data/approvals preserved.
- Recovery: reject invalid/dirty/unmigrated targets without changing their content or configuration; report fetch failures and conflicts with retry/merge-abort guidance. Preserve frozen FDP and unrelated plans.
- Release: stage checks, ordinary clone, and recovery rehearsal pass. Verify pinned upstream externally and docs/handoffs locally, including static routing; no production deployment or live Codex execution.
- Follow FDP: after approval, branch and commit only Steps 1–3 first. Each stage requires verification and an artifact-plus-summary commit; subtree helper commits cannot replace it. Organize this cycle at its fourth artifact; preserve unrelated plans.

## Key Risks
- **High risk: update loss/conflicts.** Existing recovery mixes `master`/`main`. Disposable import/update/no-op/clone/conflict-abort experiments passed. Establish fresh Railspike metadata independently of FDP; Stage 2 failure checks gate release.
- **High risk: competing guidance.** Reviewed [upstream workflow](https://github.com/gulkily/railspike/blob/95c435b82bf66c25bde834cbedfed8c54c361863/RAILSPIKE.md): entrypoints, debug state, and folder timing differ. Separate subtree guidance; reserve FDP for existing cycles.
- **High risk: handoff compatibility.** Consumers inspected; 27 baseline tests passed. Preserve `fdp_step1`, tags, drafts, and approvals; verify both old/new handoffs in Stage 3.

## Stage 1
- Goal: contributors can start Railspike work from a self-contained checkout (≤1 hour).
- Dependencies: approved plan and planning commit; recheck the pinned source before import.
- Expected changes: vendor upstream `95c435b82bf66c25bde834cbedfed8c54c361863` from `master` at `docs/railspike`; record provenance and subtree/recovery guidance outside it. Update README, cookbook, CLI reference, and plan index. Label unchanged FDP rules as legacy; temporarily refuse FDP sync with transition guidance to freeze that reference.
- Verification approach: compare imported tree to pinned source; check workflow links, ordinary-clone availability, legacy guidance, refusal without mutation, and public/static documentation tests.
- Risks or open questions: upstream setup advice could mislead; validate contributor navigation and distinguish subtree guidance.
- Canonical components/API contracts touched: workflow/docs/rendering, `v3` legacy dispatch/tests, plan navigation/index.

## Stage 2
- Goal: maintainers can update Railspike through either command name (≤1 hour).
- Dependencies: Stage 1 verified and committed.
- Expected changes: add `railspike sync`, deprecated `fdp sync` alias, target/source options, consistent branch selection, metadata validation, failure guidance, and regression coverage.
- Verification approach: disposable-repository tests cover canonical/alias updates, no-op, explicit override, unexpected source, dirty/unmigrated targets, invalid options, missing metadata, fetch failure, and conflict/abort recovery; assert rejected targets remain unchanged.
- Risks or open questions: metadata/local conflicts threaten updates; validate before mutation and rehearse recovery.
- Canonical components/API contracts touched: `v3`, sync command tests/test registration, CLI reference; frozen FDP is never synchronized.

## Stage 3
- Goal: new forum handoffs use Railspike while existing handoffs remain usable (≤1 hour).
- Dependencies: Stage 2 verified and committed.
- Expected changes: update draft wording and add `railspike` eligibility; preserve contracts and runner behavior. Add regression coverage; finish summary.
- Verification approach: draft/store/runner tests plus API/UI checks for board tags, root labels, reply restrictions, both card previews, browser rendering, legacy drafts, and localhost/approval controls. Run affected docs/static/CLI suites; audit remaining FDP references and scope.
- Risks or open questions: compatibility regressions; test old/new drafts without rewriting records or invoking live Codex.
- Canonical components/API contracts touched: `CodexHandoffDraftService`, `PostWorkflowService`; reused `fdp_step1` API/store, PHP cards, `post_analysis.js`, and runner contracts. No schema or public signature changes.
