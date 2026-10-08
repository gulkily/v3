> **Feature plan:** [Step 1](./pending_user_latest_activity_step1_solution_assessment.md) · [Step 2](./pending_user_latest_activity_step2_feature_description.md) · [Step 3](./pending_user_latest_activity_step3_development_plan.md) · [Step 4](./pending_user_latest_activity_step4_implementation_summary.md)

# Step 3: Development Plan — Pending User Latest Activity

## Completion Contract

- Normal entry: an approved viewer opens `/users/pending`.
- End-to-end outcome: each pending profile has a subordinate two-column line with its latest activity description and standard reading-friendly timestamp; bootstrap activity qualifies.
- Required recovery: a pending profile without indexed activity has an explicit fallback; unauthorized viewing and the existing approve flow remain unchanged.
- Deployment/external verification: no migration, external service, or deployment configuration is required; run focused route coverage and the relevant smoke suite.
- Release condition: focused tests prove bootstrap, later activity, fallback, layout, and unchanged approval access/action behavior.

## Key Risks

- **High risk: data correctness.** A hidden bootstrap or tied timestamp could select the wrong activity. Validate bootstrap-only and later-activity fixtures early; use the established stable activity ordering.
- **High risk: usability.** A long activity line could crowd the approval action. Render it below the profile and span only User and Profile columns; preserve Approve as its own column.
- **High risk: rollback.** A changed pending-row structure could break optimistic approval removal. Exercise the existing approval behavior against the enhanced table before release.

## Stage 1

- Goal: Provide each pending profile's stable latest activity description and timestamp to the existing pending-directory route.
- Dependencies: Existing profiles and activity read-model data, including bootstrap activity.
- Expected changes: Extend `ProfileRepository::pendingDirectoryProfiles(PDO $pdo): array`'s returned data with latest-activity fields and a missing-activity fallback contract; no schema change.
- Verification approach: Add focused route/data coverage for bootstrap-only, later activity, tied-time ordering, and no indexed activity.
- Risks or open questions:
  - Impact: Selecting only normal-feed activity would hide account bootstrap.
  - Early warning / validation: Bootstrap-only coverage has an empty result.
  - Mitigation: Source the summary from the complete indexed activity history using its stable ordering.
- Canonical components/API contracts touched: `ProfileRepository::pendingDirectoryProfiles`; the existing `activity` read-model semantics.

## Stage 2

- Goal: Display the supplied activity summary safely in the approved-only pending approval queue.
- Dependencies: Stage 1's latest-activity fields and fallback contract.
- Expected changes: Extend `templates/pages/users_pending.php` with a subordinate line spanning User and Profile columns; reuse the standard reading-friendly timestamp formatter; preserve the separate Approve column and existing row data attributes.
- Verification approach: Extend `/users/pending` route coverage for display copy, timestamp format, two-column layout, fallback, approved-viewer access, and existing approval assets/controls.
- Risks or open questions:
  - Impact: Added table markup can interfere with the pending-approval script's row removal.
  - Early warning / validation: Existing approval behavior no longer removes the intended profile cleanly.
  - Mitigation: Keep the canonical action row and its identifying attributes unchanged; scope the new line as presentation-only.
- Canonical components/API contracts touched: `users_pending.php`, `relativeTimestamp`, and `pending_approvals.js`'s existing row contract.
