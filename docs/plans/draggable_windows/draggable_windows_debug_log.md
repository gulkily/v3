# Draggable windows debug log

## 2026-11-16T11:30Z — Step 1

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Understood Intent": the Original Query was three words, so this section carried the context from the earlier chat and the #draggable thread. Retain.
- `implementation_standards.md` "Inspect existing implementations": reading `theme-bubblegum.css` showed the title bar is a click-through pseudo-element, which shaped every option. Reading the theme script concept and `reading_margins.js` gave Option A its registry and its before-paint precedent. Retain.
- `AGENTS.md` "Pages must look final on first paint" and the no-JavaScript constraint: turned a remembered collapse into the main uncertainty for Options A and C. Retain.
- `AGENTS.md` feedback item search: confirmed no matching item and named the nearest one. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- "Known user or environment": no failure was reported. The iPhone SE demo use is noted for Step 2.
- Documentation-only scope fields.

**Uncertain assessments**
- The `/new or /clear` reminder: discussion preceded this Step 1 (the thread search and post earlier in the chat), so it was included.
- Intake estimate: given in the earlier chat before any code was read, so it is recorded as exposed rather than prospective.
- The ledger row is left pending because Step 1 stays uncommitted under staged commit discipline.
- R1 reference hashes were not re-verified this session.

## 2026-11-17T04:20Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` "Shared component inventory": finding the theme guide's note that theme script UI may appear after first paint, and `layout.php`'s reading margins check, turned the first-paint risk into a concrete early test. Retain.
- `step2_feature_description.md` Risks "earliest validation": made each risk name the test or screenshot that settles it before Step 3. Retain.
- `RAILSPIKE.md` effort snapshot "distinguish new understanding from changed scope": the move from 0.60 to 0.65 is recorded as new understanding. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only scope fields.
- "Known user or environment": no failure was reported. The maintainer's iPhone SE is named in the release condition only.

**Uncertain assessments**
- The Step 1 questions are answered as proposed decisions inside Core requirements, because the maintainer approved Step 1 without answering them. They are marked as changeable before approval.
- Removing two of the three drawn title-bar boxes changes a look the maintainer approved in the Bubblegum cycle. It is listed as a decision so he can veto it.

## 2026-11-17T04:35Z — Step 3

**Necessary/useful instructions**
- `RAILSPIKE.md` Enhancement Requests: the Preferences request arrived with "approved step 2". It was recorded in Step 2 as an in-scope refinement approved in the same message, before Step 3 used it. Retain.
- `step3_development_plan_do.md` "Resolve each material risk before dependent work": put the before-paint test in Stage 1, ahead of every button. Retain.
- `step3_development_plan_do.md` Completion Contract "deployment": made explicit that the deploy needs no schema change or rebuild. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Known-user replay stage: no failure was reported. The iPhone SE check sits in the release condition.
- Documentation-only scope fields.

**Uncertain assessments**
- The Preferences request came with the approval itself. It was treated as approved scope (v2) rather than sending Step 2 back for a second approval.
- Where the Float toggle sits in the header is left to a Step 4 screenshot review rather than decided here.
