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
