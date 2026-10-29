> **Feature plan:** [Step 1](./background_sign_in_step1_solution_assessment.md) · [Step 2](./background_sign_in_step2_feature_description.md) · [Step 3](./background_sign_in_step3_development_plan.md) · [Step 4](./background_sign_in_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/background_sign_in; [ledger](../effort_estimation_trial.md#cycle-v3background_sign_in), standing enrollment P2
Extended estimates: enabled
Status: Four stages complete locally; merge/deployment and maintainer fit pending
**Cycle estimate: 0.35**

Entry: 2026-10-28 UTC; scope v1 / Option A, baseline 49a9024412c7e7f6d9230fb7ed1e7b72acf4627e, R1 / P2, Codex single exposed rater. Step 3 projection reaffirmed; pristine intake remains missing. Planning commit a9372972 on feat/background-sign-in. Original stage-work estimates: S1 0.12, S2 0.10, S3 0.10, S4 0.08; scopes/drivers/uncertainties retained in Step 3; not additive. No new dependencies authorized or proposed.

*— Codex · 2026-10-28 UTC*

## Stage 1 - Public sign-in stays in place

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Public resume and Account share quiet in-place completion; every non-navigation early/success path stays put. Unapproved public identities return authenticated, distinct from pending membership. Intentional redirects remain.
- Verification: New deterministic contract failed on baseline (public in-place result incorrectly pending), then passed. PrivateSiteAuthTest 11/11 passed; updated the explicit in-place success assertion to expect no misleading entering-site text.
- Notes: Concurrent-call semantics remain Stage 2. No schema/dependency changes. Original scope and estimate unchanged; single exposed Codex rater, R1/P2, 2026-10-28 UTC.

*— Codex · 2026-10-28 UTC*

## Stage 2 - Overlapping actions retain their intent

**Original estimate: 0.10**
**Retrospective stage work: 0.12**

- Changes: Signing/challenge work is shared independently of each caller's continuation. Forced verification and incompatible identity/restriction work queue behind the active challenge; only one helper navigation occurs per shared flight. Key-material snapshots reject obsolete results before signing submission and after response. Existing public APIs/callers retain their contracts; already authenticated state is not reported as a new approval change.
- Verification: PrivateSiteAuthTest, AuthNavigationTest and AccountKeyPageHooksTest 21/21 passed. Contract tests cover both caller orders, forced verification, and identity changes during loading, signing and authentication response. No navigation occurs for rejected stale results.
- Notes: Stage 1 commit bb4355e2. Slight retrospective increase reflects serializing forced callers without sharing navigation side effects; prospective cycle 0.35 unchanged. No dependency changes.

*— Codex · 2026-10-28 UTC*

## Stage 3 - Prove uninterrupted writing and recovery

**Original estimate: 0.10**
**Retrospective stage work: 0.12**

- Changes: New real-browser delayed-authentication replay; compose test now writes immediately and asserts document continuity instead of waiting out a reload.
- Verification: Chromium background_sign_in.mjs passed 16 cases (approved/unapproved × 390/1280 widths × typing/name editor/submission/failure-retry). Real challenge signatures and session cookies verified through Account; approved access 200 versus unapproved access 401 preserved. Input/selection/focus/scroll/editor/document survived, and each submission sent once. compose_identity_row.mjs passed its six-composer/state/viewport matrix, naming/retry, first reply, offline Like and no-JavaScript checks. Phone name-editor screenshot inspected; no overflow.
- Notes: Stage 2 commit 121796eb. Initial approved fixture used an invalid seed filename; corrected it to the canonical identity filename and reran all 16 cases successfully. Browser retry invokes the existing helper; user-facing Account recovery is part of Stage 4. No physical-device claim. Prospective scope/estimate unchanged; retrospective reflects deterministic fixture setup.

*— Codex · 2026-10-28 UTC*


## Stage 4 - Compatibility and closeout

**Original estimate: 0.08**
**Retrospective stage work: 0.15**

- Changes: Removed Account's testing-only sign-in reload; its session-bound testing approval button is available on the next intentional visit. A queued request now rejects an identity changed while it waits, rather than adopting the replacement key. These are in-scope corrections. Expanded deterministic/browser tests and recorded handoff/UI/study evidence.
- Verification: Final focused PHP run 21/21 (PrivateSiteAuthTest, AuthNavigationTest, AccountKeyPageHooksTest). Combined run with LocalAppSmokeTest: 175 run, 174 passed; the one archive-download failure reproduces on planning baseline a9372972 in a temporary detached worktree, which was removed afterward. Missing method: InstancePageController::repositoryArchiveDownloadFilename; unrelated to these changes.
- Browser checks: compose_identity_row, first_post_identity, account_states, railspike_auth_navigation, invite_redemption, invite_signing_readiness and invite_zero_click passed. Invitation checks exercised public/members-only sites, delayed verification, stale fingerprints, wrong-key/revocation, missing/unapproved keys, restore/retry, lost responses and redemption.
- Matrix: Chromium 80 delayed-auth cases (Light/Word97/Chicago/Midnight × two widths × two approval states × typing/name/submission/retry/Account); 72 name/Account cases for the other nine permitted explicit ZenMemes themes. Firefox and WebKit each passed all 20 Light-theme cases. Additional strengthened typing checks passed four cases per engine with menu-open and primary-action geometry assertions. Screenshots inspected for six representative themes; overflow checks passed throughout.
- Corrections during verification: Invalid test approval-seed filename corrected in Stage 3; a later theme run incorrectly requested site-specific Chouse/QDB/MIT themes on ZenMemes, then reran the supported nine-theme matrix successfully. These were fixture assumptions, not application regressions.
- Notes: Stage 3 commit 71df7611 (2026-10-29 UTC; its earlier footer used the local calendar date). Stage 4 retrospectively larger because of Account's testing path and cross-engine/theme evidence; original stage and whole-cycle projections retained. Dependency delta: zero. No schema/record-format changes, new UI controls or navigation destinations.

## Closeout

Outcome: Public background sign-in preserves the current document and interaction while restoring a real session; explicit access navigation retains its intended destination. Authentication/approval remain distinct. All four planned stages complete locally; planning a9372972, S1 bb4355e2, S2 121796eb, S3 71df7611, S4 is the commit containing this closeout. Baseline 49a9024412c7e7f6d9230fb7ed1e7b72acf4627e; final diff is that baseline through this stage commit on feat/background-sign-in.

Evidence: Tests above plus JavaScript/PHP syntax and whitespace checks. Browser fixture proves real session availability on Account for both membership states, approved access 200 versus unapproved 401, document identity, input/selection/focus/scroll/editor preservation and one accepted request per submission. Existing static/keyless/unregistered exemptions are covered by deterministic contracts and first-post/compose regressions. Failure recovery preserves work; explicit helper retry and existing Account recovery flows were exercised separately.

Limits: Full application suite not run. Physical iPhone/Android and production session settings not checked; desktop engine/device emulation is not physical-device evidence. Site-specific branded themes outside the ZenMemes fixture not checked. No merge, push or deployment. Version-check navigation remains a separate follow-up. The archive test failure predates this branch and remains unresolved.

Study checkpoint: 2026-10-29 UTC, railspike-effort / v3/background_sign_in, scope v1 / Option A / R1 / P2, Codex single exposed rater. Original projected total 0.35 retained through implementation; separate retrospective scope assessment 0.45 reflects concurrency and browser verification. Stage original/retrospective values: 0.12/0.12, 0.10/0.12, 0.10/0.12, 0.08/0.15; not summed. Four planned/four completed; no split, new request, independent rating or measured labor. Pristine intake remains missing. Maintainer comfortable-fit judgment and study acceptance pending; accepted-count unchanged.

*— Codex · 2026-10-29 UTC*
