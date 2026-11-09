# rebuild_availability debug log

## 2026-11-09T18:25:00Z — Step 1

**Necessary/useful instructions**
- `step1_solution_assessment.md`, "Where the time goes"-style evidence is not required, but the prior `delete_record_speed` Step 1 set that precedent; listing existing building blocks (candidate promoter, schema recovery launcher) changed the recommendation. Retain the habit; consider naming it in the Structure list.
- `RAILSPIKE.md` Original Query rule: preserving several messages kept the deploy two-step request visible as scope. Retain.
- Enhancement Requests: recording the checklist edit made before commit kept the committed checklist and this assessment consistent. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- The /new or /clear reminder: conversation preceded this Step 1, so it applies, but the user had just asked to continue in-session; advisory only.

**Uncertain assessments**
- The checklist was committed to `main` at the user's request before this Step 1. Railspike's commit rules cover step artifacts, not cross-cycle checklists, so this seemed allowed; the instructions do not say so explicitly.

## 2026-11-09T18:40:00Z — Step 2

**Necessary/useful instructions**
- `implementation_standards.md`, "Inspect existing implementations": checking `ReadModelCandidateBuilder::assertValid()` exposed that a write during a build could be lost by the swap, which became a core requirement and a risk. Retain.
- `step2_feature_description.md`, "Known user or environment": naming both hosts and the manual `./v3 rebuild` fallback gave the deploy step a testable recovery. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only scope fields: this is an application change.

**Uncertain assessments**
- The stop hook requires pushing drafts, while Railspike keeps Steps 1-3 uncommitted. Pushing to a non-main planning branch and stating "not approval" seemed the least conflicting path; Railspike does not address hooks.

## 2026-11-09T18:55:00Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md`, "replays each reported failure": the no-worker recovery became an explicit replay in Stage 8. Retain.
- "Stages should be about <=1 hour": splitting worker swap (Stage 4) from request behavior (Stage 5) kept the mid-build write risk isolated and testable. Retain.

**Redundant/unnecessary instructions**
- `step3_development_plan_before.md` asks to confirm Step 2 is approved and to keep plans uncommitted; the second conflicts with the session stop hook every turn. Suggest Railspike say how to handle an environment that requires pushes.

**Inapplicable instructions**
- Documentation-only stage rules.

**Uncertain assessments**
- The "<=1 page" limit: eight stages with all required fields run past one page. The field list and the page limit seem to pull against each other at this stage count.

## 2026-11-09T20:05:00Z — Step 4

**Necessary/useful instructions**
- `step4_implementation_do.md`, Stage Boundary Protocol: committing each stage with its summary made the Stage 3 and Stage 5 regressions (tests holding connections across a swap) traceable to one commit each. Retain.
- `implementation_standards.md`, "replay each reported failure in the relevant user/environment": kept host acceptance and the no-worker replay marked pending instead of letting local timings stand in. Retain.
- Enhancement Requests and "changed scope": the no-worker fallback contradicts a Step 2 requirement; recording it as a deviation to confirm, rather than silently, kept the approval boundary visible. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only verification rules.

**Uncertain assessments**
- Stage 1 was an operator-only preflight with no code. The rule "at least one commit per stage" produced a docs-only commit for it; that seems right, but the instructions do not say whether a no-code stage needs its own commit.
- Step 4 entry: the planning drafts had already been pushed to a separate branch for a stop hook. Recreating the planning commit on the feature branch from those drafts satisfied the gate, but the instructions do not cover pre-existing pushed drafts.
- The ledger checkpoint times written during this cycle were estimates that ran ahead of the clock; the instructions ask for UTC timestamps but no agent can read the clock without a tool call. Suggest saying "take the time from the system, not an estimate".
