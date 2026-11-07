# readable_page_urls debug log

## 2026-11-07T00:18:52Z - Step 1

### Necessary/useful instructions

- `step1_solution_assessment.md`, "≥2 solution options": the maintainer had asked "why not just use position". Writing Option A beside the `before`/`after` and plain-token options made the cost of dropping direction (a previous-page lookup) explicit. Retain.
- `RAILSPIKE.md`, Original Query preservation: the request spans several messages with three example URLs. Keeping them all records how wide the problem turned out to be. Retain.
- `concepts.md`, Planning: checking the static publication entry showed that later pages bypass first-page artifacts when a `position` query is present. That is a real cost of renaming the parameter in Option B. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `RAILSPIKE.md`, Follow-up Cycles Before Merge: not a follow-up cycle.
- `RAILSPIKE.md`, Directory structure rule: applies only after Approved Step 3.

### Uncertain assessments

- `step1_solution_assessment.md`, Original Query: the rule allows only mechanical fixes. Profanity was kept as written; the instruction does not say whether that is expected.

## 2026-11-07T00:49:18Z - Step 1 revision

### Necessary/useful instructions

- `step1_solution_assessment.md`, Review and revision: the maintainer changed a premise (repeated items are acceptable), not just a preference. Recording that under Revised Direction kept the Original Query intact and showed why the recommendation moved. Retain.
- `AGENTS.md`, read `PRINCIPLES.md` before weighing options: "Don't break what already works" turned old-token handling from a nice-to-have into a requirement shared by every option. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `RAILSPIKE.md`, Directory structure rule: applies only after Approved Step 3.

### Uncertain assessments

- `step1_solution_assessment.md`, Deliverable "≤1 page": four options, shared tasks and a long multi-message Original Query exceed one page. The limit does not say whether the preserved query counts toward it.

## 2026-11-07T01:35:42Z - Step 2

### Necessary/useful instructions

- `step2_feature_description.md`, Shared component inventory: inspecting the adapters showed Activity pages events, not posts, so `with` cannot apply there. That changed a core requirement. Retain.
- `implementation_standards.md`, recovery through ordinary entry: listing every bad input (page past the end, retracted `with` target, unreadable token) turned "old links keep working" into testable cases. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `step2_feature_description.md`, Known user or environment: the request names no user or machine.
- Documentation-only scope rules: this is an application change.

### Uncertain assessments

- `step2_feature_description.md`, Guardrails "no implementation details": the redirect status and per-row anchors are implementation choices, but each carries a user-visible risk. The guardrail does not say where a risk's mitigation may name a mechanism.

## 2026-11-07T01:42:32Z - Step 3

### Necessary/useful instructions

- `step3_development_plan_do.md`, vertical slices: starting with Recent alone, then adding `with` before other lists, means every later stage reuses a contract already proven end to end. Retain.
- `step3_development_plan_do.md`, canonical components bullet: reading `RouteServices` showed its redirect defaults to 303, which resolved the moving-target risk before planning, not during implementation. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `step3_development_plan_do.md`, known user or environment replay stage: Step 2 names no user or environment.
- Documentation-only scope rules: this is an application change.

### Uncertain assessments

- `step3_development_plan_after.md`, item 6 (keep planning uncommitted): this cycle's drafts are already committed to `plan/readable_page_urls` at stop-hook request. The instructions do not say whether that branch may become the Step 4 branch.

## 2026-11-07T02:16:44Z - Step 4

### Necessary/useful instructions

- `step4_implementation_before.md`, planning commit gate: a fresh branch from `main` kept the planning commit to approved plans only, even though drafts had been committed to a side branch at stop-hook request. Retain.
- `step4_implementation_do.md`, append a revision before dependent work: the offline reader's own tokens surfaced in Stage 6 through a failing browser test. Recording a dated revision there kept the original estimate honest. Retain.
- `implementation_standards.md`, check affected shared consumers: the offline reader, the high-traffic static bypass and the tag memory probe were all consumers outside the Step 2 inventory. Running each consumer's own test found them. Retain.
- `implementation_standards.md`, identify reproduced baseline failures: two browser tests fail without this cycle's changes. Re-running them on the earlier state separated those failures from regressions. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `step4_implementation_do.md`, documentation-only stage checks: this is an application change.
- Known user or environment replay: no user or environment was named.

### Uncertain assessments

- `step4_implementation_before.md`, "Only then create the Step 4 summary": the summary was created after the planning commit but together with Stage 1 work, not before it. The instruction does not say whether the summary must be committed alone before Stage 1.
- `step2_feature_description.md`, Shared component inventory: it asks for existing UI/API surfaces, but client scripts that parse URLs (the offline reader) are easy to miss. Consider naming client-side URL consumers explicitly.
