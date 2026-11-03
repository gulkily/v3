# app_sessions — Railspike debug log

## 2026-11-03T01:45Z — Step 1 (solution assessment)

Mode: enabled from the project marker (new workflow, no explicit request). Agent: Claude (claude-opus-5-5).

### Necessary/useful instructions

- `RAILSPIKE.md` › Effort estimation, intake checkpoint ("Preserve initial scoped estimate ... before detailed implementation inspection"): forced recording 0.90 before opening session code, so the Step 1 projection is visibly a reaffirmation rather than a reconstructed intake. Retain.
- `step1_solution_assessment.md` › Structure, `## Original Query` verbatim: kept the user's long, multi-part request intact while the outline corrections live in their own section. Retain.
- `concepts.md` › Planning ("Mark missing entries as proposed"): surfaced that no session/sign-in concept exists despite many plans touching it. Retain.
- Host `AGENTS.md` › Principles ("Read `PRINCIPLES.md` before weighing options"): caught that the outline's "redirect http to https" contradicts the HTTP-alongside-HTTPS principle. Retain; the host instruction did work Railspike itself does not do.

### Redundant/unnecessary instructions

- `RAILSPIKE.md` › Experimental Effort Estimation and `effort-estimation.md` › Extended estimates / Estimate presentation: the same presentation rules (bold `Estimate:` lines, plain numbers, non-additive) are stated three times across the two files plus the Step 1 file. Suggest consolidating to one canonical section with links.
- Host ledger `effort_estimation_trial.md` › P2 plus `RAILSPIKE.md` › Continuing collection repeat worktree-deduplication rules irrelevant to a single checkout. Clarify as conditional.

### Inapplicable instructions

- Step 3/4 commit, branch and organization rules; one-shot mode; follow-up branch reuse.
- Third-party dependency inventory: no option proposes a dependency (all use PHP's built-in SQLite/HMAC).

### Uncertain assessments

- Warning sign "Step 1 >1 page" conflicts with a request that asked for options plus revocation, migration, https, outline corrections and related fixes. The artifact runs slightly over one page; it is unclear whether the limit should yield to explicit user scope. Suggest stating which wins.

## 2026-11-03T02:10Z — Step 2 (feature description)

Mode: enabled (inherited from Step 1). Agent: Claude (claude-opus-5-5).

### Necessary/useful instructions

- `step2_feature_description.md` › Known user or environment ("Evidence from a different supported platform does not satisfy this condition"): made the iPhone Safari check an explicit acceptance item instead of letting a Chromium replay stand in for it. Retain.
- `step2_feature_description.md` › Guardrails ("A feature is a normal-flow vertical slice"): pushed the completion boundary onto ordinary sign-in entries and Deploy POSTs rather than "a new session store". Retain.
- `RAILSPIKE.md` › Enhancement Requests: kept the Step 1 inventory's deferred client items visible as out-of-scope rather than silently dropped. Retain.

### Redundant/unnecessary instructions

- `step2_feature_description.md` › Effort estimation repeats the presentation and attribution rules already given in `RAILSPIKE.md` and `effort-estimation.md`. Suggest a single link.

### Inapplicable instructions

- Documentation-only scope rules: this is an application change.

### Uncertain assessments

- "Avoid ... database schema" vs. a new session store: the store is unavoidable for Option A. The description names it without schema detail; it is unclear whether Railspike expects an explicit schema-exception note at Step 2.

## 2026-11-03T02:30Z — Step 3 (development plan)

Mode: enabled (inherited). Agent: Claude (claude-opus-5-5).

### Necessary/useful instructions

- `step3_development_plan_do.md` › Additional requirements ("include a stage that replays each reported failure"): produced Stage 6's same-host http/https replay, and the rule that it must fail on `main` first, instead of relying on unit tests of cookie names. Retain.
- `step3_development_plan_do.md` › Guardrails ("split stories into vertical slices, not layers") together with the stage commit cadence: forced a behavior-neutral test-helper pre-pass (Stages 2–3) so each stage commits with a passing suite rather than a broken mid-cutover state. Retain.
- `step3_development_plan_do.md` › Key Risks `**High risk:**` prefix: made the sign-everyone-out cutover visible at the top. Retain.

### Redundant/unnecessary instructions

- `step3_development_plan_after.md` › Effort estimation and the Do file's Effort estimation repeat the non-additive and stage-work semantics. Suggest stating them once.

### Inapplicable instructions

- Documentation-only Completion Contract variant.

### Uncertain assessments

- Stage sizing "<=1 hour or <=50 lines": Stage 4 is mostly deletions and exceeds 50 lines. The plan adds a split trigger on non-deletion lines; it is unclear whether deletions count toward the limit.
- One-page limit: seven fully specified stages with extended estimates exceed one page. Unclear which rule should yield.
