# Open beside debug log

## 2026-11-17T18:03Z — Step 1

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Original Query" and "Understood Intent": the request was spread over a thread and several session messages, and the last one ("update step 1") assumed a Step 1 that did not exist. Quoting each message and stating the reading separately kept the two apart. Retain.
- `AGENTS.md` "inspect existing implementations": finding that pages are static files, that `layout.php` already sets state before paint, and that nothing blocks same-site framing made Option A possible without a server-side page variant. Retain.
- "Recommendation, including vertical-slice viability": led to recommending the smaller option as the first slice of the larger one, against the review's own preference. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only scope fields.
- "Known user or environment": no failure was reported.

**Uncertain assessments**
- Intake estimate: recorded as exposed, since it followed long discussion and code reading.
- The `/new or /clear` reminder: discussion preceded this Step 1, so it was included.
- The design notes were committed on a docs branch before this Step 1; under staged commit discipline Step 1 itself stays uncommitted until Step 3 approval.
- R1 reference hashes were not re-verified this session.

## 2026-11-17T18:11Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` "Known user or environment": the request's screenshot names Firefox at 2048px with margins 5 and 41. Writing the condition down showed that every browser test so far ran in Chrome, which became a risk with its own early check. Retain.
- "Shared component inventory": reading `theme-bubblegum.js` showed Float already hides itself by the frame's own width, so the Step 1 question about Float needed no new rule. Reading the ruler's CSS gave the pane its position from existing variables. Retain.
- "Risks: earliest validation": moved the framed-header check and the Firefox attempt to the front of Step 4. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only scope fields.

**Uncertain assessments**
- The Step 1 questions are answered as proposals inside Step 2, following the draggable windows precedent, not asked one by one.
- "≤1 page": the document runs longer, as recent Step 2 documents in this repository do.
- R1 reference hashes were not re-verified this session.

## 2026-11-18T02:42Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_before.md` "confirm no new scope was added after approval": fetching `main` showed the ruler had been rebuilt (PR #263) since Step 2. That is not new scope, but two phrases in approved Step 2 no longer matched the code. Recorded as a dated wording correction in Step 2 and as a Key Risk here. Retain.
- "Resolve each material risk before dependent work begins": put the framed-header check and the Firefox attempt in Stage 1 and the link-kind test in Stage 3, ahead of remembering the pane. Retain.
- "Known user or environment ... include a stage that replays each reported failure": Stage 6. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only fields; database changes.

**Uncertain assessments**
- "Stages about <=1 hour or <=50 lines": Stages 2 to 4 are likely larger. They were kept whole because each is one visible behaviour; the draggable windows plan made the same call.
- Whether a wording correction to an approved Step 2 needs its own approval. It is flagged in the handoff and rides on Step 3 approval.
- The feature branch is planned on top of an unpushed docs commit, which the planning-commit rule does not cover.
