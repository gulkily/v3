> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, `v3/retraction_effects`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects). Step 2 v5 approved. [Split investigation preserved](./retraction_effects_step3_split_history.md).

## Completion Contract

Browser Retract and operator `delete-record` withdraw source-owned thread labels and label-derived scores/counts/order, preserve independent labels and replies, and offer repeat-safe legacy inspection/repair. Reload, rebuild, import and publication must not resurrect removed effects. Replay the ZenMemes pin failure on a configured fixture, including physical root deletion and usable surviving replies. Release requires focused integration/browser/theme verification, documented recovery and explicit unresolved legacy outcomes; production ZenMemes deployment is a separate acceptance step. Remaining effect families stay deferred under the parent all-effects goal.

## Key Risks

- **High risk: wrong ownership.** Imported history may contain no original creation event. Validate evidence fixtures first; timestamps/co-import alone never prove ownership. Explicit operator resolutions must be scoped, auditable and inspectable.
- **High risk: lost conversation.** Root deletion currently leaves consumers expecting a missing root. Validate a minimal removed-root representation before wiring physical deletion; never use a reply's prose as the root.
- **High risk: resurrection/partial commits.** Resolve canonical removal evidence, acceptance lock ordering and import rules before deletion integration; extend existing publication guards rather than creating competing locks. Keep accepted evidence on refresh failure; rollback must not discard it.

## Stage 1
- Goal: ownership inspection for current and legacy labels.
- Dependencies: approved v5.
- Expected changes: source-post provenance on generated labels in every post-write path, shared ownership resolver, thread-attributes evidence output; independent action labels remain unowned.
- Verification approach: round-trip parsing, signed-byte compatibility, generated versus standalone labels, bulk-import ambiguity and reported legacy fixtures.
- Risks or open questions: false attribution; validate conflicting/missing evidence first and return unresolved rather than guessing.
- Canonical components/API contracts touched: label record/parser, post writers, `LabelOwnership::resolve(repository, sourceId)` returning proven/unresolved labels, thread-attributes.

## Stage 2
- Goal: durable removal and historical resolution evidence.
- Dependencies: Stage 1.
- Expected changes: canonical source-removal/label-resolution evidence with target identity, source hash, actor and thread structure; validation, repeat-safe acceptance and CLI inspect/resolve/retry contracts. Never fabricate an affected author's signature.
- Verification approach: changed targets, forged/conflicting evidence, partial acceptance and repeated explicit operator resolutions.
- Risks or open questions: acceptance authority and physical-removal recovery; settle evidence trust and lock order before downstream mutations.
- Canonical components/API contracts touched: canonical repository/path validation, operator CLI catalog, `LabelRemoval::inspect(sourceId)` and `resolve(sourceId, labelId, actor)`.

## Stage 3
- Goal: browser retraction withdraws owned labels end to end.
- Dependencies: Stages 1–2.
- Expected changes: common effective-label filtering in rebuild/incremental aggregation; connect signed retraction/retry, recompute derived totals and invalidate presentation without touching independent replies.
- Verification approach: one/shared labels, scores/votes/activity, permissions, repeated retraction and failed-refresh retry through existing API/browser flow.
- Risks or open questions: divergent aggregation; compare incremental results against full rebuild before continuing.
- Canonical components/API contracts touched: LocalWriteService retraction, read-model builders/updaters, prepared-retraction APIs and existing recovery feedback.

## Stage 4
- Goal: physically removed parents/roots preserve conversation.
- Dependencies: Stage 2.
- Expected changes: derive content-free missing-source placeholders from retained removal evidence; preserve root identity, reply links and supported reply submission without inheriting a reply's content/author.
- Verification approach: root/intermediate/leaf deletion fixtures, direct post/thread navigation, board joins and replying to surviving posts; prior-schema startup rebuild if required.
- Risks or open questions: misleading attribution or broken joins; validate absent-root queries and write-target checks before CLI integration.
- Canonical components/API contracts touched: indexPosts, thread/post lookup and reply validation; any affected query/schema change also bumps schema version, updates spec and startup-regression coverage per CONTRIBUTING.

## Stage 5
- Goal: `delete-record` and legacy repair perform complete label cleanup.
- Dependencies: Stages 2–4.
- Expected changes: discover proven dependents before source deletion; accept evidence and delete owned records/signatures under shared write coordination; resume missing-source deletions and apply explicit legacy resolutions. Resolve artifact roots consistently.
- Verification approach: replay reported reply-ID deletion, explicit repair of imported orphan pin records, unrelated/shared labels, interrupted Git/refresh and repeat invocation; readable output/help and JSON where supported/introduced.
- Risks or open questions: deleting unrelated staged files or lost recovery; restrict commit paths and preserve evidence before removing source bytes.
- Canonical components/API contracts touched: delete_record, thread-attributes/repair CLI, common removal service, invalidator and static builder.

## Stage 6
- Goal: old imports cannot restore removed sources/effects.
- Dependencies: Stages 2–5.
- Expected changes: carry/validate provenance and removal evidence through archives/import planning; suppress/reject resurrecting records while retaining independent replies and valid labels. Report missing trusted evidence explicitly.
- Verification approach: old archive replay, fresh files-only restore, malformed evidence, idempotent reimport, signed legacy records and surviving conversation.
- Risks or open questions: untrusted archive authority; use local acceptance trust and explicit unresolved outcomes, not incoming claims alone.
- Canonical components/API contracts touched: ArchiveRecordCatalog, ContentImportPlanner/Runner, canonical archive/download handling and read-model derivation.

## Stage 7
- Goal: acceptance, retries and publishers agree under concurrency.
- Dependencies: Stages 3–6.
- Expected changes: include label-removal evidence in existing publication generations; recheck source validity for racing label writes, imports and release activation; wire partial status/retry to existing web/CLI surfaces.
- Verification approach: paused writer versus removal, stale release activation, interrupted refresh and lost response; preserved replies and independent actions remain usable.
- Risks or open questions: deadlocks/stale publication; assert common lock order and deterministic race fixtures before closeout.
- Canonical components/API contracts touched: PublicationGuard, presentation invalidation, static/offline publication, canonical write locks and removal recovery.

## Stage 8
- Goal: acceptance, documentation and bounded closeout.
- Dependencies: Stages 1–7.
- Expected changes: record ownership-family inventory, operators' legacy repair/retry instructions, schema/CLI/API documentation as affected, UI-copy checklist and study outcome; list deferred non-label effects.
- Verification approach: focused retraction/deletion/import/read-model tests; rebuild plus API/board parity; ZenMemes-fixture browser checks on phone/desktop and affected themes, standard/Forte/QDB/shared views, tags/profile/activity, offline/SQLite output; verify root placeholders, preserved replies and pin marker/order. Run required repository checks; record deployment/device limitations.
- Risks or open questions: claiming all-effects completion; close only the label slice, retain parent pending and separate production/maintainer acceptance.
- Canonical components/API contracts touched: existing test/browser harnesses, retraction/CLI runbooks, read-model spec if changed, feature summary and effort ledger.

## Execution and study checkpoint

Each stage targets approximately one hour, includes its focused regression checks and summary update, and is committed before the next. If evidence cannot support that bound, stop before dependent implementation and revise/split the stage or return to Step 2 for a scope change; do not silently omit required recovery. These are planned bounds, not measured effort. Stage 8 consolidates existing stage evidence rather than deferring functional tests to closeout.

2026-10-23 UTC; scope v5, same baseline `e960f666e1146d26b2f9435d98a447c2594b9883`; Codex, single exposed rater; R1/P2. Projected total remains unavailable pending exact frozen calibration report/diff recovery; preserve prior checkpoints and original unsplit goal. No retrospective/measured effort or completed outcome. Await `Approved Step 3`; no implementation/branch/commit yet. After approval organize this feature's current plans, historical approval/split records and debug log on the feature branch before the scoped planning commit; retain ledger accumulation for closeout under the study rule.
