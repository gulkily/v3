> **Feature plan:** [Step 1](restore_invitation_prompt_step1_solution_assessment.md) · [Step 2](restore_invitation_prompt_step2_feature_description.md) · [Step 3](restore_invitation_prompt_step3_development_plan.md) · Step 4
Debug mode: enabled
Effort study: railspike-effort / v3/restore_invitation_prompt; [ledger](../effort_estimation_trial.md#restore-invitation-prompt)
Extended estimates: enabled
**Cycle estimate: 0.85**

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

## Stage 3 - Account recovery

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes: Existing Account pre-paint state script exposes Change for local guests/pending naming as well as the enabled rename flag; server eligibility remains authoritative. Continue names an existing key rather than replacing it. Existing inline editor uses canonical 80-character limit.
- Verification: AccountKeyPageHooksTest plus BrowserSigningNormalizationTest 89/89 passed; renamed hook test separately 6/6. New real Chrome account_initial_name.mjs passed public/private flag-off guest naming, cancel, lost-response/reload recovery, same fingerprint and later rename control hidden. PHP/JS/whitespace checks pass.
- Notes: S2 commit a10f7c5a. First private browser attempt encountered pending sign-in navigation during rapid fixture clicks; fixture now completes in-place authentication before testing the reloaded Account editor, and both modes pass. Initial visibility uses existing synchronous state script, without new server personalization. Full theme/viewport matrix follows S5.

*— Codex · 2026-11-01 UTC*

## Stage 4 - Name and redeem

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.16**

- Changes: Lobby uses the canonical form/input styling and shared naming helper; username draft persists in session storage, fresh keys use the chosen name and existing automatic guest keys receive signed naming. Identity checks guard asynchronous redemption. Named visitors keep their name. Pre-paint local state controls field visibility; controls stay in place during inspection rather than appearing after a response. Targeted entry still proceeds automatically without requiring input.
- Verification: InvitationIssuanceTest 10/10; real Chrome invite_username.mjs passed four public/private × automatic on/off cases with renaming disabled, invalid input, draft reload, committed-name lost response, accepted-invitation lost response, same-key and named retries. Legacy invite_zero_click and capped invite_multi_redemption suites passed both modes, including targeted recovery and CLI rebuild. PHP/JS syntax and whitespace pass.
- Notes: S3 commit 125a58c0. First rendered check caught label CSS overriding hidden; using a hidden wrapper fixed it. Automatic-key fixture now visits Account before opening the invite, matching existing deliberate suppression of background generation on invite links. Existing tests now enter and assert names rather than optionally handling dialogs. No change to signed invitation contracts/capacity.

*— Codex · 2026-11-01 UTC*

## Stage 5 - Release proof

**Original estimate: 0.14**
**Revised stage-work estimate: 0.22**

- Discovery/reuse decision (2026-11-01): Failure review found ordinary name writes left uncommitted files after Git failure, which can consume initial-name eligibility. Extend existing Write/SignedPostTransaction's path allowlist to profile-names and its optional commit message, and reuse its journal/recovery for naming; no parallel transaction or new canonical format. Verify original signed-post consumers as well as naming failure/retry. This is correction within approved failure recovery, not new product scope.
- Cycle projection: 0.85 revises entry 0.75 for demonstrated streamed first-paint correction and transaction recovery checks; original projections retained, independently judged rather than stage sum. Same baseline/v2/C/R1/P2 and exposed rater; no measured labor.

**Retrospective stage-work assessment: 0.24**

- Changes: Preserved prior normalization without silent guest fallback; added committed-name transaction rollback/crash recovery using SignedPostTransaction and verified its original consumers. Account guest hint and canonical account-state selection now run ahead of markup for streamed first paint; existing CSS exposes the correct control. Identity-change events refresh Lobby naming only on real key/name changes. Updated API/flag/reference/spec/concept guidance and UI audit.
- Verification: Final focused suite 177/177 passed (naming/signatures/eligibility/replay, legacy guest without Username, transaction failures and original signed-post consumers, invitation capacity/policy, parsers, Account/browser helpers, agent contracts). Real Chrome inline invitation suite passed fresh/local-only automatic/published guest cases in both public/private modes with renaming off, including invalid input, preserved draft, reload, lost naming/acceptance responses, same-key and named retries. Account suite covers pending and already-approved guest recovery, cancellation, lost-response reload and ordinary CLI rebuild with preserved fingerprint/name/approval; legacy and capped invitation suites passed both modes and ordinary CLI rebuild. Counts from earlier stage runs overlap and are not additive.
- Rendered verification: invite_username_layout.mjs passed all 16 themes across their permitted site profiles, phone 390px and desktop 1280px, both Lobby and Account. Checks include actual first-frame control visibility, Lobby action position, loaded/long/error/expanded states and document overflow. Light phone and Word97 desktop screenshots visually inspected; snapshots are disposable local artifacts under /tmp/v3-invitation-username-layout.
- Corrections during checks: Theme test initially navigated only within the same fragment, so initialization did not rerun; unique query URLs now force full loads. Site-specific themes are tested under the appropriate site profile instead of selecting forbidden themes. Streamed Account markup exposed a real late-control visibility issue, fixed with the existing canonical state helper before children parse. No unrelated theme/layout redesign.
- Integrity/scope: PHP/JS syntax, whitespace and current plan/concept/reference links checked. Zero external dependency, schema, manifest/lock or todo.txt changes. No navigation destination added. No full-suite, production-scale, physical-device, deployed-site or merge-readiness claim.

## Closeout

Status: Five stages implemented and locally verified; user deployment remains unspecified/unverified, with replay instructions in invitation_entry.md. Local completion does not establish recovery on the live site.
Commits: Planning 4cb17b77; S1 1b2f0c39; S2 a10f7c5a; S3 125a58c0; S4 201a623c; S5 is the stage-scoped closeout commit containing this record. No merge/push/deployment.
Study: v3/restore_invitation_prompt, v2/C, original baseline/R1/P2; entry 0.75 retained, revised projection 0.85. Original A 0.55 and C 0.65 remain in earlier artifacts; stage originals and revisions above retained. Codex single exposed rater; missing pristine intake and unknown measured labor retained.
**Retrospective cycle scope assessment: 0.85**
Maintainer comfortable-fit/acceptance: Pending; no accepted-outcome increment. No additional scope or branch reuse approved; current branch is feat/invitation-username.

*— Codex · 2026-11-01 UTC*
