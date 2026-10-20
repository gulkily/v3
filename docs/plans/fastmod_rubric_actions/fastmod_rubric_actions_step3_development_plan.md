> **Feature plan:** [Step 1](./fastmod_rubric_actions_step1_solution_assessment.md) · [Step 2](./fastmod_rubric_actions_step2_feature_description.md) · [Step 3](./fastmod_rubric_actions_step3_development_plan.md) · [Step 4](./fastmod_rubric_actions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: E07 — [trial ledger](../effort_estimation_trial.md#e07--fastmod-rubric-actions)

## Completion Contract

**Scope v3: root-only meta tagging.** A newly published local top-level post receives `meta` after its ordinary successful Fastmod score meets the configured threshold (initial **≥0.90**). Existing-style tags support normal live/static/offline discovery. Private SQLite owns scores/classifications/suppression; no per-tag canonical files or Git commits, public score export, styling, reply tagging, browser controls or automatic reclassification. CLI status, remove-and-suppress, retry recovery and machine-only rollback complete the outcome. Preserve human tags, signed bytes, votes and visibility.

Default off; only publication-time eligible roots qualify, including retries of their initial work. Configuration changes never backfill. Keep classifications stable until explicit removal; no model-accuracy gate. Complete root publication → score → tag → discovery → CLI correction/rollback plus restart/rebuild and generation-safe artifact checks. No external deployment required; report unavailable checks. Documentation: reference/runbook, feature plans/log, trial ledger, schema spec only if applicable. This replaces the historical plan below; original Stage 1 remains complete, but its later stages are superseded.

## Key Risks

- **High risk: ownership/loss.** Private machine decisions outlive transient scores; test private migration/retention first, preserve human labels and document backup. No public attachment/export of private evidence.
- **High risk: replay/rollback.** Capture eligibility during new local publication; retain idempotent evidence receipts and suppression. Verify old/reply/backfill work and duplicate retries cannot create or resurrect tags.
- **High risk: stale artifacts.** Live joins are feasible (prior Stage 1 probe); generated outputs must use one root-label selection contract and classification generation guard through publication/activation. Implement this explicitly in Stages 5–6, before final verification.

## Stage 1 — Root classification storage

- Goal: durable machine ownership and correction state.
- Dependencies: revised Step 3 approval and revised planning commit before application edits.
- Expected changes: extend private SQLite for eligible root receipts, minimal score/model/content/rubric/threshold evidence, tags, suppression, pending application and generation; operations `recordEligibleRoot`, `apply`, `removeAndSuppress`, `rollback`.
- Verification approach: migration/restart, threshold boundaries, duplicate receipts, score-pruning independence and human-state isolation.
- Risks or open questions: separate persistent decisions from score/work retention; reject malformed/legacy evidence for new tags.
- Canonical components/API contracts touched: SqliteFastScoreStore or companion private store; new focused root-tag service/config.

## Stage 2 — Publication and worker integration

- Goal: ordinary scoring adds tags only to newly eligible roots.
- Dependencies: Stage 1.
- Expected changes: default-off configuration and threshold; capture eligibility on normal/signed local root publication, consume current successful meta result in the worker, retain failed application separately for provider-free retry. No new scoring trigger.
- Verification approach: both publication paths; score once/add once; replies/imports/backfill/old queues excluded; missing meta/provider failure; disable and changed configuration during work.
- Risks or open questions: persist eligibility before acknowledging enqueue; validate captured scoring configuration/content at creation, never reclassify existing tags.
- Canonical components/API contracts touched: RouteServices publication enqueue, FastScoreSweepService, workflow/config and CLI/task-worker factories.

## Stage 3 — Live tag discovery

- Goal: existing root tag presentation includes effective machine meta.
- Dependencies: Stage 2.
- Expected changes: shared indexed read-only attachment/join or bulk lookup; merge/deduplicate into root labels used by shared thread cards, tag index/detail, board/Forte/QDB/profile/search consumers. No live read-model copy or new styling.
- Verification approach: existing tag navigation, mixed-origin labels, hidden/retracted roots, bounded queries, counts/order, unchanged votes/visibility and unavailable private store recovery.
- Risks or open questions: preserve ordinary human-only reading; report unavailable classification rather than silently claim complete meta results. Do not attach private state to downloadable database connections.
- Canonical components/API contracts touched: shared tag read service, RouteServices, ThreadRepository/TagGrouping and shared root/detail consumers identified by prior investigation.

## Stage 4 — CLI correction and recovery

- Goal: operator removes mistakes without database edits.
- Dependencies: Stage 3.
- Expected changes: extend fast-score CLI with root meta status, remove-and-suppress, machine-only bulk rollback and application recovery using existing operator shell trust; configuration disables additions. No restore command or browser API.
- Verification approach: repeated/interrupted operations, suppression versus worker, rollback preserving human tags, restart and provider-free retry.
- Risks or open questions: already committed decisions still repeat failed invalidation; keep pending application visible and recoverable using retained evidence.
- Canonical components/API contracts touched: scripts/fast_score.php, v3 help, private classification service/store and existing worker status/retry.

## Stage 5 — Artifact freshness guard

- Goal: corrected tags cannot reappear from concurrent builds.
- Dependencies: Stage 4.
- Expected changes: generation-aware invalidation/publication contract for generated HTML, snapshots, shared refresh and static release activation; retryable regeneration. Reuse existing invalidation/locking patterns while keeping retraction protection.
- Verification approach: classification/correction during build, build-before-correction/activate-after-correction, partial invalidation retry and served-artifact probes.
- Risks or open questions: fixed lock order; generation checks must cover final activation, not just extraction. Never hold locks across provider requests.
- Canonical components/API contracts touched: StaticArtifactInvalidator, static builder/release publisher, offline publisher and root-tag generation guard.

## Stage 6 — Generated root tags

- Goal: public artifacts carry effective tags through existing root formats.
- Dependencies: Stage 5.
- Expected changes: apply shared merged root labels during static route enumeration/rendering and offline snapshot/update construction; keep existing offline root-label presentation/format. No score/evidence export or offline item-level schema/client feature.
- Verification approach: meta tag routes and offline filter, human/meta deduplication, exports containing labels but no scores/audit, no mutation of main live read model or canonical files.
- Risks or open questions: root-only output must obey existing visibility/size rules and generation guard; verify source connections never copy attached private tables.
- Canonical components/API contracts touched: StaticArtifactBuilder, PublicOfflineSnapshotBuilder, snapshot publisher and existing export paths. Core schema changes are not planned; if needed, follow CONTRIBUTING version/spec/prior-startup/rebuild requirements before dependent queries.

## Stage 7 — End-to-end verification

- Goal: prove the normal reader/operator journey.
- Dependencies: Stages 1–6 focused checks complete.
- Expected changes: extend existing Fastmod/browser fixtures and regression scenarios; fix only in-scope defects.
- Verification approach: publish/score/tag/discover/remove/retry/rollback with deterministic provider; phone/desktop, keyboard, long/empty/error content and affected themes; restart/rebuild; inspect generated static/offline results.
- Risks or open questions: no model-accuracy claim from fixtures; record unavailable checks as pending. No new navigation means no new icon mapping.
- Canonical components/API contracts touched: Fastmod browser harness, write/worker/tag/artifact regressions and shared existing presentation.

## Stage 8 — Operations and study closeout

- Goal: recoverable documented release with accurate outcome accounting.
- Dependencies: Stage 7.
- Expected changes: reference/runbook for enable/threshold/status/remove/retry/rollback, private backup and stable classifications; update summary and ledger, retaining original scope and reduced replacement separately.
- Verification approach: documented commands, appropriate regression suite, links/diff/scope checks and final pass/fail/pending inventory; no unsolicited deployment/live-model calls.
- Risks or open questions: Git alone cannot restore tags/suppression; record maintainer comfortable-fit judgment as pending unless supplied. Reduced release does not complete historical roots/replies scope.
- Canonical components/API contracts touched: reference/runbook, feature summary/debug log, effort ledger and relevant test commands.

## Execution and study checkpoint

Retain investigation commit `79bc519f` and earlier history. Revised implementation starts with a separate planning-only commit containing approved plans/log and necessary organization fixes; exclude summary/ledger changes until their authorized implementation commits. Then commit each numbered stage with its summary before the next; no amendments or squashing. The eight stages now separate artifact production from browser verification; no repeated exploratory stage is planned. Any material discovery requiring a scope/stage change must be recorded and replanned before dependent work.

E07, revised Step 3 handoff, UTC 2026-10-20; scope **v3**, R1 hashes reverified; Codex single exposed rater. Original start `4c521b743575979b8fefc66b38271551a57a08ef`; investigation baseline `79bc519f`; outcome/recovery as contracted above. **0.85 Railspike units reaffirmed**, total including original investigation/replanning, implementation, verification, documentation and ordinary correction. Root-label formats/shared UI are reused; no new reply snapshot/client or signed browser workflow. Private persistence, joins, CLI recovery and artifact guards remain included; not a remaining-work estimate or conversion from stage counts/hours. Original 0.85/0.95/1.20 snapshots remain historical and do not imply acceptance of the broader outcome.

Scope v3 Step 2 approved explicitly by user. Revised Step 3 awaits `Approved Step 3`; application work remains paused. This approval will apply to v3 only, not historical requirements below.

<details>
<summary>Historical Step 3 v2/A2 — superseded after the completed investigation</summary>


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

</details>
