# Railspike research: exploratory findings and next collection

[Protocol](./protocol.md) · [Evidence register and frozen sources](./evidence.md) · [Implementation record](../../plans/railspike_paper/railspike_paper_step4_implementation_summary.md)

**Decision: proceed to a bounded prospective collection pilot; do not yet claim comparative effectiveness or write an efficacy paper.** The records support hypotheses about reviewable scope, useful checkpoints, repetitive instructions, and gaps between tests and user outcomes. They lack a comparison group, measured human effort, and independent review of this coding. A descriptive paper would be possible, but is not the selected empirical outcome.

## What was reviewed

The [capture window and inventory](./evidence.md#inventory) fix the reviewed working-tree versions. The inventory contains 42 diagnostic/planning candidates: 12 canonical workflow logs, one required failure report, one contextual application-debug handoff, four duplicate installed logs, and 24 excluded process/planning documents. Another 16 supporting artifacts were captured. Thus 34 exact text snapshots accompany the register; the 24 excluded planning documents have path/reason dispositions rather than snapshots. This is a census of the specified local candidate set, not all Railspike users or all historical tasks.

| Coverage measure | Count and denominator | Interpretation |
|---|---|---|
| Canonical logs coded | 12/12: R01–R06, V01–V06 | Six per project; copied installations excluded |
| Logs with a Step 4 report and captured summary | 11/12; all except V06 | Implementation reporting, not eleven successful outcomes |
| Planning-only workflows retained | 1/12: V06 | Recovery was not delivered at cutoff |
| Required failure report analyzed | 1/1: F01 | Linked to V04 incident I01, not an additional independent failure |
| Human-reviewed coding | 0/12 workflow rows | All pending; this analysis was produced by the research agent |
| Workflows with measured human effort in reviewed sources | 0/12 | Missing, not zero effort |
| Controlled with/without comparisons | 0/12 | No causal or comparative estimate possible |

Tasks comprise four documentation/process workflows (R02/R03/R05/V01), three static-site workflows (R01/R04/R06), and five application/integration workflows (V02–V06), totaling 12. The site tasks are related successive redesigns; installation and recovery are related too. Workflow rows are therefore not independent replications. Exact historical prompt versions and execution models are unresolved; one inspected cleanup commit attributes co-authorship to Claude Sonnet 5.5, which is attribution rather than verified runtime identity.

## Findings, evidence, and counterevidence

| Finding | Evidence and qualification | Consequence for study design |
|---|---|---|
| Plans and stage summaries make some decisions and corrections inspectable. | R02/R03/R05 and V01/V03 have inspected commit manifests supporting summary/change co-presence. Logs attribute benefit to the rules; manifests establish history, not that the rules improved quality or speed. | Measure artifact availability and reviewer reconstruction accuracy separately from completion quality. |
| Procedural completion and test success can miss the initiating user's outcome. | V04's original positive report is followed by F01 and the appended correction. S12 promises actionable manual guidance; S13 explicitly names the host/target mismatch. S14's 39 transaction records and 31 runtime-check strings concern the supported environment. F01 quotes the user's inadequate Ubuntu 22.04 recovery messages. | Define an acceptance journey for the known user environment before work; score it separately from implementation/test completion. Do not treat repeated accounts as repeated failures. |
| Repetition/formatting friction is a plausible hypothesis, not a measured cost. | See R01–R06 and V01–V05 coding for repeated guidance, reprints, page-size or stage-sizing concerns. R02 also credits a reprint with exposing the correct contract after repository recovery. Shared prompts and authorship can produce repeated opinions. | Record actual output volume, active review time, and missed constraints; test any simplification in a later controlled protocol rather than removing rules based on votes. |
| Correction history and partial verification must survive aggregation. | R05's checklist correction has a separate inspected commit; V02 records an offline follow-up; V05 records a targeted correction, nine reproduced baseline failures, and a blocked final full rerun. Its indexing benchmark measures the application, not the agent. | Keep rework, baseline failures, acceptance failures, and environment-blocked checks distinct. A completion label is insufficient. |
| Present records cannot reliably reconstruct approval adherence or elapsed effort. | R01 describes pre-planning deletion, R04 describes abbreviated reprints, R05 has approval/scope interpretations, V04 accepts a semantic advancement request. R06's Step 4 timestamp precedes Step 3. Complete conversations and timed reviews are absent. | Capture authorization events and configuration precedence prospectively; preserve unknowns rather than label every ambiguity a violation or infer durations from timestamps. |

The failure report's proposed instruction changes remain proposals. No observation demonstrates that reprint overhead caused its recovery failure. The original supported-platform results remain recorded evidence within their stated limits; the correction narrows what those results establish. The later recovery plan (V06/S15/S16) responds to the gap but does not prove the proposed remedy works.

## Proposed next cycle: collection pilot

This is a concrete proposal for separate approval, not scheduled or started work. Its purpose is to test measurement feasibility, not establish efficacy.

1. **Before enrolling work:** have an identified human review all 12 current workflow rows and incident I01 against the frozen sources. Record agreement, disagreements, and corrected definitions without replacing snapshots. Fix the pilot protocol/version before its first task.
2. **Selection:** prospectively enroll the first four eligible tasks in each of three categories: documentation, bounded bug fixes, and bounded user-facing features (12 intended tasks). Eligibility: an agreed observable outcome, access to relevant sources, and a task small enough for the existing one-day/eight-stage workflow limit. Log every screened task and exclusion before outcomes are known. Include aborted, unsuccessful, and stalled runs; do not replace them to improve results.
3. **Context:** use the same pinned Railspike instructions and debug policy for the pilot. Record repository/base revision, task request, acceptance criteria and known user environment, agent/model/tool versions, prompt/configuration references, task category, complexity assessment, session changes and interruptions. Unknown metadata stays unknown; necessary instruction changes start a labeled new cohort.
4. **Evidence:** preserve per-step artifacts, debug reports/corrections, approval events, final diffs, verification output, outcome evidence, and human review records. Separate agent self-report from artifacts and reviewer judgments. Record missingness and capture cost so the collection process itself can be assessed.
5. **Stop:** stop enrolling at 12 tasks or 14 calendar days after pilot start, whichever comes first; freeze outcomes at that deadline and label unfinished work accordingly. The cap is a proposed feasibility budget, not a powered sample size or a deadline for forced task completion. Publish the screened/enrolled/completed/aborted/missing counts even if some category quotas are unfilled. No automatic extension or favorable-result stopping.
6. **Exit decision:** assess whether a reviewer can apply every primary outcome definition from retained evidence and whether collection effort is tolerable. Unresolvable acceptance/authorization definitions or substantial missing primary outcomes trigger a revised pilot, not a comparative claim. A human must judge acceptable measurement burden and remaining gaps before approving the comparison.

## Outcome definitions to fix before the pilot

| Measure | Proposed observable definition | Missing-data rule |
|---|---|---|
| User outcome | Reviewer evaluates the prewritten ordinary-entry and required-recovery acceptance cases for the named environment; pass/fail/blocked per case. | Untested is unknown or blocked with reason, not pass. |
| Approval handling | Record each transition requiring authorization and the preceding approval, revision, or explicit scope instruction; adjudicate against the recorded instruction hierarchy. | Incomplete conversation means unassessable, not compliant or violated. |
| Scope deviation | Reviewer identifies changes outside the then-approved boundary, distinguishing subsequently authorized revisions. | Missing approved baseline makes scope unassessable. |
| Rework | Count correction events after an attempted implementation or completion claim; link initiating evidence, changed artifact, and resolution. Separate requested scope changes and environmental repairs. | No complete edit history means count is a lower bound, not total effort. |
| Verification | Preserve commands, results, environment, acceptance coverage, and known baseline failures; separate targeted, full-suite, and user-journey checks. | A summary without output is reported evidence only. |
| Human effort | Reviewer records active reading, clarification, editing, and verification minutes separately; pause the timer for idle/wait time. | Do not infer minutes from commit or log timestamps. |
| Output/agent cost | Record instruction/conversation volume and available elapsed/token/cost telemetry separately from human effort. | Unavailable telemetry remains unknown; output length is not cognitive burden. |

A second reviewer should adjudicate disputed primary outcomes and the key failure case. Review identities and disagreement resolution must be recorded. The collector cannot label its own reasoning as independent human review.

## Requirements for the later comparison

Use pilot findings to choose feasible primary outcomes and a justified sample size. Before comparison runs, fix comparable task selection, random assignment or matched allocation, time/review budgets, agent/model/tool configuration, baseline planning behavior, acceptance tests, analysis and stopping rules. The baseline must be described concretely (including native plan mode and ordinary safety/approval constraints), not merely named “without Railspike.” Use blinded outcome review where practical; account for carryover if the same task is attempted twice. Do not let a model/version change silently become the treatment effect. Debug collection may itself affect behavior and must be held constant where possible or explicitly treated as part of the evaluated package.

Report descriptive and negative results as well as uncertainty; avoid causal claims if allocation or missing data undermines them. A subsequent publication cycle can produce the paper/PDF once the evaluation and public-source review are complete. Existing locally frozen records need attribution/privacy review before release.

## Verification and correction status

The source register includes capture revisions, statuses and SHA-256 fingerprints; all 34 stored source texts matched their fingerprints. Active research/plan links and source anchors were checked separately from inert historical quotations. Selected immutable commit manifests corroborate narrow history claims. No application/runtime tests were executed for this research, and no sources were edited.

Live-source final checks found 31/34 original paths unchanged; three startup-recovery documents moved after capture. Two retained identical bytes and one changed only a relative link. The [movement record](./evidence.md#post-capture-source-movement) preserves that distinction; subsequent work does not revise this cutoff retrospectively.

No human has yet reviewed this research coding. A disputed row should receive a dated correction identifying the old interpretation, new evidence, and affected counts/conclusions. Preserve the snapshot and any unresolved alternatives. This review package is complete as an exploratory handoff; the pilot, comparison, and whitepaper remain later work.
