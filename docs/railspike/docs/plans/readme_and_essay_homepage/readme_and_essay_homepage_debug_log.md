# README and essay homepage — Railspike debug log

## 2026-10-12T19:24Z — Step 1 (solution assessment)

### Necessary/useful instructions
- `docs/workflow/step1_solution_assessment.md` § Structure (Original Query, Understood Intent): the query includes a question about process and a misspelled filename; both could be preserved while the intent section resolved them. Retain.
- `RAILSPIKE.md` § Key Rules (\"Recommend Step 1 for complex features or whenever multiple solutions exist\"): supported answering the user's one-shot-or-cycle question. Retain.
- § Warning Signs for Step 1 (>4 options): kept to three options. Retain.

### Redundant/unnecessary instructions
- The Original Query rules are stated in both `RAILSPIKE.md` § Key Rules and the Step 1 file with different wording. Suggest consolidating.
- The Approved Step 1 gate appears in three places. Suggest keeping one with links.

### Inapplicable instructions
- Plan organization and commit rules: nothing is committed at Step 1.
- Step 1 `## Review and revision`: no revision requested yet.

### Uncertain assessments
- Debug mode state for a new feature is not specified when the user was told the mode carries over and did not object; it was recorded as enabled. The rule that new workflows default to disabled conflicts with that expectation.
- The instructions do not say how to record a user question about workflow choice (one-shot or cycle) inside the Original Query; it was preserved verbatim.
- A request touching a file that a script parses by marker (`README.md`) has no guidance in the instructions; it was treated as a risk driving the options.

## 2026-10-12T19:28Z — Step 2 (feature description)

### Necessary/useful instructions
- `step2_feature_description.md` § Core requirements and § Risks: turned the user's \"no copy changes\" constraint into a testable requirement and a named drift risk. Retain.
- § Delivery scope (work type and allowed files): kept the README, script, and logo outside this feature after the revised plan. Retain.
- § Success criteria (measurable): produced a concrete verbatim check. Retain.

### Redundant/unnecessary instructions
- Completion boundary and Success criteria both carry release conditions. Suggest stating which section owns the verification checks.
- The reprint-instructions rule repeats the file's own header; a short summary was used.

### Inapplicable instructions
- Documentation-only clauses in Delivery scope and Completion boundary.
- API and data wording in the shared component inventory.

### Uncertain assessments
- The Step 2 \"Shared component inventory\" has little to say for a one-file text page; it was filled with the source file and carried-over metadata.
- The feature name (`readme_and_essay_homepage`) no longer matches the revised scope (essay only); filenames were preserved per the naming rule rather than renamed.
- Two decisions the user had not answered (a repository link, a heading) were written as requirements with defaults so they can be corrected in review.

## 2026-10-12T19:29Z — Step 3 (development plan)

### Necessary/useful instructions
- `step3_development_plan_do.md` § Structure (Completion Contract, Key Risks first): put the copy-drift and untracked-source risks ahead of any stage. Retain.
- Per-stage \"Early warning / validation\": produced a concrete diff-based check instead of a visual one. Retain.
- `step3_development_plan_after.md` item 3: confirmed the plan completes the Contract, including recovery. Retain.

### Redundant/unnecessary instructions
- The uncommitted-planning rule appears in the Before file, the After file, and `RAILSPIKE.md` § Commit discipline. Suggest consolidating.
- Stage sizing is stated as \"about <=1 hour\" and \"<=50 lines\". Suggest one measure.

### Inapplicable instructions
- Database and function-signature guidance in the Do file.
- Documentation-only Completion Contract variant.

### Uncertain assessments
- The instructions do not address verification against an input file that is untracked in the repository; a recorded hash was used as the evidence anchor.
- Three stages for a one-file page may be more ceremony than the work needs; the instructions have no lower bound or fast path other than skipping Step 1.

## 2026-10-12T19:31Z — Step 4 (implementation)

### Necessary/useful instructions
- `step4_implementation_before.md` § Planning Docs Commit Gate: produced a clean first commit while the untracked `railspike.txt` stayed out. Retain.
- `step4_implementation_do.md` § Stage Boundary Protocol: `git status --short` before each commit kept the source file out of stage commits. Retain.
- Step 3 per-stage \"Early warning / validation\": made the character-level diff the gate, which caught no drift but did catch a real 360 px overflow in Stage 2 through the width check. Retain.

### Redundant/unnecessary instructions
- One commit per stage with the summary update is restated in `RAILSPIKE.md` § Step 4 commit cadence, `step4_implementation_do.md` § Execution Rules and § Stage Boundary Protocol, and `step4_implementation_after.md` item 2. Suggest consolidating.
- `step4_implementation_after.md` item 6 overlaps the per-stage summary rule. Suggest consolidating.

### Inapplicable instructions
- Documentation-only verification clauses in the Do and After files: this was an application change.
- Migration, role, and database dependency checks in `step4_implementation_after.md` item 5.

### Uncertain assessments
- A verification-only final stage (Stage 3) needed its own commit carrying only the summary; the instructions do not say whether that is expected.
- The instructions have no guidance for a source file that the work depends on but must not commit (`railspike.txt`); a recorded hash was used as the evidence anchor.
- The feature folder name no longer describes the final scope (essay only) because the name was fixed at Step 1.
- Live-URL verification cannot occur before merge; recorded as pending rather than not applicable. Real OS dark-mode emulation was unavailable.
