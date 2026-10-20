> **Feature plan:** [Step 1](./fastmod_rubric_actions_step1_solution_assessment.md) · [Step 2](./fastmod_rubric_actions_step2_feature_description.md) · [Step 3](./fastmod_rubric_actions_step3_development_plan.md) · [Step 4](./fastmod_rubric_actions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: E07 — [trial ledger](../effort_estimation_trial.md#e07--fastmod-rubric-actions)

## Status

Branch `feat/fastmod-rubric-actions`; planning commit `766deab1`. Stage 1 investigation complete; stage-atomicity gate failed. Stages 2–8 have not started. No application code, tag state, policy or provider calls changed. This cycle is not complete and does not count as an accepted study outcome.

## Study state

- Step 4 entry: **0.95 reaffirmed**, E07, scope v2 / architecture A2, reference R1; Codex single exposed rater, UTC 2026-10-20, starting revision `4c521b743575979b8fefc66b38271551a57a08ef`. This entry record follows read-only tracing; no independent or pre-inspection claim. Total includes planning, implementation, verification, documentation and ordinary correction; outcome/recovery remain the approved meta-only root/reply journey.
- Stage 1 discovery: **1.20 projected total**, same scope/reference/rater/date. New understanding, not additional scope: the offline reader needs a new item-level classification contract, not merely copying tags into an export; stale artifact publication must be guarded at build and activation. The signed settings, corrections and rollback UI are also distinct integrations. Comparable breadth to the R1 image/private-history boundary examples, beyond the comfortable startup-recovery anchor; not an hour conversion or a sum of components. Earlier 0.85/0.95 snapshots remain intact. Above 1.0 prompts explicit split discussion; it does not authorize extra implementation.

## Stage 1 - Fix the shared contracts

- Changes: organized approved plans and existing debug log, added plan index entry and committed planning separately. Recorded the concrete storage/read/publication lifecycle and source evidence below; no application implementation.
- Verification: read-only source tracing; temporary PHP/PDO probe verified exact reply targeting with read-only SQLite attachment, rejection of writes through the attached connection and visibility of a separately committed classification update without copying. EXPLAIN used covering indexes for tag/active/item and post identity. Probe files were removed. Planning link checks and `git diff --check` passed.
- Notes: the probe is feasibility evidence, not production wiring, performance measurement, WAL/concurrency assurance or browser acceptance. Runtime application, migration, browser and deployed-host checks are inapplicable to this investigation-only stage, and remain pending for the proposed feature. Stage 1's required sizing gate fails for Stage 7: it contains multiple production contracts and their acceptance work under a verification title. Do not start Stage 2 under the current breakdown.

## Chosen contracts and implementation boundaries

| Boundary | Contract and evidence |
| --- | --- |
| Score storage | Existing SqliteFastScoreStore stores the complete named score set in one private row keyed by post/content/rubric revision. `pruneBefore` removes old score/work rows. Persistent classification/correction/audit evidence must have independent retention. No generated-tag canonical record, signature pair or Git commit. |
| Model and reply evidence | FastPostScorer returns `source=llm` and rubric revision, not model/provider identity. FastScoreContextFactory hashes target post text; parent/root text is supplied separately and is absent from that hash. Store model/provider plus a fingerprint/manifest of the actual bounded scoring input; invalidate against changed dependencies without silently changing the advisory legacy score contract. |
| Live connection | RouteServices::pdo currently creates a fresh ordinary ReadModelConnection per call. Add a dedicated shared classification read service/read-only attached connection, not a global attachment to every writer/export connection. The probe supports indexed read-only joins. Resolve filtering/counts before pagination and decorate shared cards in bulk. Preserve human labels/reactions and origin separately. |
| Exact-item discovery | TagGrouping reads only `board_tags` and `thread_labels`; TagsPageController and its templates list thread cards. Extend a shared item-level tag result contract and exact post links. Forte groups use the same thread-only helper; do not tag a root simply because its reply matches. |
| Authority | Reuse the private prepared/signed Fastmod action verification and current root-approved operator checks in LocalWriteService/ToolsPageController. Extend the action contract for policy, suppress/restore, retry and rollback without canonical tag files; use durable action identity for idempotent replay. |
| Decision lifecycle | Serialize private-store policy/correction/worker decisions. Bind post/input/model/rubric/policy identity; commit retained evidence and classification before acknowledging application. Suppression survives rescoring; restore is conditional on current evidence. Disable freezes automatic mutations. Rollback advances a generation/cutoff, withdraws only machine-owned tags and prevents historical replay; a new eligible ordinary evaluation may classify later. |
| Presentation freshness | Decision generation changes invalidate affected generated pages/caches and enqueue offline regeneration. A failed invalidation is recoverable application work, not a failed score; retries must repeat invalidation even when tag state was already committed. No provider request is needed for current retained evidence. |
| Offline format and client | PublicOfflineSnapshotBuilder version 4 stores thread labels; posts have no machine-classification field/table. `offline_reader.js` boardRows/tagGroups/renderTagResult group threads only. Add versioned public item-tag data and reader behavior for replies, with legacy snapshot handling; selected visible posts only, no raw score/private audit export. Merely appending meta to root labels violates the approved target semantics. |
| Publication races | StaticArtifactBuilder, StaticArtifactReleasePublisher and PublicOfflineSnapshotBuilder guard retraction generation, not classification generation. Fastmod needs a separate guard/consistent snapshot around extraction, final publication and later release activation; corrections during builds cannot publish stale tags. Preserve existing retraction protection and lock order; do not lock across model requests. |
| Core schema | Live joins alone require no new main read-model table. If integration changes a core schema/query dependency, CONTRIBUTING's version/spec/prior-schema-startup/rebuild requirements apply. Offline format changes require their own version/client regression, not an unjustified core schema bump. |

## Stage-gate finding and reviewable alternatives

The approved plan's Stage 7 combines all of: offline snapshot schema/version and extraction; offline client item-level rendering/filtering and legacy behavior; static/offline build and release-activation generation guards; then phone/desktop/theme and failure verification. These are separate production/recovery boundaries, not one atomic verification stage. Stage 4 similarly combines worker integration with signed policy UI; Stage 6 combines correction, retry and interrupted rollback. The chosen joins simplify live state but do not remove these obligations.

- Recommended split for discussion: return to Step 2 and assess **root-only automated meta tagging** as the first releasable slice, retaining corrections/retry/rollback and coherent live/static/offline results. Add exact reply classification/discovery in a separate approved cycle. Root-only must be estimated and planned before assuming it fits; no child outcome automatically counts independently in the trial.
- Alternative: retain the full roots/replies outcome and explicitly approve a larger, revised stage budget after splitting offline format/client/publication work into atomic stages. This requires renewed planning, not silent extra stages or an invented approval from the 1.20 score.
- Preserve this cycle's original outcome/estimates and link any reduced/replacement scope. Do not declare the original E07 complete after a narrower release. No application work is authorized under this failed sizing gate.

## Pending requests and approvals

All accepted refinements remain: meta alone; no human-labeled accuracy gate; private SQLite scores/decisions; live read-only joins/bulk lookup; generation-time public artifacts; no per-tag canonical files. Stage 3 approval authorized Stage 1 investigation with its stop condition, not bypass of the gate. Await decision on revised scope or stage budget. Trial ledger changes remain uncommitted and reserved for authorized closeout.

## Scope revision requested after Stage 1

- Request: retain automatic meta tagging of scored top-level posts; explicitly omit reply tagging, browser controls and automatic reclassification. Disposition: user accepted the narrower direction; return to Step 2/3 for revised scope and fresh approval before application implementation. Outcome: pending replanning; original v2/A2 requirements and 1.20 discovery estimate remain historical, not overwritten.
- Public backup clarification: scores/private audit evidence are excluded, as already intended. Only effective tags/origin belong in public presentation/artifacts. Private database backup remains an operational concern, not a new public export feature.
- Further trims proposed, not yet approved: fixed `meta` mapping and initial 0.90 configuration; no generic action/rule engine, evidence viewer, restore command or automatic retry dashboard; use one CLI remove-and-suppress operation plus disable and machine-only rollback. Reuse existing worker retry/status and tag presentation. Retain minimal provenance, human-tag preservation, idempotence, durable suppression, signed-byte preservation, public/private separation and artifact freshness.
- Revised numeric scope estimate is pending a coherent Step 2/3 scope. Do not reuse the old 0.95 estimate as if it rated this reduced outcome; preserve original study scope and identify any approved replacement explicitly.


## Accepted scope reduction — replanning at Step 2

- User accepted all further trims, explicitly clarifying that new tag styling was never intended. The evidence-viewer/restore/dashboard/generic-rule proposals are deferred, not implemented. Current [Step 2 v3](./fastmod_rubric_actions_step2_feature_description.md) is the root-only replacement awaiting renewed approval. Previous scope/estimates and Stage 1 investigation remain intact.
- Revised projected total **0.85 Railspike units**, scope v3, R1, Codex single exposed rater, UTC 2026-10-20, including prior cycle work and replanning; the 1.20 discovery estimate still describes the original broader v2. No new implementation work or commits authorized by agreement to the trims alone.


## Root-only v3 implementation entry

Revised Steps 2 and 3 approved. New branch `feat/fastmod-root-meta`, revised planning commit `fe9578f9`; prior investigation retained. E07 Step 4 entry, scope v3, R1, Codex exposed single rater, UTC 2026-10-20: **0.85 total reaffirmed**, including prior investigation/replanning. The numbered stages below refer to v3, not the historical stopped plan.

## Stage 1 - Root classification storage (v3)

- Changes: added private SQLite root eligibility/classification/suppression store with minimal retained evidence, serialized decisions and dirty generation; fixed meta configuration contract. Independent from transient score pruning.
- Verification: FastmodMetaTest — 2 passed, covering inclusive threshold, replay, suppression, rollback, pruning and invalid/stale/backfill evidence. No core read-model change.
- Notes: no live tags or publication policy enabled; integration follows. Ledger remains uncommitted until closeout.

## Stage 2 - Publication and worker integration (v3)

- Changes: new-local-post receipt marks eligibility only when meta tagging is configured on; captures threshold/model/rubric/content identity. Ordinary worker results retain private application evidence and recover independently of provider work. Replies, old queues and backfill lack eligible receipts; backfill cannot supply application evidence.
- Verification: 13 focused meta/sweep tests passed; prior workflow tests also passed. New recovery test uses retained ordinary evidence without score rows and confirms backfill exclusion and suppression. PHP syntax check passed.
- Notes: no automatic reclassification; successful scoring survives application failure. Tag presentation and artifact invalidation follow in later stages.

## Stage 3 - Live tag discovery (v3)

- Changes: shared root-label reads use a request-local indexed public-ID join. Private state is read in bulk through a separate read-only connection; only IDs enter temporary request state, keeping private evidence inaccessible to user-entered SQL. Shared existing thread/card/tag/Forte/profile queries inherit merged labels. No persistent main schema/data copy or new styling.
- Verification: 4 meta tests passed, including effective labels, unchanged main human labels/scores and absence of private tables/attachments from the live connection. Joins precede filtering/counts. No core read-model schema/column dependency change.
- Notes: chose the approved bulk-read alternative to attaching private tables to programmable query connections. Generated artifacts are handled in Stages 5–6; CLI unavailable-state recovery follows.

## Stage 4 - CLI correction and recovery (v3)

- Changes: added meta-status, meta-remove --post-id, meta-rollback and meta-retry; registered default-off configuration and threshold environment/template keys. CLI uses existing operator shell trust; no browser controls. Missing initial private state does not break ordinary reading; operator logs identify initialization/restore action.
- Verification: 10 meta/private-config checks passed, including real CLI subprocess status/remove/rollback and persistent suppression. Existing score store/read model remain separate.
- Notes: explicit command status includes generation/dirty state; artifact flush wiring is Stage 5. Restore workflow remains excluded.

## Stage 5 - Artifact freshness guard (v3)

- Changes: private classification generation/epoch and publication lock serialize decisions against static/snapshot publication. Full/shared builds, release activation, per-route files and QDB aliases reject stale generation. Serving falls back from stale HTML; offline snapshots regenerate. Dirty decisions invalidate presentation and enqueue offline regeneration; acknowledgment occurs only after success, including CLI correction/retry.
- Verification: 12 focused meta/static/offline/private-isolation tests passed. New guard test rejects correction-during-build and older release activation; syntax checks passed. CLI queue isolation corrected to use FORUM_TASK_QUEUE_DATABASE_PATH; verified the existing workspace offline task remained the preexisting queued task, with no new task to undo.
- Notes: default/zero generation preserves legacy static behavior. Existing retraction guard and lock order retained. Snapshot root labels are wired in Stage 6.

## Stage 6 - Generated root tags (v3)

- Changes: static tag-route enumeration and offline snapshot/update extraction use shared effective root labels. Existing snapshot schema/version and offline presentation remain valid; no main read-model writes, new client format, scores or private evidence in public artifacts.
- Verification: 10 meta/static/offline/private-isolation checks passed. New real-snapshot regression verifies meta discovery labels, absence of private tables, unchanged source human labels and removal after correction.
- Notes: artifact generation tokens span extraction and publication. Existing schema is reused; core schema version/migration rules are inapplicable to these temporary read views.

## Stage 7 - End-to-end verification (v3)

- Changes: extended real-browser harness with isolated writable public/static roots, ordinary/signed root tagging, exact meta discovery, reply exclusion, CLI suppression/recovery/rollback and static/offline inspection. Closed crash window by committing successful score and retained ordinary application evidence together; runtime rechecks current enablement before applying tags.
- Verification: browser flow passed after final fixes, with deterministic local HTTP provider and real OpenPGP signed publication. At 390/1280 widths, light/dark/console/Word97/Chicago/Midnight discovery remained visible without overflow; screenshots saved under /tmp/fastmod-meta-*.png and phone Midnight/desktop Word97 inspected. No new styles/navigation. 68 focused tests passed before the final atomic evidence change; final 27 meta/sweep/store checks passed afterward, including rollback-on-failed evidence transaction and disabled-at-application behavior. Successful score/tagging left canonical repository HEAD unchanged.
- Notes: initial browser artifact fixture lacked service_worker.js; fixed isolated fixture copying and reran successfully. Broader PHP run is ongoing for closeout; four observed failures reproduced on preimplementation commit 79bc519f (approval/profile copy and old static-page progress expectations). No live external model/deployment claims.
