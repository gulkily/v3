> **Feature plan:** [Step 1](./event_feature_gating_step1_solution_assessment.md) · [Step 2](./event_feature_gating_step2_feature_description.md) · [Step 3](./event_feature_gating_step3_development_plan.md) · [Step 4](./event_feature_gating_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** an operator changes the site event-support flag; a member creates a thread through an existing full composer; a visitor opens a board or thread.
- **End-to-end outcome:** default-disabled sites expose no event UI or event blocks and reject supplied event fields; enabled sites retain current event authoring and rendering.
- **Required recovery:** disabling hides, rather than changes, existing event data; re-enabling restores its display. A rejected disabled-site submission writes nothing.
- **Deployment/external verification:** not applicable.
- **Release condition:** `./v3 test` passes, with coverage for default, site-enabled, direct disabled submission, and disable/re-enable behavior.

## Key Risks

- **High risk:** gating only templates leaves the write endpoint able to create hidden event records. Early validation: create a thread with event fields while disabled. Mitigation: reject non-empty event input in the existing write service before a file is written.
- **High risk:** a missed shared surface creates inconsistent visibility. Early validation: render the three full composer contexts and both event-block contexts in each flag state. Mitigation: pass one flag value through the shared renderer and use it in the shared composer/event-block partials.
- **High risk:** disabling could be implemented as data removal. Early validation: render a pre-existing event after disable then re-enable. Mitigation: change only capability checks; do not alter canonical records or read-model values.

## Stage 1

- Goal: register the default-off, site-mutable event-support capability and make its effective value available to templates.
- Dependencies: none.
- Expected changes: extend the feature-flag registry with `FORUM_EVENT_SUPPORT_ENABLED`, an authored-content label/description, `false` default, and site mutability; expose its effective value through the existing template-rendering data.
- Verification approach: verify registry/evaluator default, site-record, and environment-override precedence; render the Feature Flags tool and confirm the new flag is listed as disabled by default.
- Risks or open questions:
  - Impact: a non-site-mutable flag would prevent intended per-site rollout.
  - Early warning / validation: exercise a site-record override through the existing evaluator.
  - Mitigation: follow the established site-mutable flag definition pattern.
- Canonical components/API contracts touched: `FeatureFlagRegistry`, `FeatureFlagEvaluator`, `TemplateRenderer` template data, Feature Flags tool.

## Stage 2

- Goal: make the existing event UI visible only when event support is enabled.
- Dependencies: Stage 1.
- Expected changes: condition the event inputs in the shared non-compact thread composer and condition the shared event-block partial; retain all existing composer call sites and both board/thread card consumers without new routes.
- Verification approach: with the flag off, render `/compose/thread`, board inline composition, the paned new-thread dialog, a board card, and a thread page and confirm no event UI; with it on, confirm the existing inputs/block appear in those applicable surfaces. Confirm the compact QDB composer remains event-free in both states.
- Risks or open questions:
  - Impact: a partial gate could make event creation and display disagree.
  - Early warning / validation: state-paired renders for every listed surface.
  - Mitigation: use the one renderer-provided capability value in the two shared partials.
- Canonical components/API contracts touched: `thread_compose_form.php`, `event_block.php`, `thread_card.php`, `thread_root_card.php`, full-composer page/board/paned consumers.

## Stage 3

- Goal: prevent disabled sites from persisting event metadata while preserving normal thread creation.
- Dependencies: Stage 1.
- Expected changes: extend the existing thread-creation validation contract so any non-empty event date, location, or link is rejected when event support is disabled, before canonical record creation; preserve current event validation when enabled and the controller's existing validation-error re-render.
- Verification approach: call the normal creation path with event values while disabled and verify a clear error and no record/commit; verify an ordinary thread still creates; enable the flag and verify the same event values create the current canonical metadata.
- Risks or open questions:
  - Impact: checking after a write could leave an orphaned event record.
  - Early warning / validation: inspect repository state after the rejected request.
  - Mitigation: validate the capability before the service reaches its write/commit operation.
- Canonical components/API contracts touched: `LocalWriteService::createThread(array $input)`, `ComposeAndAccountKeyController::submitComposeThread(array $query)` error path.

## Stage 4

- Goal: prove flag transitions preserve existing event data and the full vertical slice remains stable.
- Dependencies: Stages 2 and 3.
- Expected changes: add focused regression coverage for an existing event-bearing thread across disabled → enabled → disabled → re-enabled states; update the Step 4 implementation summary with verification evidence.
- Verification approach: render the same seeded event thread before disable, while disabled, and after re-enable; assert the underlying event values remain unchanged; run `./v3 test`.
- Risks or open questions:
  - Impact: a visual pass alone may conceal data loss or a stale flag evaluator.
  - Early warning / validation: assert both rendered output and unchanged stored/read-model values across transitions.
  - Mitigation: use the existing feature-flag evaluator and a seeded event record in the regression test.
- Canonical components/API contracts touched: existing feature-flag and application smoke-test coverage; event read-model/rendering contract.

Waiting for "Approved Step 3" before branching and starting Step 4.
