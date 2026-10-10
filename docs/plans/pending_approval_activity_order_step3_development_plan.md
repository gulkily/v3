> **Feature plan:** [Step 1](./pending_approval_activity_order_step1_solution_assessment.md) · [Step 2](./pending_approval_activity_order_step2_feature_description.md) · [Step 3](./pending_approval_activity_order_step3_development_plan.md) · [Step 4](./pending_approval_activity_order_step4_implementation_summary.md)

## Completion Contract

- **Entry/outcome:** An approved viewer follows Users → View users awaiting approval, reads each clearly grouped user's details and most recent action before Approve on mobile and desktop, and approves the intended user.
- **Recovery:** Success removes only the complete selected group and shows the empty state after the last approval; failure preserves the group, displays feedback, and enables retry.
- **Deployment/external verification:** Use the existing application release process; no migration, API, configuration, or external-service changes. Verify locally with the real page/assets; exercise approval through existing integration coverage and controlled browser success/failure responses. Production deployment is outside this implementation scope.
- **Release condition:** Grouping, reading order, links, permissions, approval recovery, and existing profile-page approval pass the checks below; record evidence in Step 4 before declaring completion.

## Key Risks

- **High risk: User/activity mismatch.** Approval cleanup currently depends on an adjacent activity row; a layout change could leave orphaned content or remove a neighbor. Early validation: inspected the existing template and cleanup together. Mitigation: preserve one explicit user association throughout rendering/removal and verify first, middle, and last approvals before release.
- **High risk: Mobile grouping and accessibility.** Separate row borders and long content can obscure ownership or produce conflicting reading orders. Early validation: inspected the mobile stacking rules and current source order. Mitigation: use the same information order in the document and visual presentation, with separators between complete users; verify narrow, breakpoint, and desktop layouts.

## Stage 1

- **Goal:** Deliver the complete pending-user review and approval layout fix within approximately one hour.
- **Dependencies:** Approved Step 3; create the feature branch and commit only approved Steps 1–3 before implementation. No unresolved product decisions or backend dependencies.
- **Expected changes:** Extend the existing pending page and styles to group details → activity → Approve; adjust approval cleanup as needed to remove the complete selected group. Preserve existing content, links, ordering of users, feedback, and permissions. Update existing smoke assertions that assume the old layout and verify meaningful per-user reading order. No database or function-signature changes.
- **Verification approach:** Run `php tests/run.php WriteApiSmokeTest`, PHP lint for the changed template, JavaScript syntax checking, and `git diff --check`. Inspect the real page at 360, 520, 521, and 1280px with adjacent users, long content, and missing activity; check keyboard/source order and link destinations. Exercise success, failure/retry, first/middle/last-user removal, and shared profile approval in a local browser with controlled approval responses.
- **Risks or open questions:** Both risks above apply; validate group ownership and reading order before approval checks. Any failed check blocks completion until corrected and reverified.
- **Canonical components/API contracts touched:** `templates/pages/users_pending.php`, `public/assets/pending-approvals.css`, `public/assets/pending_approvals.js`, and existing `tests/WriteApiSmokeTest.php` coverage. Reuse the current directory data, browser signing helper, and approval APIs without contract changes.
- **Completion/commit:** Record changes and verification in Step 4 and commit this stage with its summary. Minimum implementation history: one planning commit plus one completed-stage commit.

**Review:** Awaiting **Approved Step 3** before branching or implementation.
