> **Feature plan:** [Step 1](./railspike_paper_step1_solution_assessment.md) · [Step 2](./railspike_paper_step2_feature_description.md) · [Step 3](./railspike_paper_step3_development_plan.md) · [Step 4](./railspike_paper_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract
- Deliver a protocol, evidence register, and feasibility report. Required sources: both repositories' workflow debug logs and `~/v3/docs/plans/local_server_dependency_recovery_railspike_failure_report.md`, requested at Step 2 approval.
- Documentation-only writes: `docs/research/railspike_paper/{protocol,evidence,findings}.md`, this feature's plans/summary, and required organization/index references. Sources remain unchanged. No runtime, instrumentation, generated artifacts, experiments, publication, or workflow changes; runtime/deployment checks are inapplicable.
- Finish with every frozen candidate accounted for and findings traceable, with denominators, unknowns, and human-review status. Record corrections; insufficient evidence requires specific follow-up needs.
- After approval: branch, organize plans under `docs/plans/railspike_paper/`, repair references, commit approved planning, then create the summary. Each stage requires verification, summary update, and commit before proceeding. Minimum: five commits.

## Key Risks
- **High risk:** Staged/modified v3 sources threaten provenance. Before extraction, freeze versions with UTC cutoff, revision/status, fingerprints, and necessary dated excerpts; never silently substitute newer content.
- **High risk:** Self-reports and overlap inflate evidence. Define corroboration/deduplication first; link the report and related log as one incident, preserving contradictions and corrections.
- Selection/missing-data bias misleads study design. Before extraction, inventory incomplete work and exclusions; mark unknowns. Review excerpt suitability; unresolved provenance blocks dependent claims.

## Stage 1
- Goal: Establish an auditable protocol and demonstrate it on the requested failure report (≤1 hour).
- Dependencies: Planning commit; source-status and evidence rules resolved.
- Expected changes: Create the three linked research documents; define questions, observation units, outcomes, and fixed inventory. Record the report's claims, related debug assessment, corroboration, and preliminary limits as a worked entry.
- Verification approach: Confirm both repositories and the named report appear; check provenance, links, and distinction between recorded test success and reported recovery failure.
- Risks or open questions: Duplicate narrative is not independent confirmation; test incident linkage before broader extraction.
- Canonical components/API contracts touched: Research documents; existing report, logs, plans, and verification records reused read-only; no API changes.

## Stage 2
- Goal: Complete Railspike's exploratory evidence and provisional findings (≤1 hour).
- Dependencies: Stage 1 verified and committed.
- Expected changes: Extend the register and findings across inventoried Railspike logs, retaining revisions, negative observations, task context, instruction versions, and review status.
- Verification approach: Reconcile candidates and dispositions; trace observations to sources; separate claims, corroboration, and unknowns; check document links and scope.
- Risks or open questions: Workflow-development tasks may dominate; label that population before generalizing.
- Canonical components/API contracts touched: Research documents; Railspike logs and history reused read-only.

## Stage 3
- Goal: Complete v3 evidence and cross-source reconciliation (≤1 hour).
- Dependencies: Stage 2 verified and committed.
- Expected changes: Extend register/findings with all inventoried v3 workflow logs and relevant linked evidence; reconcile the required report with installation/recovery logs without double counting. Account for copied installations separately.
- Verification approach: Check v3 inventory coverage, source versions, incident/revision links, evidence gaps, and unchanged source files.
- Risks or open questions: Later repairs cannot establish earlier success; preserve event order and distinguish reported from independently verified outcomes.
- Canonical components/API contracts touched: Research documents; v3 logs, report, plans, and recorded checks reused read-only.

## Stage 4
- Goal: Deliver a reviewable feasibility decision and future collection criteria (≤1 hour).
- Dependencies: Stage 3 verified and committed; unresolved evidence issues explicitly bounded.
- Expected changes: Finalize themes, denominators, contradictions, limitations, and collection requirements: task selection, reviewer role, outcome definitions, and stopping rules before future runs.
- Verification approach: Reconcile every candidate and quantitative claim; check navigation, provenance, whitespace, allowed paths, and source preservation. Confirm required v3 sources are represented and pending human review is visible.
- Risks or open questions: Sparse evidence cannot support efficacy conclusions; specify additional evidence needed rather than launch experiments or claim effectiveness.
- Canonical components/API contracts touched: Final research package and Step 4 summary only; no runtime surfaces.
