# adaptive_nav debug log

## 2026-11-05T04:40:00Z - Step 1

### Necessary/useful instructions

- `RAILSPIKE.md`, Planning Artifacts, Step 1 structure (`## Original Query` then `## Understood Intent`): the request arrived as a short text message plus a voice conversation. Keeping the text as the Original Query and the voice decisions under Understood Intent kept the maintainer's words separate from the refinements. Retain.
- `RAILSPIKE.md`, Experimental Effort Estimation, standing enrollment: the host ledger's P2 policy meant no selection question was needed. Retain.
- `step1_solution_assessment.md`, "≥2 solution options": the maintainer had already chosen a direction by voice. Writing Options A and C still exposed a buildable first slice (A) inside the chosen option. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `RAILSPIKE.md`, Follow-up Cycles Before Merge: not a follow-up cycle.

### Uncertain assessments

- `step1_solution_assessment.md`, Original Query preservation: it does not say how to handle a request refined mostly in a voice conversation that has no verbatim transcript in the repository. This cycle summarized the voice decisions under Understood Intent. Consider clarifying whether that is the intended place.

## 2026-11-05T04:50:00Z - Step 2

### Necessary/useful instructions

- `implementation_standards.md`, "Identify all existing UI/API surfaces": inspecting the head pre-paint script and `account_menu.js` showed that More can reuse the You menu disclosure. Without that check, Step 2 would likely have proposed a new More page. Retain.
- `step2_feature_description.md`, Known user or environment: the maintainer named phones and uses an iPhone. The rule made the iPhone check an explicit acceptance condition instead of trusting a headless 375px run. Retain.
- `step2_feature_description.md`, Risks with earliest validation: it surfaced the feature-flag nav refresh and the Messages unread indicator, both learned from the earlier `viewer_nav_prepaint` cycle. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `step2_feature_description.md`, documentation-only scope fields: this is an application change.

### Uncertain assessments

- `step2_feature_description.md`, "Avoid implementation details": the shared component inventory requires source paths and reuse decisions. The line between a reuse decision and an implementation detail was a judgment call here (for example, reusing the You menu disclosure as More).

## 2026-11-05T05:05:00Z - Step 3

### Necessary/useful instructions

- `step3_development_plan_do.md`, "split stories into vertical slices, not layers": it shaped Stages 1–3 into a shippable fixed layout before any slot work, which is also the split point the Key Risks rely on. Retain.
- `step3_development_plan_do.md`, known-environment replay stage: Stage 8 exists because of it, and it names the headless pass as insufficient. Retain.
- `RAILSPIKE.md`, Key Rules, "Prefer solutions that avoid database migrations": pushed the slot state into browser storage. Retain. Revision 2026-11-05T05:15:00Z: the first draft chose a `Key: value` text format, reading the host's AGENTS.md rule too broadly; CONTRIBUTING.md allows JSON for internal state, and the maintainer's question led to the correction. This was a host-instruction reading error, not a Railspike finding.

### Redundant/unnecessary instructions

- `step3_development_plan_before.md` and `_do.md` both restate "keep Step 1–3 docs uncommitted"; `RAILSPIKE.md` Commit discipline says it a third time. One statement with links would do. Consolidate.

### Inapplicable instructions

- Documentation-only clauses in all three Step 3 files: application change.

### Uncertain assessments

- `step3_development_plan_do.md`, "Stages should be about <=1 hour or <=50 lines": Stages 4 and 5 may exceed 50 lines each because of the pre-paint script and the storage format. The plan keeps them because a further split would separate markup from the script that reveals it, which is a layer split the same file warns against. Unclear which rule wins.

## 2026-11-05T07:45:00Z - Step 4

### Necessary/useful instructions

- `step4_implementation_do.md`, Stage Boundary Protocol: committing each stage with its summary kept a clean record when Stage 4's high-traffic check failed after its commit; the fix went in as a second, labelled Stage 4 commit instead of an amend. Retain.
- `implementation_standards.md`, "Replay each reported failure in the relevant user/environment": it kept Stage 8 honestly pending rather than calling a headless 375px pass the user outcome. Retain.
- `implementation_standards.md`, "identifying reproduced baseline failures": running `lcd_palettes.mjs` and the pre-paint check against `main` separated two baseline problems (the LCD timeout, the 3px tab width) from this cycle's changes. Retain.

### Redundant/unnecessary instructions

- `step4_implementation_before.md` says "Do not add the ledger or summary to the planning commit"; `RAILSPIKE.md` Planning Artifacts says the planning commit "contains only approved Steps 1–3 ... and the moves, index entries, and reference fixes". The first draft of the planning commit included the ledger because the host's calendar cycle had done so; one statement, in one place, with the ledger named, would avoid the redo. Consolidate.

### Inapplicable instructions

- Documentation-only clauses in the three Step 4 files.
- One-shot transition rules.

### Uncertain assessments

- `step4_implementation_do.md`, "each stage <2 hours": Stage 2's browser-check updates for the duplicated Messages destination pushed it past the plan's estimate. The instruction gives no guidance on when a verification-only overrun should split a stage versus be recorded as a retrospective; this cycle recorded it.
- `step4_implementation_after.md`, "handoff opens with anything unverified": followed. It is unclear whether a pending known-environment replay should keep the cycle at "Step 4 in progress" or allow closeout with a pending stage; this cycle closed out with Stage 8 marked pending and the ledger saying so.
