> **Feature plan:** [Step 1](./absent_retraction_snapshot_step1_solution_assessment.md) · [Step 2](./absent_retraction_snapshot_step2_feature_description.md) · [Step 3](./absent_retraction_snapshot_step3_development_plan.md) · [Step 4](./absent_retraction_snapshot_step4_implementation_summary.md)

Debug mode: enabled (inherited).
Effort study: `railspike-effort`, cycle `v3/absent_retraction_import`; [host ledger](../effort_estimation_trial.md#cycle-v3absent_retraction_import), standing enrollment P2.

## Problem and user stories

Valid retained retractions for physically absent posts currently prevent offline snapshot publication. Publish the remaining safe content without requiring historical repair or repeatedly failing the whole build.

- As an operator, I want direct and queued snapshot publication to finish despite absent retraction targets so that offline readers receive current content.
- As an offline reader, I want excluded content to remain unavailable across base/update snapshots so that stale copies do not restore it.

## Core requirements

- For a readable, valid retraction with a confirmed absent target, omit the target post and its retraction from the snapshot, retain canonical evidence/deletions, and continue. Preserve surviving replies and existing private-destination access rules.
- Keep that target excluded throughout the current build if it reappears. A later build validates present content under existing authority rules, including author mismatch and protected-post checks; temporary exclusion creates no trusted removal authority.
- Prevent excluded content from leaking through stale read-model rows, derived presentation or an earlier base snapshot when applying an update. Produce usable base/update artifacts through both direct publication and queued execution/retry.
- Stop only when affected content cannot be safely isolated or remaining publication cannot be established as safe. Unreadable repositories, missing tracked retractions, unsafe paths and malformed evidence retain existing rejection/recovery behavior; they are not confirmed absence. Preserve existing safeguards for unrelated concurrent changes.
- Reuse shared validation and snapshot publication paths; regression-check shared static publication without broadening its behavior. Defer same-build reappearance resolution, generic invalid-record skipping, historical repair and project-wide resilient processing.

## Delivery scope and completion boundary

Application change with focused regressions and operator/implementation documentation, including study bookkeeping. No new third-party dependencies proposed (delta zero); no live canonical repair, production task retry or deployment authorized.

Normal entry is existing direct offline publication or the queued snapshot worker. Completion means a usable published base/update and its normal manifest/status, with the affected content excluded and other content retained. Recovery from an actual safety failure uses existing diagnostic/repair and rerun/retry paths; retain the previously usable publication where existing recovery guarantees require it. Release requires end-to-end artifact consumption and failure/retry evidence, not only guard-unit tests.

## Shared component inventory and operator flow

Reuse/extend `PublicationGuard` validation, `PublicOfflineSnapshotBuilder`, `OfflineSnapshotPublisher`, the existing direct publication command and `TaskQueueWorker`; preserve their existing output/status surfaces. Regression-check shared guard consumers: static artifact building/release, high-traffic cache publication and import-triggered publication. Offline base/update download, manifest, bootstrap and reader surfaces consume the resulting artifacts; verify them without adding controls or explanatory UI.

1. Operator invokes the existing publication command, or an existing queued publication runs against retained retractions such as the task-952 case.
2. The build isolates confirmed absent targets, continues through their reappearance, and publishes the remaining content.
3. Operator verifies completion/artifact availability; an offline reader loads the base and applies the update. If safe isolation fails, the existing actionable failure identifies the repair and retry needed. Acceptance uses repository fixtures reproducing the reported condition; it does not retry live task 952.

## Risks and early validation

- **Stale/base content leakage:** an update may leave old target content visible. Before dependent implementation, establish how normal base/update consumption excludes it; mitigate through artifact and actual consumer acceptance checks, replanning if scope must grow.
- **Unsafe absence or authority inference:** corruption or reappearance could suppress unrelated content permanently. Validate absence/evidence distinctions and later-build authority behavior early; keep exclusion build-local and canonical evidence intact.
- **Concurrency and shared regressions:** broader guard relaxation could publish unsafe artifacts or still abort on target reappearance. Validate target isolation versus unrelated changes and inventory shared callers before finalizing Step 3; retain existing unrelated safeguards and compatibility regressions. Unresolved isolation risks block dependent stages.

## Success criteria and study checkpoint

Direct and queued base/update publication finish for valid absent targets, including target reappearance during the build; consumed snapshots exclude the affected post/retraction and stale copies while preserving surviving replies. A later build correctly validates reappeared sources. Safety failures reject unsafe publication with recoverable diagnostics; private access and shared static publication regressions pass.

Step 1 scope v3 explicitly approved by the user. Step 2 handoff 2026-10-23T19:37:36Z: scope v3 clarified, original baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`, R1/P2, Codex single exposed rater. **0.70 projected total reaffirmed**, including prior investigation/planning, required verification, recovery and documentation. Exact R1 hashes reverified; existing scores and limited shared-guard/publisher/worker source inspection visible, no independent rating or runtime validation. Base/update exclusion remains a planning risk, not a verified implementation. Historical checkpoints retained in Step 1; no measured effort or accepted outcome.

Request disposition: reduced aborts through affected-target exclusion is included in approved scope v3; abort-on-reappearance trim remains rejected. No pending additional scope requests. Received **Approved Step 2**; continued in [Step 3](./absent_retraction_snapshot_step3_development_plan.md). Implementation remains unapproved.
