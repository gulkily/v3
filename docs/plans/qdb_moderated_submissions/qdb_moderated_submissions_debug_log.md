# QDB moderated submissions debug log

## 2026-11-13T04:40Z — Step 1

**Necessary/useful instructions**
- `AGENTS.md` "Current retraction coordination": reading the handoff turned rejection cost into the deciding difference between Options A and B; retain.
- `implementation_standards.md` "Inspect existing implementations": surfaced `StagedSubmissionStore` and `/staged/` as the base for Option A instead of a new queue; retain.
- `AGENTS.md` "search `docs/feedback/items/`": no matching item, so none is named; retain.

**Redundant/unnecessary instructions**
- `RAILSPIKE.md` Experimental Effort Estimation: intake and Step 1 snapshots are identical when both follow inspection; suggest a single combined line, as noted in earlier cycles.

**Inapplicable instructions**
- Step 1 "/new or /clear" reminder: the conversation had no discussion before this Step 1, so it is omitted.
- Documentation-only scope rules: this is an application change.

**Uncertain assessments**
- Whether qdb quotes receive replies in practice was not confirmed; the scope default leaves replies unchanged.

## 2026-11-17T17:50Z — Step 1 Revision 1

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Review and revision" (recheck tradeoffs; record revised direction separately): the public-queue correction reversed the recommendation, and keeping the original options made the reason visible; retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Step 1 "/new or /clear" reminder: once per cycle; not repeated on revision.

**Uncertain assessments**
- Whether original qdb.us allowed votes on queued quotes is taken from the maintainer's description plus memory of bash.org; listed as a Step 2 default to confirm.

## 2026-11-17T18:05Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` "Known user or environment": tied acceptance to qdb.us on its shared host rather than a local run; retain.
- `implementation_standards.md` shared component inventory: found the single `qdb_page_eligible` rule in `QdbPage.php`, which shrinks the "missed views" risk; retain.
- `step2_feature_description.md` Risks (impact, validation, mitigation): surfaced the imported-archive problem, which would otherwise hide every existing quote; retain.

**Redundant/unnecessary instructions**
- Effort snapshot at Step 2 with an unchanged value repeats the Revision 1 snapshot; low signal, as noted in earlier cycles.

**Inapplicable instructions**
- Documentation-only scope fields: application change.

**Uncertain assessments**
- Moving rejection from deletion to a `rejected` tag changes a Step 1 default; recorded as a change for the maintainer to accept, not as approved scope.

## 2026-11-17T18:20Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md` "include a stage that replays each reported failure": produced Stage 6's qdb.us replay; retain.
- `AGENTS.md` "Navigation icons": a new Queue destination needs icons in both sets; this raised the projection; retain.
- `CONTRIBUTING.md` "Read-model schema changes": pushed the plan toward a query-time rule to avoid a schema bump, with an explicit fallback; retain.

**Redundant/unnecessary instructions**
- `step3_development_plan_do.md` per-stage "Estimate details" bullet duplicates the driver already implied by Expected changes; suggest making it optional for small stages.

**Inapplicable instructions**
- Documentation-only completion fields: application change.

**Uncertain assessments**
- Whether query-time evaluation is fast enough on the shared host is unknown until Stage 1 timing; the fallback needs a plan revision.

## 2026-11-17T19:05Z — Step 4

**Necessary/useful instructions**
- `implementation_standards.md` "Inspect existing implementations": led to `AttributionCutoff`'s signed switch-on and `ScoreVisibility::listedSql`, which replaced a planned private setting and three separate filters; retain.
- `step4_implementation_do.md` Stage Boundary Protocol: one commit per stage kept the summary current and the deviations dated; retain.
- `implementation_standards.md` "Replay each reported failure in the relevant user/environment": kept the qdb.us check marked pending instead of letting local Chrome acceptance stand in for it; retain.
- `AGENTS.md` "Navigation icons": produced the hourglass in both sets and a six-theme render check; retain.

**Redundant/unnecessary instructions**
- `step4_implementation_do.md` per-stage retrospective estimate lines: low signal when they match the original within 0.02; suggest allowing "as planned".

**Inapplicable instructions**
- Documentation-only verification rules: application change.
- `CONTRIBUTING.md` read-model schema checklist: the plan avoided a schema change, so no version bump was needed.

**Uncertain assessments**
- Whether pending quotes should also leave the instance page's "Recent included items" was judged out of scope, since it lists repository contents like Activity; recorded for the maintainer.
