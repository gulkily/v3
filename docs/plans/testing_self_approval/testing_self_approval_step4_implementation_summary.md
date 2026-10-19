> **Feature plan:** [Step 1](./testing_self_approval_step1_solution_assessment.md) · [Step 2](./testing_self_approval_step2_feature_description.md) · [Step 3](./testing_self_approval_step3_development_plan.md) · [Step 4](./testing_self_approval_step4_implementation_summary.md)

Debug mode: enabled

## Execution

- Approved Steps 1–3; planning commit: `37594b14`. Branch: `feat/testing-self-approval`.
- Existing user modification to `todo.txt` is excluded from this work.
- Stage 1 in progress; no effort study selected.

## Stage 1 - Testing boundary
- Changes: shared fresh marker/direct-loopback policy; ignored project-root marker; regression coverage.
- Verification: PASS `php tests/run.php TestingSelfApprovalTest` (1 test); ordinary `./v3 start 18763 --quiet` and WSL Chromium direct `127.0.0.1` request returned 200. Initial browser invocation used unavailable `playwright`; corrected to installed `playwright-core`.
- Notes: actual connection is WSL Chromium to loopback-bound PHP development server; Windows-host forwarding remains pending. No marker created in the working checkout.

## Stage 2 - One-click browser approval
- Changes: current-account POST action with session token, exact Origin check, authenticated-session target, and marker/locality revalidation; Account Key button and existing feedback styling; immediate reload after seed approval. Reject alternate input fields. Local Host and proxy-header checks additionally keep public reverse-proxy paths out.
- Verification: PASS 17 focused policy/account hooks/auth tests; PASS isolated HTTP browser workflow with members-only both disabled and enabled, desktop/mobile overflow checks, marker removal/re-enable, wrong method/token/origin/target, anonymous request, immediate authenticated member access, and harmless repeat.
- Notes: seed/auth combined run passed 30/31; the failure expects obsolete profile copy `This is your profile.` It also fails with the pre-Stage-2 Application restored, confirming it predates this change. Existing template-hook tests emit undefined identityHint fixture warnings. Stage 1 commit: `1f86f805`.
