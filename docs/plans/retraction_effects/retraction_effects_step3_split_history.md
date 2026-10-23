> Historical split investigation; current executable proposal: [Step 3](./retraction_effects_step3_development_plan.md).

> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/retraction_effects`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects).

## Completion Contract

Step 2 v4 is approved: browser Retract and operator `delete-record` withdraw all proven generated effects, preserve authored replies, recover historical orphans, and remain consistent through retries, imports, rebuilds and publication. Production acceptance on ZenMemes is separate from local fixture/browser evidence. **Planning disposition: user approved the split. This historical investigation is not an executable or completed Step 3 plan; renewed Step 2 v5 awaits approval.** Implementation remains unapproved.

## Key Risks

- **High risk: false ownership.** New hashtag labels have no explicit source-post link; the local history of the reported label starts with a bulk import, not a trustworthy one-post creation event. Validate archive/history recovery first; timestamp/author equality alone cannot justify deletion. Uncertain associations must remain inspectable and explicitly unresolved.
- **High risk: inaccessible surviving replies.** Physical deletion removes the root file; current indexing can seed thread summaries from a reply, while consumers expect a real root post. Validate root reconstruction and ordinary reply navigation before releasing physical root cleanup; do not promote a reply into the missing author's content.
- **High risk: resurrection or incomplete removal.** `delete-record` currently commits removal before its rebuild lock and has no resumable source-removal evidence. Existing retraction publication checks cover signed retractions, not an equivalent deletion lifecycle. Validate acceptance/locking, import and retry semantics before dependent consumer work.
- **Scope:** ownership, canonical removal evidence, root reconstruction, machine-output cleanup and deployment/browser checks exceed the workflow's approximately eight atomic stages/day boundary. Do not disguise these as large single stages.

## Approved split; first-cycle Step 2 awaiting review

1. **Source-owned label cleanup and conversation preservation.** First releasable application cycle: both normal removal entry points withdraw generated thread labels, recompute label-derived scores/order, preserve independent label actions and replies, retain missing-root/parent structure, and handle already deleted sources through inspection/repair. Include provenance, durable deletion evidence, repeat-safe retry, import/rebuild/publication consistency and the actual ZenMemes pin regression. Unknown historical attribution receives evidence-backed operator resolution, never automatic matching by timestamps. No claim that all other effect families are fixed.
2. **Remaining generated-effect cleanup.** Follow-on application cycle(s): inventory and close gaps for post reactions, generated subjects/event metadata, scoring/classification, expansions, pending jobs and their generated dependency chains. Reuse the first cycle's ownership/removal contract; preserve ordinary human and agent conversation. Include worker races, source-dependent outputs and existing LLM privacy guarantees. Independently retained contributions and irreversible external actions remain explicitly classified. Split further if atomic staging still exceeds the limit.

Each child needs its own approved feature description, atomic development plan and verification contract before implementation. The parent stays incomplete until the approved all-effects outcome is covered; children do not count as independent study successes. Proposed children are not yet enrolled as started cycles or approved. This split does not approve deployment or remote modification.

## Evidence and next action

Inspected canonical hashtag writes, deletion commit/rebuild ordering, read-model root construction, import dependency handling, retraction publication guards and expansion provenance. Tests available include `ReadModelPostRetractionsTest`, `PostRetractionScriptTest`, `ContentImportPlannerTest`, `ContentImportRecoveryTest`, `RetractionSweepTest`, `FastmodMetaTest` and existing retraction browser journeys; these are planning references, not newly executed checks.

The user accepted the split. Step 2 has been revised to v5 for the first cycle; obtain renewed `Approved Step 2` before producing its executable Step 3. Preserve the original all-effects contract and v1–v4 history; do not treat this artifact as `Approved Step 3` material.

## Study checkpoint

2026-10-23 UTC; v4 approved scope, split proposed rather than accepted; Codex single exposed rater; original baseline `e960f666e1146d26b2f9435d98a447c2594b9883`; R1/P2. Numeric projection remains unavailable pending exact frozen calibration report/diff recovery. This is a Step 3 investigation/split checkpoint, not a completed Step 3 handoff, retrospective effort or measured actual. New understanding of legacy provenance, missing-root representation and physical-deletion recovery motivates splitting; no narrower scope is silently substituted.

Split acceptance update, 2026-10-23 UTC: user said “i approve the split.” First-slice scope v5 is recorded in Step 2; original investigation and v4 checkpoint above remain historical. Split acceptance does not approve an executable Step 3 or implementation.
