> **Feature plan:** [Step 1](./fastmod_rubric_actions_step1_solution_assessment.md) · [Step 2](./fastmod_rubric_actions_step2_feature_description.md) · [Step 3](./fastmod_rubric_actions_step3_development_plan.md) · [Step 4](./fastmod_rubric_actions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: E07 — [trial ledger](../effort_estimation_trial.md#e07--fastmod-rubric-actions)

## Completion Contract

Ordinary local publication → existing async evaluation → item-level machine-owned `meta` → normal tag discovery → signed operator correction/retry/rollback. Preserve human tags, signed bytes, scoring and visibility. Default off; inclusive initial threshold 0.90, operator-adjustable. User explicitly removed the human-labeled accuracy gate; no benchmark, live-model accuracy claim or threshold-calibration requirement for this slice. Retain human correction. No historical backfill, extra rubrics, ranking or holds. Verify phone/desktop, affected themes, rebuild/restart and generated output; record unavailable checks. Approved documentation scope includes feature plans/log, references/runbook, schema specification and trial ledger. No deployment required.

## Key Risks

- **High risk: attribution/data.** Tags currently use identity-attributed reactions; tag pages group threads. Combine human and machine-owned tags through one live read contract with exact reply targets and shared no-score presentation. Stage 1 validates integration/sizing before dependents.
- **High risk: rollback/races.** Scorer results lack explicit model identity; crash/replay can resurrect tags. Bind evidence and policy generations, durable suppression and rollback cutoff; serialize decisions and invalidate generated artifacts idempotently.
- **High risk: disclosure/staleness.** Public output must contain only public tag state, not private evidence. Freeze automatic changes while disabled; when enabled, invalidate outdated classifications without treating scoring failure as zero. Verify read and export consumers early.

## Stage 1 — Fix the shared contracts

- Goal: settle evidence, authority, target and publication boundaries.
- Dependencies: Approved Step 3 and planning commit.
- Expected changes: record consumer inventory, model/rubric freshness contract and private-state/live-join/artifact-snapshot lifecycle; retain existing human tag contracts.
- Verification approach: trace tag/API/card/static/offline consumers and private-state retention/backup; confirm later stages remain atomic.
- Risks or open questions: oversized stages block dependent implementation and require revised scope/plan; do not omit reply discovery or recovery.
- Canonical components/API contracts touched: FastPostScorer, SqliteFastScoreStore, TagScore, ThreadRepository, TagGrouping, ReadModelBuilder, artifact builders.

## Stage 2 — Persist classification decisions safely

- Goal: durable evidence, suppression and machine ownership.
- Dependencies: Stage 1 contracts resolved.
- Expected changes: extend private SQLite storage for model/provider evidence, policy versions and per-item classification/correction/audit rows; no per-tag canonical text/signature files or Git commits; conceptual decision/retry/rollback service contracts. Legacy evidence without model identity cannot create tags.
- Verification approach: private-store migration, restart, current/stale/model mismatch, threshold equality, concurrent/idempotent decision tests.
- Risks or open questions: bind bounded reply-context dependencies as well as target bytes; failures remain non-evidence and never erase human tags.
- Canonical components/API contracts touched: scorer/factory, workflow, private Fastmod store; classification service `evaluate`, `suppress`, `restore`, `retry`, `rollback` operations.

## Stage 3 — Combine tags during live reads

- Goal: read effective machine tags directly without continuously copying them into the live read model.
- Dependencies: Stage 2.
- Expected changes: shared read-only cross-database tag lookup/join, indexed by item/tag with bulk reads; union/deduplicate human and machine tags while preserving origin. Filter before counts/pagination. Expose only public tag fields through the application; no private database download or per-item connections.
- Verification approach: root/reply and mixed-origin query results, counts/pagination, bounded query count, read/write contention, unavailable private store and restart/rebuild with no scoring calls.
- Risks or open questions: Stage 1 chooses SQLite read-only attachment versus bulk composition based on existing PDO consumers; avoid writes across databases or attachment leakage into export paths. Report unavailable classification state, preserve human reading and reject tag-filter results that would silently claim completeness.
- Canonical components/API contracts touched: RouteServices PDO/read services, ThreadRepository, shared machine-tag lookup and tag discovery queries. No core schema change is assumed; if one becomes necessary, apply all CONTRIBUTING schema/version/startup/rebuild requirements.

## Stage 4 — Wire ordinary scoring and controls

- Goal: new ordinary evaluations apply policy with recoverable failures.
- Dependencies: Stage 3.
- Expected changes: worker post-score integration and signed operator policy controls on Tools → Fastmod; enabled/threshold/version state, default off, application status and retry. Exclude backfill/import work. Configuration changes make no model calls.
- Verification approach: normal thread/reply enqueue to decision; malformed/missing meta, provider failure, stale queued work, duplicates and policy changes in flight.
- Risks or open questions: score success survives classification failure; retain retry evidence independently of ordinary score pruning.
- Canonical components/API contracts touched: FastScoreSweepService, task/CLI status, existing Fastmod settings/signing panel and application service.

## Stage 5 — Discover exact classified items

- Goal: readers find matching roots/replies through meta tags.
- Dependencies: Stage 4.
- Expected changes: shared classification decoration across root/post cards and detail; extend tag index/detail to exact reply links with deduplicated human/machine labels; cover standard/Forte/QDB, search/profile and API/export consumers from Stage 1 inventory.
- Verification approach: normal tag navigation to exact item, mixed human/machine tags, reply-only matching, counts/pagination and unchanged votes/ranking/visibility.
- Risks or open questions: never label an ancestor because its reply matches; no private evidence in public detail/API output.
- Canonical components/API contracts touched: TagsPageController, TagGrouping/ThreadRepository, shared cards/detail and identified consumer adapters.

## Stage 6 — Correct and roll back

- Goal: root-approved operators recover without database edits.
- Dependencies: Stage 5.
- Expected changes: signed per-item suppress/restore and explicit retry; separate disable and rollback actions with visible consequences. Durable suppression and rollback generation/cutoff prevent reapplying historical decisions.
- Verification approach: tampered/replayed/concurrent actions, revoked operator, correction versus worker, below-threshold restoration, interrupted rollback/retry, human-tag preservation and off/on transitions.
- Risks or open questions: disabling freezes automatic mutations; restoration while disabled preserves suppression until enabled/current evaluation. Explicit rollback remains available while disabled.
- Canonical components/API contracts touched: existing prepared signed operator actions, classification service/store, Fastmod panel and post evidence/correction view.

## Stage 7 — Verify browser and distributed output

- Goal: demonstrate normal reader/operator journeys and freshness.
- Dependencies: Stage 6.
- Expected changes: focused browser/fixture coverage and any in-scope fixes; materialize only effective tags/origin during public database export and static/offline generation, using the shared tag-selection semantics; never distribute the attached private store. Track classification generations so changes invalidate affected artifacts and interrupted builds retry safely.
- Verification approach: phone/desktop, keyboard, long/empty/error states and affected themes; publish/score/discover/correct/retry/rollback with deterministic provider; restart/rebuild, export inspection and concurrent-build/stale-artifact probes.
- Risks or open questions: local mechanics do not establish model accuracy; untested surfaces remain pending, not passed.
- Canonical components/API contracts touched: existing Fastmod browser harness, shared UI/browser fixtures, artifact builders/serving and relevant regression suites.

## Stage 8 — Close out operations and study

- Goal: document and verify recoverable release.
- Dependencies: Stage 7.
- Expected changes: update reference/runbook for threshold, correction, backup/private-state retention, retry and rollback; finish implementation summary and E07 ledger with actual commits/checks and acceptance pending where needed.
- Verification approach: relevant regression suite, startup/rebuild and any applicable schema checks, documented commands, plan links, diff/scope checks; broader suite if integration warrants it.
- Risks or open questions: no full-suite/live-model/deployment pass claim without evidence; maintainer comfortable-fit judgment remains pending until supplied.
- Canonical components/API contracts touched: reference/runbook, feature summary/debug log, trial ledger and existing test commands.

## Execution and study snapshot

One planning commit after branching/organizing, then one verified stage commit including summary before each successor; no amendments. Stage 1 must stop if this breakdown cannot stay atomic under existing workflow limits. Ledger stays out of the initial planning commit and accumulates for authorized closeout.

E07, Step 3 handoff, UTC 2026-10-20; Codex single exposed rater; R1 source hashes verified; scope **v2** removes human-labeled acceptance by explicit user direction, preserving the meta-only outcome, correction and rollback; starting revision `4c521b743575979b8fefc66b38271551a57a08ef`. **0.95 Railspike units reaffirmed**, projected total from cycle start including planning, implementation, verification, documentation and ordinary correction. Removed benchmark work offsets clarified model-provenance and reply-projection work; conditional on Stage 1 integration validation. Prior intake/Step 1 0.85 and Step 2 0.95 remain intact; no hour/stage conversion or independent-rater claim.

Request: omit human-labeled accuracy gate / user-authorized Step 2 amendment / incorporated in v2; deterministic behavior and human recovery remain required. Step 3 awaits approval; no implementation authorized.


## Storage clarification — review request

- Request: avoid the file/signature/Git-object overhead of ordinary per-item tag records. Disposition: in-scope clarification of the separate machine-owned classification path; Step 3 still awaiting approval.
- Scores already reside in `state/private/fast_scores.sqlite3` (configurable): one score-set row per post/content/rubric revision, containing all enabled rubric probabilities together. Extend this database for classification state, suppression and audit rows; do not call the canonical human tag writer for generated tags. Operator authorization still uses signed actions, but does not require one canonical file/signature pair per generated tag.
- Original proposal (superseded by the accepted live-read refinement below): continuously copy effective tags into disposable live read-model rows. Private classification/correction/audit data still requires backup and must not be pruned with transient score work. Canonical Git alone cannot restore this instance-owned state, and other instances do not automatically inherit it. Cross-instance signed distribution remains outside this slice.


## Accepted refinement — live joins and artifact snapshots

- Request: user accepted joining instead of copying for live reads, with effective tags included when generating public artifacts. Disposition: in-scope architecture refinement; Step 3 approval remains pending. Supersedes the earlier continuous live projection proposal, without changing Step 2's reader/operator outcome.
- Private SQLite remains authoritative for scores, tags, correction and audit. Live reads combine tags through indexed read-only attachment/join or equivalent bulk lookup; public database/static/offline artifacts materialize public tags only at generation time. Generated copies require invalidation on classification changes; there is no continuous live-read-model synchronization. Scores remain private.
- E07 Step 3 revision, UTC 2026-10-20, scope v2 unchanged, architecture revision A2; Codex single exposed rater, R1, starting revision unchanged: **0.95 reaffirmed** for the total cycle. Removing live projection synchronization offsets cross-database availability/locking and artifact-generation integration. Earlier snapshots remain historical; no measured performance improvement is claimed.
