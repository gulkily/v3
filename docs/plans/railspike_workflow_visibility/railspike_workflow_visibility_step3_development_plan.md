> **Feature plan:** [Step 1](./railspike_workflow_visibility_step1_solution_assessment.md) · [Step 2](./railspike_workflow_visibility_step2_feature_description.md) · [Step 3](./railspike_workflow_visibility_step3_development_plan.md) · [Step 4](./railspike_workflow_visibility_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/railspike_workflow_visibility`, scope v2, [P2 ledger](../effort_estimation_trial.md#cycle-v3railspike_workflow_visibility). Steps 1–2 approved.

## Completion Contract

- Enter `/activity/` as operator/anonymous, scripts on/off: ordinary heading, navigation, feed and pagination work without the detached workflow block or workflow requests. Existing filters, RSS, entry links and pagination recovery remain functional; original thread/post workflows retain status, signed actions and retry.
- Release condition: local rendered acceptance and targeted regressions pass; report production reload verification at `https://zenmemes.com/activity/` separately. Deployment is outside this authorization; after deployment, reload with/without scripts and confirm ordinary navigation and absence of the block. If the link persists, reload fresh and report the served version rather than declaring live acceptance.
- No new navigation, API contract, repository inclusion rule or schema; broader repository coverage and private readable workflows remain separate.

## Key Risks

- **High risk:** shared workflow/auth code could disrupt operator actions. Source inspection separates Activity refresh from thread mounts; retain signing/auth dependencies and thread initialization, and replay the existing signed browser journey before closeout.
- Lost cross-thread discovery is the approved tradeoff; retain original-thread access and the separately saved reading proposal. Browser emulation/local serving cannot establish production deployment or physical-device acceptance.

## Stage 1

- Goal: remove the detached Activity integration as one working change (about one hour).
- Dependencies: Approved Step 3; feature branch, organized approved plans and planning-only commit before runtime changes.
- Expected changes: remove the Activity placeholder and Activity-only refresh branch; initialize workflow polling only for post mounts; remove Activity’s workflow script inclusion. Retain the other auth/signing dependencies conservatively and preserve thread/post script wiring. Replace the existing browser expectation of a workflow link with rendered absence and no workflow-network-activity checks, including an interval/visibility refresh opportunity.
- Verification approach: PHP/JS syntax and whitespace checks; `node tests/browser/railspike_workflow_browser.mjs` for signed start/answer/revise/approve, failure/retry, standalone posts, saved-key resumption and authorization; keep direct API summary privacy checks.
- Risks or open questions: removing shared refresh can break threads; inspect the diff before running the existing journey, retain all thread code and fix any regression before the next stage. Keep the documented summary endpoint and its store/query intact.
- Canonical components/API contracts touched: `templates/pages/activity.php`, `ActivityPageController.php`, `public/assets/railspike_workflow.js`, existing workflow browser test; workflow API contracts unchanged.

## Stage 2

- Goal: verify the reported browsing paths and close out documentation (about one hour).
- Dependencies: Stage 1 verified and committed with its implementation-summary update.
- Expected changes: extend existing browser checks only where required for operator/anonymous and script-disabled Activity absence; record evidence/limitations in the UI audit checklist, implementation summary, debug log and effort ledger. No new test framework or navigation icons.
- Verification approach: replay the stray-link location using workflow fixture data as operator/anonymous, scripts on/off, phone/desktop; inspect initial/loaded content, empty view, pagination/recovery and overflow across affected themes. Run `node tests/browser/standard_activity_pagination.mjs` for existing theme/JS/no-JS/paging recovery coverage; run `php tests/run.php RailspikeWorkflowTest StandardActivityPaginationTest AuthNavigationTest ActivityItemLinkTest`. Verify an ordinary entry opens and RSS still serves. Record the reported production URL’s post-deploy manual checks as pending unless actually performed on the released version.
- Risks or open questions: absent links alone do not prove polling stopped; observe workflow requests through the former five-second interval and visibility transition. Existing thread failure/retry coverage from Stage 1 establishes recovery; rerun only after relevant changes. Environment blockers remain explicit, not passed checks.
- Canonical components/API contracts touched: existing workflow/Activity browser checks and host documentation; ordinary Activity, thread/post, RSS and API consumers are regression targets, not new functionality.

## Execution and Study Checkpoint

After approval, organize only this cycle’s plans/debug log under `docs/plans/railspike_workflow_visibility/`, repair navigation/index references and make the required planning commit on the feature branch. The separate reading assessment stays separate/unapproved. Create Step 4 summary after that commit; each completed stage includes its summary in the same commit. Accumulated cycle ledger updates belong to closeout, outside the initial planning-only commit.

2026-10-23T05:37:07Z, Step 3 handoff: scope v2; baseline `cd2f84087a6584d2b1cb3ac8ebaa2395fa897ec6`; R1/P2; Codex, single exposed rater. Consumer and existing browser-harness inspection informs the two stages. Total projection remains **unavailable** pending exact frozen R1 recovery; previous snapshots and split history retained. No implementation, measured effort or accepted outcome. Awaiting **Approved Step 3**; plans remain uncommitted.
