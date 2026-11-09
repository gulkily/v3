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
