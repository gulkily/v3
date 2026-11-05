# calendar Railspike debug log

## 2026-11-04T14:10:00Z — Step 1

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Original Query first … do not summarize": the request arrived as a short opener plus six numbered answers; keeping both verbatim made the change from host-only marking to member claims visible. Retain.
- `concepts.md` Planning, "candidates by option": listing concepts per option exposed that the existing `Event-Date` header has no concept entry, which became the main reuse argument for Option A. Retain.
- `RAILSPIKE.md` Experimental Effort Estimation, intake disclosure: inspection preceded rating and is disclosed rather than presented as a pristine intake. Retain.
- AGENTS.md "Work items": searching and creating the item before planning surfaced a stalled post-back operation that blocked all frm.py writes; resolving it was a prerequisite the workflow would otherwise have hidden. Retain.

**Redundant/unnecessary instructions**
- Ledger prose entry, ledger index row and artifact snapshot repeat the same numbers; the index row was omitted this cycle in favor of the prose entry, as recent cycles do. Consolidate.

**Inapplicable instructions**
- Planning-commit organization rule and Step 4 branch rules: not reached in Step 1.

**Uncertain assessments**
- The Step 1 template asks for "≤1 page"; the Open questions list pushes this artifact slightly past that. Unclear whether open questions belong in Step 1 or should be deferred to the Step 2 intake. Suggest clarifying.

## 2026-11-04T14:30:00Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` "Shared component inventory: enumerate every existing UI/API surface": led to finding that `/compose/thread` already accepts an event date in its query, which removed a planned prefill change. Retain.
- "Known user or environment": forced the members-only and invite-link assumptions into a checkable statement with a recovery path for an unapproved guest. Retain.
- "Risks: earliest validation": placed the operator-only directive check before dependent stages instead of in final verification. Retain.

**Redundant/unnecessary instructions**
- "Completion boundary" and "Known user or environment" again overlap for a single-instance feature; the same recovery statement appears in both. Consolidate.

**Inapplicable instructions**
- Documentation-only scope fields: work type is an application change.

**Uncertain assessments**
- The guardrail "avoid database schema" sits awkwardly with the need to say whether a derived table is allowed; stated it as a scope allowance rather than a design. Suggest the template say where storage allowances belong.

## 2026-11-04T16:05:00Z — Step 2 (Revision)

Supersedes nothing; adds to the 14:30 Step 2 entry after the v2 scope revision.

**Necessary/useful instructions**
- `step2_feature_description.md` "Apply Enhancement Requests when revising scope; retain pending requests and approval status": the two maintainer questions were handled as a scope revision with a preserved v1 estimate rather than silently widening scope. Retain.
- `RAILSPIKE.md` "Re-estimate combined/revised options with stable identities and append scope/score changes": made the 0.65 → 0.80 change explicit and traceable. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Step 3 stage estimates: not yet reached.

**Uncertain assessments**
- Whether a mid-step scope revision warrants a new debug entry at all; the contract says phases and stages do not trigger reports but is silent on revisions. Logged briefly; suggest clarifying.

## 2026-11-04T17:00:00Z — Step 2 (Revision, v3)

**Necessary/useful instructions**
- `step2_feature_description.md` guardrail "A feature is a normal-flow vertical slice, not a component": generalizing to all event threads kept the slice (open a day, post, confirm) intact instead of turning into an events subsystem. Retain.
- `RAILSPIKE.md` "Preserve the Original Query; record the revised direction separately": the Step 1 Revision 2 note kept the approved assessment readable while pointing to the current scope. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Step 3 stage estimates: not yet reached.

**Uncertain assessments**
- Whether a direction change this size should reopen Step 1 rather than revise Step 2. The recommendation and option comparison still hold, so Step 2 was revised; the contract gives no size threshold. Suggest clarifying.

## 2026-11-04T18:00:00Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md` "split stories into vertical slices, not layers": kept the header, write path and read model as separately verifiable stages that each leave the site working, following the event-time precedent. Retain.
- "When Step 2 states a known user or environment recovery condition, include a stage that replays each reported failure": Stage 8 carries the invite-link replay rather than relying on local tests. Retain.
- `step3_development_plan_after.md` check 2 "about <=1 hour per stage": Stage 5 is the largest at 0.20 and was kept as one stage because the grid, query and derivation only make sense together; flagged in its estimate details. Retain.

**Redundant/unnecessary instructions**
- The plan template repeats "Risks or open questions" per stage while Key Risks sits at the top; most stages only point back. Suggest allowing "see Key Risks" explicitly. Consolidate.

**Inapplicable instructions**
- Documentation-only scope checks: application change.

**Uncertain assessments**
- "<=50 lines of change" per stage is not achievable for a schema column that touches six files; the hour bound was used instead. Suggest clarifying which bound governs.

## 2026-11-05T01:35:00Z — Step 4

**Necessary/useful instructions**
- `step4_implementation_before.md` "Planning Docs Commit Gate": the plans had been committed loose on `main` before approval. The gate's organization rule moved them into the feature folder in one planning commit, so the history reads cleanly. Retain.
- `step4_implementation_do.md` "one stage-scoped commit per completed stage" with the summary update: the stage boundary was where the unregistered Stage 1 tests and the stale signing expectations surfaced. Both were corrected in the next stage commit, not hidden by amending. Retain.
- `implementation_standards.md` "Check affected shared consumers … themes": the theme pass found Word97's unreadable confirmed days and the phone grid hiding half the week. Neither would have appeared in route tests. Retain.
- `RAILSPIKE.md` Enhancement Requests record (Request / Disposition / Approval / Outcome): the maintainer's three refinements, and the extension of whole-square links to every day, are traceable without a scope revision. Retain.
- `step4_implementation_after.md` item 7, "the handoff opens with anything unverified": kept the production replay visible as not done when the maintainer chose to merge before running it. Retain.

**Redundant/unnecessary instructions**
- The stage template's "Estimate details" line repeats "original scope unchanged; no revision" in every stage of a cycle with no revisions. Suggest allowing it to be stated once. Consolidate.

**Inapplicable instructions**
- Documentation-only verification rules: application change.
- Follow-up cycles before merge: no follow-up cycle; further work starts from `main`.

**Uncertain assessments**
- The Completion Contract makes production replay part of the release condition, but the maintainer can merge first. Recording it as "not done" and closing Step 4 seemed right. The contract does not say whether Step 4 may close with the release condition unmet at the maintainer's request. Suggest clarifying.
- The host's test runner lists test files explicitly. Railspike's verification steps do not ask the agent to confirm that new tests actually ran, which is how the Stage 1 tests went unrun. Suggest the stage boundary protocol require confirming that the named tests appear in the runner's output.
