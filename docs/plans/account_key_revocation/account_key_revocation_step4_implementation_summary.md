> **Feature plan:** [Step 1](./account_key_revocation_step1_solution_assessment.md) · [Step 2](./account_key_revocation_step2_feature_description.md) · [Step 3](./account_key_revocation_step3_development_plan.md) · [Step 4](./account_key_revocation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/account_key_revocation` — [P2 ledger](../effort_estimation_trial.md#cycle-v3account_key_revocation).

Status: Stage 1 complete; Stage 2 next; no revocation capability released.

## Execution and study entry

- User approved Step 3 after explicit signed cutoff revisions (scope v2). This authorizes implementation of the dependent revised contract. Option B/non-operator cross-user revocation remain deferred.
- Branch: `feature/account-key-revocation`; planning commit: `11e7f26fd`; original baseline: `82cef24f17de4b644497a7d9c38aaa54d27faf02`.
- Step 4 entry reaffirms Step 3: total projection unavailable pending exact frozen R1 feedback recovery; same scope v2/R1/P2, Codex single exposed rater. Prior snapshots retained; no invented scores or measured effort. Maintainer acceptance pending.
- Approved dependencies: existing platform/project facilities only; proposed and current dependency delta zero. HTML mockup retained, screenshots excluded. Pending user-authorized todo edits and accumulated ledger updates excluded from planning commit.

## Stage 1 - Historical authority and signed cutoff contract

- Changes: Added strict canonical revocation parser and [contract/inventory](../../specs/key_revocation_record_v1.md). Explicit signed cutoff is independent of acceptance/commit time. Historical derivation remains separate from current-access overlay; reviewed username is a retained claim, and both replacement approval and rename entry require protection.
- Verification: `php tests/run.php KeyRevocationTest` passed 2/2: missing/invalid cutoff and implicit acceptance-time substitution fail parsing; legacy multi-generation sponsorship derives identically in full/incremental paths. Existing naming writer accepts arbitrary normalized renames when enabled, confirming the need to protect reserved claims before UI work. Inspection of InvitationApprovalReplay shows accepted receipt facts can be retained independently of current issuer access; Stage 2 must verify invitation parity with revocation overlay. `git diff --check` passed.
- Notes: Risk gate design: preserve the historical graph, overlay terminal current-access state after historical attribution, and retain the target account claim in accepted revocation evidence. No stable-ID redesign selected; no existing records rewritten. If projection/rename/invitation fixtures disprove these rules, dependent implementation stops for rescoping. No browser, production or implemented access-denial claim at this stage. Parser is not wired to any writer yet.
