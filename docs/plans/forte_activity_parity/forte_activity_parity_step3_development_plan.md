> **Feature plan:** [Step 2](./forte_activity_parity_step2_feature_description.md) · [Step 3](./forte_activity_parity_step3_development_plan.md) · [Step 4](./forte_activity_parity_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Keep Forte's split-pane layout and Commits view. From `/forte/activity/`, shared categories, event facts, evidence, and eligible RSS agree with default Activity; filtering, selection, sorting, pagination, and failed-fetch retry work on desktop/phone with mouse and keyboard.
- Security review retains authenticated operator authorization, no-store responses, and no RSS. Ordinary views never reuse restricted details. Reuse existing data/presentation contracts and bounded manifests; no schema changes or production deployment.
- Release after focused PHP/browser regressions and local UI checks pass, with results in the Step 4 summary. Commit each completed stage with its summary update before continuing.

## Key Risks

- **High risk:** Cached details or late fetches can mix restricted and ordinary views. Validate transitions first; scope detail state to its view and discard stale responses before dependent presentation work.
- **High risk:** Missing row membership, duplicate pagination, or stale selection can hide activity. Compare category results early; verify selection, sorting, counts, and load-more together.
- Large manifests can regress page responsiveness. Preserve lightweight rows, relevant-file summaries, and on-demand commit manifests; run existing lightweight-detail/recovery tests.

## Stage 1

- Goal: Reliable category switching and event inspection, including operator security review.
- Dependencies: Approved Step 3; existing shared ActivityService and authorization guards.
- Expected changes: Correct initial/paged row membership; align view-specific detail rendering/cache state and in-flight responses; allow failed detail/page fetches to be retried without stale selection.
- Verification approach: Focused browser flows for ordinary↔security switching, selected-event reuse, delayed/failed responses, pagination, sorting, and keyboard selection; PHP checks for operator/member/anonymous authorization and no-store detail/page endpoints.
- Risks or open questions: Resolve restricted-state reuse and missing-row risks here; do not proceed until transitions and endpoint guards pass.
- Canonical components/API contracts touched: ForteActivityController, paned activity row/list/detail partials, paned_activity_reader.js, existing Forte activity detail/page/commit endpoints and tests.

## Stage 2

- Goal: Readers see equivalent information and controls in Forte and default Activity.
- Dependencies: Stage 1 committed and verified.
- Expected changes: Place policy context inside the Forte window; align shared-category labels and eligible RSS access; close event/author/source/signature/acceptance-evidence presentation gaps using existing partials while retaining Forte content destinations and Commits navigation.
- Verification approach: Compare matching events/categories against default Activity; inspect ordinary and security details plus RSS availability, responsive layout, keyboard controls, and evidence destinations. Retain shared-manifest and lightweight-row assertions.
- Risks or open questions: Resolve inaccurate evidence or oversized-manifest risks before completion; security policy context and RSS must follow the active view.
- Canonical components/API contracts touched: Default/Forte Activity templates/controllers, shared source_metadata/activity_commit_manifest/approval_security_review partials, existing activity/Forte CSS and theme variables.

## Stage 3

- Goal: Verify the complete parity flow and document the outcome.
- Dependencies: Stages 1–2 committed and verified.
- Expected changes: Complete focused regression/browser coverage and Step 4 summary with stage evidence and known limits; fix only defects within approved scope.
- Verification approach: Run activity recovery, filtered-limit, manifest-parity, lightweight-detail, operator-security, and relevant offline/cache tests; exercise desktop/phone navigation, sorting, repeated pagination, switching, and fetch recovery against an isolated local fixture. Run syntax and whitespace checks.
- Risks or open questions: Distinguish pre-existing failures with focused baseline checks; production deployment and unrelated root approval pages remain outside this work.
- Canonical components/API contracts touched: Existing PHP/browser test fixtures and implementation summary; no new external services or canonical authority format.
