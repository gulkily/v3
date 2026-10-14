> **Feature plan:** [Step 1](./railspike_paper_step1_solution_assessment.md) · [Step 2](./railspike_paper_step2_feature_description.md) · [Step 3](./railspike_paper_step3_development_plan.md) · [Step 4](./railspike_paper_step4_implementation_summary.md)

Debug mode: disabled

## Problem
An empirical Railspike whitepaper needs traceable observations and measurable outcomes. Existing debug reports can guide research, but their completeness, corroboration, and selection biases must be assessed first.

## User Stories
- As maintainer, I want an auditable exploratory dataset so that I can judge whether a comparative study is feasible.
- As researcher, I want consistent collection criteria so that future observations can support defensible comparisons.

## Core Requirements
- State research questions about instruction usefulness, friction, and outcome verification; define observation units, inclusion/exclusion rules, and outcome categories before extraction. Treat existing material as exploratory, not a prospectively designed experiment.
- Freeze a dated inventory of both repositories' existing workflow logs and linked evidence at collection start. Account for every candidate, including incomplete and unsuccessful work; deduplicate installed copies and distinguish corrections from independent observations. Record source revisions or dated snapshots for uncommitted material.
- Produce a Markdown evidence register linking each observation to its source, workflow/step, task type, available agent/model/instruction version, and supporting artifacts. Separate agent claims, corroboration, and human review status; mark unavailable values unknown. Assess approval violations, scope deviations, rework, verification outcomes, and recorded human effort without estimating missing measurements.
- Summarize coverage, recurring themes, contradictions, negative findings, and evidence gaps with explicit denominators. Deliver a feasibility recommendation and prospective collection criteria, including task selection, human review, and stopping rules fixed before future runs; no causal or productivity conclusions from these records.

## Delivery Scope
**Documentation-only**, for the maintainer/researcher: create `docs/research/railspike_paper/{protocol,evidence,findings}.md` and maintain this feature's planning artifacts under `docs/plans/`, including its organization/index updates. Both repositories are read-only evidence sources outside that scope. Application/runtime changes, instrumentation, generated datasets/PDFs, new task runs, workflow changes, and publication are excluded. Comparative evaluation and the whitepaper remain subsequent cycles.

## Completion Boundary
Deliver the protocol, evidence register, and findings as a linked review package. Finish when the frozen inventory is accounted for, not when favorable results appear. Missing evidence may yield an “insufficient evidence” recommendation with specific collection needs. Correct disputed entries with traceable revisions; preserve source records and mark unresolved human review explicitly.

## Risks
- **Self-report and selection bias:** Could exaggerate effectiveness. Initial log review already shows selective reporting; before Step 3, retain corroboration labels, negative cases, and limits on generalization.
- **Missing or duplicated evidence:** Could distort counts or imply unmeasured effort. Initial inventory found ten logs plus installed copies; before Step 3, retain deduplication, fixed cutoff, and unknown-value rules.
- **Changing/private source material:** Could break provenance or expose unsuitable excerpts. Before Step 3, confirm source-status and publication boundaries; use minimal attributed evidence, flag restricted sources, and defer public release.

## Shared Component Inventory
Reuse existing debug logs, plans, summaries, Git history, and linked verification records as evidence; reuse the current debug contract without changing mode. No existing UI/API presents a research dataset; the new Markdown register supplies that missing documentation surface.

## Simple User Flow
1. Read the protocol and frozen inventory.
2. Inspect observations alongside supporting sources and uncertainties.
3. Review findings and decide what additional collection or comparative work to approve.

## Success Criteria
Every inventoried candidate has an inclusion/exclusion decision; every observation has provenance and evidence/review status; every quantitative summary names its denominator. The package identifies unanswered questions and a concrete next research decision without requiring new experiments to finish this cycle.
