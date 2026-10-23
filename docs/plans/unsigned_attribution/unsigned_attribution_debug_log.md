# unsigned_attribution Railspike debug log

## 2026-10-21T21:06Z · Step 1 (solution assessment)

### Necessary/useful instructions

- `RAILSPIKE.md` Experimental Effort Estimation, "Enroll at the first artifact" and the intake row ("disclose earlier inspection instead of pretending it was prospective"): under standing enrollment P2, the cycle had to be enrolled without asking, and the checklist's 1.0 had to be labelled as inherited and exposed. Without this, the inherited number could easily pass for a fresh intake. Retain.
- `step1_solution_assessment.md` "Understood Intent": the query was three words ("Start Cycle A"). Linking the checklist section kept the Original Query intact while making the scope recoverable. Retain.
- `RAILSPIKE.md` Key Rules, "A feature is a releasable vertical slice: normal entry, end-to-end outcome, and required recovery": this pushed the assessment to cover the legitimate paths (reply agent, browser hint-only votes, queued offline votes), not only the attack. Retain.

### Redundant/unnecessary instructions

- `docs/reference/railspike_workflow.md` "Local experimental effort-estimation patch" still says "Selection must be explicit" and "Unrelated cycles need no estimates". The 2026-10-21 continuing-collection section at the end supersedes this. Reading both costs a reconciliation step each cycle. Suggest consolidating that paragraph to point at P2.

### Inapplicable instructions

- `RAILSPIKE.md` Follow-up Cycles Before Merge: this is a new cycle on a new branch (when one is created); there is no unmerged predecessor branch.

### Uncertain assessments

- `docs/railspike/docs/effort-estimation.md` asks for "verified model identity if known". The rater identity is recorded from the session's model ID; whether that counts as "verified" is not defined.

## 2026-10-23T06:46Z · Step 1 revision 2 (Option E)

### Necessary/useful instructions

- `docs/railspike/docs/effort-estimation.md` "Keep initial scores immutable and append revisions" and "reason (scope change, new understanding, or reaffirmation)": the move from Option A to Option E had to be recorded as a scope change (v1 → v2), with A's 0.80 superseded, not overwritten. Retain.
- `docs/railspike/docs/effort-estimation.md` "Scores above 1.0 prompt a split discussion": full E is about 1.35, so the revision proposes a split instead of a single oversized cycle. Retain.
- `CONTRIBUTING.md` "Read-model schema changes": unverified vote counting changes the derived read model, so the assessment names the schema obligations up front. Retain.

### Uncertain assessments

- Whether a ruled-out option's projection counts as "revised" or "superseded" is not defined. It is recorded as superseded with the reason, and the scope version is incremented.

## 2026-10-23T10:21Z · Step 2 (feature description)

### Necessary/useful instructions

- `step2_feature_description.md` "Shared component inventory: enumerate every existing UI/API surface that already renders the data": listing the surfaces turned up the existing per-record signature status for thread labels and post reactions, which can serve as the verified/unverified source instead of a new indicator. It also exposed that session-authenticated votes are unsigned, a gap Step 1 had not drawn out. Retain.
- `step2_feature_description.md` Risks "impact, earliest validation, mitigation": forced each risk (score shift, session votes, agent breakage) to name a check that can run before Step 3. Retain.
- `RAILSPIKE.md` Experimental Effort Estimation "distinguish new understanding from changed scope": the 0.65 → 0.70 change is recorded as new understanding under the same scope v2. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- `step2_feature_description.md` "Known user or environment": the request names no specific user, machine or environment.
- Documentation-only scope fields: this is an application change.

### Uncertain assessments

- Effort snapshots record a starting revision, but this repository's history was rewritten between Step 1 and Step 2, changing commit IDs. Recording both old and mapped IDs seemed right; the contract does not cover renamed revisions.
- `step2_feature_description.md` "Concise doc (≤1 page)": open questions inherited from Step 1 had to be answered here, which pushes the document toward the limit.

## 2026-10-23T14:17Z · Step 2 revision 2 (scope v3)

### Necessary/useful instructions

- `RAILSPIKE.md` Enhancement Requests, "Changed scope: … return to the earliest affected planning step": accepting unsigned attributed posts reverses a core requirement, so it was handled as a Step 2 revision with a new scope version, not folded in silently. Retain.
- `RAILSPIKE.md` Experimental Effort Estimation, "Append revisions and retain original scope/scores": the 0.70 (v2) snapshot stays; 0.80 (v3) is appended. Retain.

### Uncertain assessments

- The revision came from checking the plan against a project principles document written mid-cycle. Railspike has no step for re-checking an in-flight plan when project principles change; doing it here worked, but the trigger was ad hoc.

## 2026-10-23T14:28Z · Step 1 restart (new assessment under PRINCIPLES.md)

### Necessary/useful instructions

- `step1_solution_assessment.md` "Original Query … preserve the request": the restart request was one line; preserving it and putting the cycle context in Understood Intent kept the new assessment honest about why it exists. Retain.
- `step1_solution_assessment.md` "≥2 solution options … Recommendation, including vertical-slice viability": forced the device-token follow-up to be compared against a signature-based alternative instead of being carried forward. Retain.
- `RAILSPIKE.md` Experimental Effort Estimation, "Copies … are the same observation" and "preserve … replaced and incomplete cycles": supported keeping the same cycle ID and preserving superseded snapshots. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Uncertain assessments

- Railspike has no defined way to restart a cycle at Step 1 after Step 1 was approved. Renaming the earlier artifacts with `_superseded` and keeping the cycle ID was a local choice; whether a restart should get a new cycle ID or a numbered artifact set is not specified.

## 2026-10-23T14:35Z · Step 2 (feature description, after restart)

### Necessary/useful instructions

- `step2_feature_description.md` "Shared component inventory": confirmed the existing per-record signature status can supply both the verified rule and its wording, so no new UI text is needed. Retain.
- `step2_feature_description.md` Risks "earliest validation": checking for existing rate limiting before committing to per-client limits found none in the codebase, which changed the decision. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- "Known user or environment": no specific user, machine or environment is named.

### Uncertain assessments

- The Step 2 template has no slot for answering questions that Step 1 deferred to Step 2. A "Decisions from Step 1" section was added; the template could name it.

## 2026-10-23T14:42Z · Step 3 (development plan)

### Necessary/useful instructions

- `step3_development_plan_do.md` "Resolve each material risk before dependent work begins": turned the score-shift risk into a Stage 1 audit with an explicit review gate before any scoring change. Retain.
- `step3_development_plan_do.md` "Canonical components/API contracts touched": surfaced reuse of the existing signature check, post signature audit script and feature flag registry instead of new components. Retain.
- `CONTRIBUTING.md` "Read-model schema changes": its four obligations map directly onto Stage 3's verification. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Uncertain assessments

- `step3_development_plan_do.md` asks for a plan of at most one page and also eight fully specified stages with six fields each. With eight stages, both limits can't be met; the plan stays at eight terse stages and runs slightly over a page.

## 2026-10-23T15:20Z · Step 2 revisions 2 and 3 (changed scope during Step 4)

### Necessary/useful instructions

- `RAILSPIKE.md` changed-scope rule (return to the earliest affected step): the cutoff, and then the default-off flag, each altered core Step 2 requirements after Stage 1, so implementation paused and Step 2 was revised before Step 3. Retain.
- Step 3's Stage 1 review gate: the audit surfaced that the planned rule would unverify most of zenmemes' history, before any scoring change shipped. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Uncertain assessments

- Two scope changes arrived in quick succession at the same gate. Each got its own revision and effort snapshot; Railspike does not say whether back-to-back revisions before re-approval need separate snapshots.

## 2026-10-23T16:10Z · Step 4 Stage 2 (flag and cutoff)

### Necessary/useful instructions

- `CONTRIBUTING.md` UI verification (phone and desktop, themes, failure states): the browser fixture checked the move failure path keeps both the entry and the displayed cutoff, and the first switch-on reveals the cutoff without reload. Retain.
- `PRINCIPLES.md` "signatures are the authority": reading the existing flag-change write path showed switch-ons are already operator-signed, so the cutoff came from that record instead of the unsigned server file Step 3 named. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Uncertain assessments

- Railspike does not say whether a plan's named file path, replaced by an equivalent signed source meeting the same Step 2 requirement, needs renewed Step 3 approval. Treated as an implementation detail and recorded as a deviation in the Step 4 summary.
