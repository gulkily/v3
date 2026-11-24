# push_on_events — Railspike debug log

## 2026-11-24 UTC — Step 1 (solution assessment)

### Necessary/useful instructions
- `RAILSPIKE.md` → Experimental Effort Estimation, "Visible estimates": the per-option `Estimate:` lines made Option B's cost advantage (0.55) visible beside the recommendation, which matters because the maintainer accepts the queue as a fallback. Retain.
- `step1_solution_assessment.md` → Structure, "Original Query first ... do not summarize": the handoff carried the questions Step 1 must answer; keeping it verbatim let the assessment answer each one in its own section. Retain.
- `AGENTS.md` → "Before starting a fix or feature, search `docs/feedback/items/`": the search found no matching item, and the artifact records that. Retain.

### Redundant/unnecessary instructions
- `step1_solution_assessment.md` → Deliverable, "Ultra-concise comparison document (≤1 page)": with a long verbatim handoff as the Original Query, plus the study table, the page limit can't be met. Same tension as in `push_subscribe`. Suggest clarifying that the limit excludes the Original Query and study metadata.

### Inapplicable instructions
- `step1_solution_assessment.md` → Next, "/new or /clear" reminder: the conversation began with this Step 1 task, so it was omitted.

### Uncertain assessments
- `RAILSPIKE.md` → Planning Artifacts: steps stay uncommitted until Step 3 approval, while the maintainer has said he plans to commit each step as it is approved. This session had read-only repository access, so nothing was committed either way.

## 2026-11-24 UTC — Step 2 (feature description)

### Necessary/useful instructions
- `implementation_standards.md` → Before dependent work, "Identify all existing UI/API surfaces that already render the relevant data": checking who writes to `PrivateMessageStore` showed that history sync uses it too, so the DM hook moved to `PrivateMessageMailboxService::send()`. Retain.
- `step2_feature_description.md` → Known user or environment: naming the maintainer's next unaided step (Send a test, then the Deploy page's worker row) produced a concrete diagnosis path for "nothing arrived". Retain.

### Redundant/unnecessary instructions
- `step2_feature_description.md` → Deliverable "≤1 page" together with the required sections (risks, inventory, known user, study snapshots): the required content runs past one page for a cycle with several write paths. Suggest "as short as the required sections allow".

### Inapplicable instructions
- Documentation-only scope fields: application change.

### Uncertain assessments
- Approval with unanswered open questions: Railspike says nothing about how a bare "Approved Step N" treats open questions. This plan carried the assumptions forward and labeled them overrulable; a rule could make that the default.

## 2026-11-24 UTC — Step 3 (development plan)

### Necessary/useful instructions
- `step3_development_plan_do.md` → "Prefix usability, data, or rollback risks **High risk:**": it forced a second look at the read check, which found that `InboxMarkStore::mark()` creates a mark at "now" when none exists, a silent-failure risk now covered in Stage 2. Retain.
- `step3_development_plan_do.md` → "include a stage that replays each reported failure": Stage 5 carries the iPhone replay as pending, so desktop evidence can't close the cycle. Retain.

### Redundant/unnecessary instructions
- `step3_development_plan_do.md` → "Numbered plan (<=1 page)" with the full per-stage field list (goal, dependencies, changes, verification, estimate details, risks with three sub-bullets, canonical components): five stages with every field run well past a page. Suggest allowing risks to be one line when there is a single item, which the instructions half-allow.

### Inapplicable instructions
- Documentation-only fields: application change.

### Uncertain assessments
- Stage sizing "<=50 lines": Stage 1's pass with hold and cap rules may exceed 50 lines of code; it stays one stage because splitting the rules from the pass leaves neither testable alone.

## 2026-11-24 UTC — Step 4 (implementation)

### Necessary/useful instructions
- `implementation_standards.md` → "Check affected shared consumers and applicable roles": checking who can hold a push device found that any key, approved or not, can carry any username, so a device filter was added before the first notice could leak a DM sender's name. Retain.
- `step4_implementation_do.md` → Stage Boundary Protocol: one commit per stage kept the device-check refactor (Stage 1) reviewable apart from the hooks. Retain.
- `RAILSPIKE.md` → Planning Artifacts, "Do not add the ledger or summary to the planning commit": kept the planning commit to approved plans only. Retain.

### Redundant/unnecessary instructions
- `step4_implementation_do.md` → per-stage "Estimate details" bullet when nothing was revised: each stage repeats "original scope as in Step 3; no revisions". Suggest allowing one shared line in the closeout when no stage was revised.

### Inapplicable instructions
- `RAILSPIKE.md` → Follow-up Cycles Before Merge: new branch from `origin/main`.

### Uncertain assessments
- The Step 3 plan was approved against `6b66069d`; the branch started at `3dc254a` (two docs-only merges later). Railspike has no rule for a moved base between Step 3 and Step 4; the summary records both.
