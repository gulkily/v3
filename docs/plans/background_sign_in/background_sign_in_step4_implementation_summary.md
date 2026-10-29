> **Feature plan:** [Step 1](./background_sign_in_step1_solution_assessment.md) · [Step 2](./background_sign_in_step2_feature_description.md) · [Step 3](./background_sign_in_step3_development_plan.md) · [Step 4](./background_sign_in_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/background_sign_in; [ledger](../effort_estimation_trial.md#cycle-v3background_sign_in), standing enrollment P2
Extended estimates: enabled
Status: Step 4 active; Approved Step 3 received
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
