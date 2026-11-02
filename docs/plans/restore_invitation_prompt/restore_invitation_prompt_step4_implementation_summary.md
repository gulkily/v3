> **Feature plan:** [Step 1](restore_invitation_prompt_step1_solution_assessment.md) · [Step 2](restore_invitation_prompt_step2_feature_description.md) · [Step 3](restore_invitation_prompt_step3_development_plan.md) · Step 4
Debug mode: enabled
Effort study: railspike-effort / v3/restore_invitation_prompt; [ledger](../effort_estimation_trial.md#restore-invitation-prompt)
Extended estimates: enabled
**Cycle estimate: 0.75**

Authorization: User approved Step 3 on 2026-11-01; v2/C inline naming, no prompt restoration. Branch feat/invitation-username; planning commit 4cb17b77. No merge/deployment authorized.
Entry checkpoint: 2026-11-01 UTC; 0.75 reaffirmed, baseline 767a8a2ba8270e29269fd9d2f6d2e707bff8ffa4, R1/P2, Codex single exposed rater. Original A 0.55 and C 0.65 retained. No measured labor or maintainer acceptance.
Stage originals: S1 0.12 eligibility; S2 0.15 retry; S3 0.12 Account; S4 0.15 Lobby; S5 0.14 release proof. Stage work is non-additive; shared release verification belongs to S5.
Reuse: Existing LocalWriteService profile-name contracts, canonical identity and ProfileNames history, browser_signing.js naming/identity helpers, Account inline editor and invitation UI/APIs. No new identity system, dependencies or schema planned.
Completion: Untargeted inline naming and Account guest recovery keep the key, posts, approval and capacity through retries; ordinary rebuild and affected shared consumers verified. User deployment remains unspecified and cannot be claimed recovered by local tests.

*— Codex · 2026-11-01 UTC*

## Stage 1 - Initial-name authority

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes: Canonical original guest plus no verified name history permits initial naming with the flag off; prepare and locked finalization both enforce the rule. Naming guest again is refused under the exception.
- Verification: Three focused WriteApiSmokeTest tests passed: initial guest/concurrent/renamed-back cases, signature/tamper/rebuild, and default-off endpoint gates. Existing named identities retain the gate. No schema/dependency change.
- Notes: No estimate revision; original scope retained. Browser surfaces await S3/S4. Runtime canonical identity and verified history reused as planned.

*— Codex · 2026-11-01 UTC*

## Stage 2 - Naming retry

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Exact committed signed names recover before prepare-token validation, verify Git blob identity and verified history, repair stale projection, and return the latest name without overwriting later changes. Browser stores a per-key signed pending request before sending, retains it across uncertain failures, and protects local state against identity changes.
- Verification: Five focused PHP/Node-backed tests passed: prior eligibility/signature/gate checks plus injected committed refresh failure, absent token, lost-response replay, forged replay, newer name, and browser pending-request/identity-switch behavior. PHP/JS syntax and whitespace pass.
- Notes: S1 commit 1b2f0c39. Recovery authority remains exact signed committed names; no new receipt family. Definitive missing/expired uncommitted requests can be prepared afresh on next retry. No stage estimate revision.

*— Codex · 2026-11-01 UTC*
