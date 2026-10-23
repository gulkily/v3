# Managing production feedback

Start in the [request register](../plans/production_feedback_register.md), linked from the [planning index](../plans/README.md). It owns current request status and next actions for migrated batches. Dated summaries and triage are historical evidence; detailed feature plans retain implementation/approval records.

## Intake and identity

1. Search the register and linked older inventories for the reported outcome and source-post ID. Match by intended behavior and acceptance conditions, not shared keywords or shared source alone.
2. For a new outcome, allocate an unused permanent ID `PF-YYYYMMDD-Fnn` or `PF-YYYYMMDD-Bnn` using the intake date/category and next unused number. Never renumber or reuse retired IDs. Link the exact report, parent post and full thread; retain every batch/category/item alias, request nuance and import limitation. Label unknowns.
3. Add workflow state, delivery evidence/date/limits, completion checks, next action, inherited estimate/provenance, proposed workflow and existing plan/evidence links. Use the [minimal triage process](minimal_feedback_triage.md) for benefit/effort/uncertainty; preserve original estimates separately from revised scoped estimates. Scores and recommendations never authorize implementation.
4. Repeated reports of the same outcome add sources/aliases to the existing request. Distinct outcomes get separate linked requests. Uncertain duplicates stay separate with a review next action; older unreconciled inventories remain explicitly outside migrated coverage.

## Updates and closure

Update current fields in the register only; append dated evidence or a concise correction reason when changing claims. Detailed evidence stays in linked triage/feature documents. Historical status tables are snapshots and must link to the register, rather than become a second current-status list.

Workflow: **untriaged** needs scope/evidence review; **ready** has clear checks and a proposed next action; **planned** links an approved plan and its approval boundary; **in progress** has authorized active work; **blocked** names the unmet prerequisite and next recovery action; **closed** records the disposition and evidence. An inherited recommendation or a drafted plan alone is not approval. Local work awaiting deployment may remain blocked with deployment as its next action.

Delivery evidence: **unknown/pending**, **locally verified**, **deployed**, **production accepted**. Record applicable platform/surface, date and links; these are evidence claims, not automatic workflow transitions. Do not erase a known local result because deployment is unknown. Production acceptance requires evidence against this request's completion checks in the reported environment; adjacent improvements do not establish closure.

Close only with completion evidence and any required production acceptance, or an explicit maintainer disposition (declined, superseded, duplicate) linked to its reason/target. Preserve unresolved checks. Reopen on contrary evidence with the new report, prior closure context and next action. Posting a source-comment reply requires explicit authorization; it is not implied by maintaining this register.

## Corrections and recovery

Review diffs/Git history before correcting mistakes; avoid broad resets. For a mistaken merge, restore distinct request records and original aliases/sources, link the correction in both records and recheck coverage. A deliberate merge retains the retired ID as a closed duplicate pointing to the surviving request; transfer all aliases/reports. Splits preserve the original ID as a relationship record and link the separately scoped outcomes. Never silently delete identity or evidence.

Illustrative walkthroughs (not live actions): a new F07 report of the same default-port behavior adds a source to that request; an explicit-port behavior report stays separate. A scoped F03 with defined checks may move to ready, but implementation still needs authorization. Local F02 verification does not imply deployment; deployment evidence alone does not imply production acceptance. A contrary F02 report reopens any later closure. If F08/F09 were mistakenly merged, restore both outcomes with their shared source retained and document the correction.
