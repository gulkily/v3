# History purge — Railspike debug log

## 2026-10-30T20:30:04Z — Step 1

### Necessary/useful instructions

- `docs/railspike/docs/workflow/step1_solution_assessment.md`, Understood Intent: the request named "your top suggestion", and inspection showed the suggestion's premise (undefined receipt fields) was already resolved by a sibling cycle. The section gave a sanctioned place to restate the actual open problem without rewriting the query. Retain.
- `docs/railspike/RAILSPIKE.md`, Experimental Effort Estimation, "preserve intake before detailed inspection": inspection had already happened for an earlier document in the session, so the rule forced an explicit disclosure that no pristine intake exists rather than a reconstructed one. Retain.
- `docs/railspike/docs/concepts.md`, Planning: listing concepts exposed that retraction has no inventory entry despite three completed cycles, which is useful signal for the next Step 2. Retain.
- `AGENTS.md`, Current retraction coordination: reading the handoff first prevented proposing a per-retraction immediate rewrite, which the design-mistakes checklist identifies as the earlier production failure pattern. Retain (host instruction, noted for context).

### Redundant/unnecessary instructions

- `docs/railspike/docs/workflow/step1_solution_assessment.md`, "Ultra-concise comparison document (≤1 page)": no recent Step 1 in this project fits one page once the required navigation, debug, study, estimate, concepts, enhancement and snapshot sections are present. Suggest clarifying the limit to the options-and-recommendation body, or dropping the page figure.

### Inapplicable instructions

- Step 1 "Share the document for review and stop": applicable and followed; noted only because the same user turn requested two non-Railspike documents, which the stop does not cover.

### Uncertain assessments

- The 0.80 projection rests on `filter-branch` performance on a QDB-sized repository, which was not measured. This is application uncertainty, not a workflow-instruction finding.
- Whether Step 1's option attribution per section (three signatures) is what the attribution contract intends for a single-pass artifact, versus the Step 3 single-footer rule. The contract is explicit for Step 3 only.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## 2026-10-30 UTC — Step 2

### Necessary/useful instructions

- `docs/railspike/docs/workflow/step2_feature_description.md`, Shared component inventory: forced enumeration of every consumer of a commit identity, which surfaced image backup manifests and interrupted-import journals as consumers the Step 1 had only partly listed. Retain.
- Same source, Known user or environment: the handoff names qdb.us, so the section produced a concrete sizing failure mode (rewrite longer than the affordable pause) and the preview-before-run flow that handles it unaided. Retain.
- Same source, Risks "earliest validation": placed the timing gate at Stage 1, before any rewrite code, which matches the retraction design-mistakes lesson about budgeting work on the production host. Retain.

### Redundant/unnecessary instructions

- Same source, "Concise doc (≤1 page)": the required sections (concepts, delivery scope, completion boundary, known environment, risks, inventory, flow, criteria, enhancement requests, checkpoint) cannot fit one page for an application change with history consumers. Same suggestion as Step 1: scope the limit or drop the figure.

### Inapplicable instructions

- Documentation-only scope rules: not applicable; this is an application change.

### Uncertain assessments

- Whether a `preview` that refuses to run while an interrupted import journal exists is a requirement or an implementation detail the guardrails say to avoid. Recorded as a requirement because it protects a documented recovery path.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## 2026-10-30 UTC — Step 3

### Necessary/useful instructions

- `docs/railspike/docs/workflow/step3_development_plan_do.md`, "split stories into vertical slices, not layers": kept the map record (Stage 2) and route resolution (Stage 5) as separate verifiable slices instead of folding them into the engine stage. Retain.
- Same source, known-environment replay requirement: produced Stage 7's named QDB-copy run as a deliverable rather than fixture-only acceptance. Retain.
- Same source, "Resolve each material risk before dependent work begins": placed the sizing gate in Stage 1 and the engine decision as an explicit report before Stage 3. Retain.

### Redundant/unnecessary instructions

- Same source, "<=1 page" and "Stages should be about <=1 hour or <=50 lines": seven stages with the required eight bullets each cannot fit one page, and the 50-line figure does not describe a Git plumbing stage with tests. Suggest dropping the page figure and restating stage size as "one reviewable commit".

### Inapplicable instructions

- Documentation-only variants of Completion Contract and stage guidance: not applicable.

### Uncertain assessments

- Whether an engine switch at Stage 1 (filter-branch to horizon squash) is "changed scope" requiring a return to Step 2, or an implementation discovery within the approved Step 1 contingency. The plan reports it for confirmation before Stage 3 either way.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## 2026-11-01 UTC — Step 4

### Necessary/useful instructions

- `docs/railspike/RAILSPIKE.md`, Step 4 commit cadence (one stage-scoped commit with the summary update before the next stage): kept each stage's evidence attached to its code, and made the Stage 1 engine switch and the Stage 7 entry-note correction visible as dated records instead of silent rewrites. Retain.
- `docs/railspike/docs/workflow/step4_implementation_do.md`, "report checks as passed, failed, blocked, pending, or inapplicable; a focused pass is never an unqualified full-suite result": forced the two load-sensitive failures to be rerun in isolation and on a clean `main` worktree before being classified, and kept the five inherited failures named in the closeout. Retain.
- `AGENTS.md`, implementation standards ("replay that condition or explicitly report it unverified"): drove Stage 7 to look again for a QDB-derived copy, which found one the Step 4 entry had missed, and to run the real command over HTTP rather than relying on route tests that cannot see headers. Retain (host instruction, noted for context).
- Extended estimates, separate retrospective stage-work assessment per stage: the Stage 3 revision at Stage 1 (0.20 to 0.15, engine pulled forward) and the Stage 5 retrospective (0.08, memoization risk did not materialize) were cheap to record and explain where the plan's uncertainty actually sat. Retain.

### Redundant/unnecessary instructions

- `docs/railspike/docs/workflow/step4_implementation_before.md`, re-reading the whole Step 3 plan at each stage: the plan's per-stage bullets were sufficient; the full re-read added nothing after Stage 2. Suggest "re-read the current stage and the Completion Contract" instead.

### Inapplicable instructions

- Documentation-only scope rules, UI copy audit tracking, navigation icon mapping: not applicable; application change without a navigation destination or UI text.
- Browser/theme verification matrix: not applicable; the only user-visible change is a plain-text source route response and a response header.

### Uncertain assessments

- Whether the Stage 7 entry-note correction (a QDB-derived copy existed after all) should count as a planning defect under the study's "corrections versus new requests" rule. Recorded as a correction to the entry note, not as new scope; the cycle score was not changed by it.
- Whether a dispatcher test that runs a full purge with a static build belongs in the ordinary suite (it adds several seconds). Kept, because it is the only automated coverage of the artifact-root branch.

*— Claude · 2026-11-01 UTC · Model: claude-fable-5-1*
