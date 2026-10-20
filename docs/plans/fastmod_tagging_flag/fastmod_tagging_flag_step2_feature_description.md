> **Feature plan:** [Step 2](./fastmod_tagging_flag_step2_feature_description.md) · [Step 3](./fastmod_tagging_flag_step3_development_plan.md) · [Step 4](./fastmod_tagging_flag_step4_implementation_summary.md)

Debug mode: enabled

Follow-up to [root meta tagging](../fastmod_rubric_actions/fastmod_rubric_actions_step4_implementation_summary.md). Step 1 skipped: the shared-capability direction is agreed. New cycle identity `fastmod_tagging_flag`; create a new feature branch after Step 3 approval, without assuming reuse of the predecessor's branch. This follow-up is not separately selected for the effort trial; retain E07's completed records and pending maintainer judgment unchanged.

## Problem

Automatic tagging currently requires a meta-specific private switch. Operators need one discoverable capability flag that future tagging rubrics can share without multiplying feature flags.

## User stories

- As an operator, I want to enable automatic Fastmod tagging from Feature Flags so that I can use the existing root-meta outcome without editing a private enablement setting.
- As a maintainer, I want new tagging mappings to share that switch so that rubric expansion does not add an enable flag for every rubric/tag.

## Core requirements

- Add one site-mutable **Automatic Fastmod tagging** flag in the existing Fastmod group, default off, with scoring as a dependency. No new flag per rubric or generated tag. Reuse existing signed operator authorization and flag persistence.
- Use the same effective flag for publication eligibility and fresh worker/application checks, including queued work and CLI recovery. Disabling prevents new automatic additions while preserving existing tags/suppression and allowing explicit CLI removal/rollback. Normal scoring and rubric selection remain independent; automatic enqueue still controls whether new publications enter ordinary scoring.
- The shared flag controls the existing root-only `meta` mapping at its private threshold (initial 0.90). Future tagging mappings use this capability; do not implement a general rules engine, mapping editor, new rubric or extra tag in this cycle. Rubric selection is classification configuration, not authorization to take a moderation/ranking action.
- Define one precedence contract: new shared environment override → explicitly saved shared site value → legacy meta-only environment/private setting when neither shared override exists → false. An explicit shared site false must override legacy true. Keep the old switch as a compatibility fallback, without exposing it as a second flag. Invalid flag state fails closed for new tagging and offers existing flag recovery.
- Enabling or changing the flag causes no scoring calls, historical backfill or automatic reclassification. Preserve publication-time eligibility, retained evidence, existing presentation, CLI correction/rollback, private score storage and public-backup exclusion.

## Delivery scope and completion boundary

**Work type: application change.** Existing Feature Flags entry → authorized shared toggle → publish new root → ordinary Fastmod scoring → meta label → disable → later roots remain untagged while existing labels and explicit CLI recovery work. Update relevant reference/runbook and this cycle's plans/debug log; record the follow-up disposition in the predecessor summary. No threshold UI, reply tagging, navigation destination, styling, moderation action or instance activation.

Verify signed save, permissions, persistence/restart, legacy precedence, false override, dependency state, publication/worker agreement, disable during in-flight evaluation and no calls on save. Replay the existing root-tag browser journey from the shared toggle on phone/desktop and affected themes, including failure/retry and existing human tags. Source-only checks do not establish UI completion.

## Risks and shared components

- **Split policy:** current publication and worker paths read private booleans independently. Resolve all enablement call sites before Step 3; use one shared evaluator contract, including fresh checks and configured repository paths, without changing scoring policy.
- **Compatibility/override:** legacy defaults could lock the new switch or silently turn tagging off on upgrade. Validate the precedence matrix early and display effective source/dependency through the existing Feature Flags row; an operator can resolve overrides through existing configuration guidance.
- **Privilege/UI:** unsigned or stale-authority changes must not enable tagging. Reuse FeatureFlagRegistry/Evaluator, current signed feature-flag APIs/browser flow, Tools Fastmod cross-links, and existing feature-flag rendering rather than creating a settings form. No icon mapping change because no navigation destination is added.

## Request disposition and approval

User requested putting the shared capability switch into existence after rejecting per-rubric flag growth. Incorporated as a distinct follow-up with the existing meta mapping as its first consumer. Prior cycle approvals do not approve this application change. Step 2 awaits `Approved Step 2`; no implementation or instance setting change has begun.
