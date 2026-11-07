# theme_directive debug log

## 2026-11-07T11:45:50Z - Step 1

### Necessary/useful instructions

- `step1_solution_assessment.md`, "≥2 solution options": the codebase holds two directive precedents, `event:` derived on read and `title:` with a derived record. Writing both as options, with the parent's root header as a third, showed that the cheaper read-time pattern fits a theme. Retain.
- `RAILSPIKE.md`, Original Query preservation: the request was one line, so the parent query's page-theme sentence was added as labeled context, not merged into the query. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `RAILSPIKE.md`, Follow-up Cycles Before Merge: no branch reuse requested yet; the cycle's branch is decided after Approved Step 3.

### Uncertain assessments

- Effort estimation, starting point: this cycle depends on unbuilt work from `v3/engagify_theme`. The instructions do not say how to state a starting revision for a cycle that starts after another cycle's stage; the estimate assumes engagify page themes are delivered and records the planning revision.

## 2026-11-07T15:13:01Z - Step 2

### Necessary/useful instructions

- `step2_feature_description.md`, Risks with earliest validation: reading the canonical post record spec showed the client-signed subset rejects unknown headers, which turned the hidden form's main uncertainty into a named spec change. Retain.
- `step2_feature_description.md`, Shared component inventory: `Body-Format` is an existing optional header on threads and replies, so the hidden form has a precedent to follow instead of a new pattern. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `step2_feature_description.md`, Known user or environment: the request names no user or environment.
- `step2_feature_description.md`, documentation-only scope fields: this is an application change.

### Uncertain assessments

- Effort estimation, starting revision: the cycle starts from an unmerged branch (`feat/engagify_theme`). The instructions do not say whether a starting revision must be on the default branch; the branch commit is recorded.

## 2026-11-07T15:16:39Z - Step 3

### Necessary/useful instructions

- `step3_development_plan_do.md`, stage sizing (about one hour or 50 lines): the hidden form split into a record-format stage and a read-model stage instead of one stage touching eight components. Retain.
- `step3_development_plan_do.md`, `**High risk:**` prefix for data and rollback risks: the signed record format and the schema bump both qualified. Retain.

### Redundant/unnecessary instructions

- `step3_development_plan_before.md` and `step3_development_plan_after.md`, keep Step 1–3 docs uncommitted: this cycle's planning lives on its own `plan/` branch at the maintainer's request, so commits there do not reach a feature branch. The instruction does not anticipate a plan branch.

### Inapplicable instructions

- `step3_development_plan_do.md`, known user or environment replay stage: Step 2 names none.
- Documentation-only fields: application change.

### Uncertain assessments

- Stage 3 runs independently of Stage 1; the format does not say whether parallel stages need a different numbering or note beyond Dependencies.


## 2026-11-07T15:33:12Z - Step 4

### Necessary/useful instructions

- `step4_implementation_before.md`, Planning Docs Commit Gate: committing the approved plans first, before any code, kept the folder move and link repairs out of the stage commits and made each stage diff reviewable on its own. Retain.
- `step4_implementation_do.md`, Stage Boundary Protocol (verification recorded per stage): running smoke plus changed tests at each stage caught the base-branch `BoardListTest` failure early and kept it from being mistaken for a regression later. Retain.
- `read_model_schema_v1.md` (project rule surfaced through the shared implementation standards' reuse step), prior-version startup test: following the event-end-date precedent added a schema-50 startup test that the Step 3 plan had not listed. Retain; consider naming project schema contracts in the Step 3 template's verification field.

### Redundant/unnecessary instructions

- `step4_implementation_do.md`, retrospective stage-work assessment per stage plus a separate cycle retrospective: with six small stages, the per-stage retrospectives added a line each but the cycle retrospective was their sum in practice. Clarify whether the cycle retrospective should be judged independently, as the projection is.

### Inapplicable instructions

- `step4_implementation_do.md`, documentation-only verification: application change.
- `step3_development_plan_do.md` replay stage for a known user or environment: none named.
- `RAILSPIKE.md`, Follow-up Cycles Before Merge: this cycle uses a new branch based on an unmerged one, not branch reuse.

### Uncertain assessments

- Branching from an unmerged feature branch: the instructions cover reusing an unmerged branch for a follow-up, but not basing a new cycle's branch on another cycle's unmerged branch. This cycle recorded the base and merge order in the summary.
- `step4_implementation_do.md`, "never amend completed commits": a status edit in the Stage 6 commit touched three unrelated index lines; the fix went in a separate commit rather than an amend. The instructions do not say whether an unpushed final stage commit counts as completed.
