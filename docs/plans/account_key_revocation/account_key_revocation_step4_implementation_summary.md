> **Feature plan:** [Step 1](./account_key_revocation_step1_solution_assessment.md) · [Step 2](./account_key_revocation_step2_feature_description.md) · [Step 3](./account_key_revocation_step3_development_plan.md) · [Step 4](./account_key_revocation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/account_key_revocation` — [P2 ledger](../effort_estimation_trial.md#cycle-v3account_key_revocation).

Status: Stages 1–3 complete; Stage 4 next; no revocation capability released.

## Execution and study entry

- User approved Step 3 after explicit signed cutoff revisions (scope v2). This authorizes implementation of the dependent revised contract. Option B/non-operator cross-user revocation remain deferred.
- Branch: `feature/account-key-revocation`; planning commit: `11e7f26fd`; original baseline: `82cef24f17de4b644497a7d9c38aaa54d27faf02`.
- Step 4 entry reaffirms Step 3: total projection unavailable pending exact frozen R1 feedback recovery; same scope v2/R1/P2, Codex single exposed rater. Prior snapshots retained; no invented scores or measured effort. Maintainer acceptance pending.
- Approved dependencies: existing platform/project facilities only; proposed and current dependency delta zero. HTML mockup retained, screenshots excluded. Pending user-authorized todo edits and accumulated ledger updates excluded from planning commit.

## Stage 1 - Historical authority and signed cutoff contract

- Changes: Added strict canonical revocation parser and [contract/inventory](../../specs/key_revocation_record_v1.md). Explicit signed cutoff is independent of acceptance/commit time. Historical derivation remains separate from current-access overlay; reviewed username is a retained claim, and both replacement approval and rename entry require protection.
- Verification: `php tests/run.php KeyRevocationTest` passed 2/2: missing/invalid cutoff and implicit acceptance-time substitution fail parsing; legacy multi-generation sponsorship derives identically in full/incremental paths. Existing naming writer accepts arbitrary normalized renames when enabled, confirming the need to protect reserved claims before UI work. Inspection of InvitationApprovalReplay shows accepted receipt facts can be retained independently of current issuer access; Stage 2 must verify invitation parity with revocation overlay. `git diff --check` passed.
- Notes: Risk gate design: preserve the historical graph, overlay terminal current-access state after historical attribution, and retain the target account claim in accepted revocation evidence. No stable-ID redesign selected; no existing records rewritten. If projection/rename/invitation fixtures disprove these rules, dependent implementation stops for rescoping. No browser, production or implemented access-denial claim at this stage. Parser is not wired to any writer yet.

## Stage 2 - Durable revocation evidence and projection

- Changes: Added signed-request/acceptance-receipt validation and terminal current-access overlay; retained historical approval separately for attribution, scoring and sponsorship. Schema 26 adds profile revocation facts and historical approval; profile lookup, trusted archive catalog and remote authority exclusions updated. Full/incremental metadata writes project the same evidence.
- Verification: 24 focused policy/receipt/history/archive-validation/content-import tests passed. Two additional tests passed: signed revocation preserves Bob's existing post and Casey's sponsored approval across full/incremental projection, retains distinct signed cutoff/acceptance times and rejects altered evidence; normal application startup from schema 25 with removed revocation columns rebuilds before profile lookup. `./v3 rebuild tests/fixtures/parity_minimal_v1 /tmp/account-key-revocation-stage2.sqlite3` passed; normal thread/profile lookup passed through the signed fixture. PHP syntax and `git diff --check` passed.
- Notes: An initial signed parity test found overlay wiring only in the no-Git incremental branch; moved projection to the shared metadata write and the rerun passed. Stage 1 commit: `f49f9fe6d`. Actual signed revocation API, reserved-claim checks and full privilege enforcement remain Stages 3–4. Trusted archive catalog validation is covered here; complete revocation archive restore/release rehearsal remains Stage 7. No production changes; dependency delta zero.

## Stage 3 - Signed operator acceptance and retained-claim protection

- Changes: Added authenticated root-operator prepare/finalize APIs with no-store responses, exact reviewed cutoff/context binding, acceptance-time live checks and terminal root-target rejection. Reused atomic acceptance commit/cleanup; exact signed retries recover committed revocation without a duplicate. Retained claims require explicit operator replacement regardless of the cross-account flag; revoked keys cannot be reapproved, root-seeded or renamed, and unrelated renames cannot join a retained username.
- Verification: Signed API test passed member denial, root-target denial, explicit cutoff preservation, one terminal event after exact retry, root-seed/reapproval denial and member/operator replacement policy. Existing committed-refresh approval recovery passed after shared atomic helper extraction. Policy/receipt/API-catalog regressions and signed invitation policy acceptance passed on focused rerun. `git diff --check` and PHP syntax passed.
- Notes: Exact-retry test exposed command-output newline normalization for empty-body canonical records; committed evidence now compares Git blob IDs against exact file hashes. Stage 2 commit: `bc4c40198`. Broader session/private/signed-write denial is Stage 4; UI and complete race/fault/browser acceptance remain Stages 5–7. No deployment or dependency change.
