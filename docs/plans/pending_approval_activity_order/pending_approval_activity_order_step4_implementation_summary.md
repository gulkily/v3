> **Feature plan:** [Step 1](./pending_approval_activity_order_step1_solution_assessment.md) · [Step 2](./pending_approval_activity_order_step2_feature_description.md) · [Step 3](./pending_approval_activity_order_step3_development_plan.md) · [Step 4](./pending_approval_activity_order_step4_implementation_summary.md)

## Stage 1 - Group activity before approval

- Changes:
  - Each pending user now has a single row containing user details, profile/activity, then Approve. On mobile, activity appears above the button, with separators between complete users.
  - Preserved activity links, timestamps, missing-activity fallback, and the desktop approval column; removed obsolete paired-row styles and cleanup.
  - Updated the existing directory smoke assertion to verify the user's activity precedes their own approval control within the same row.
  - Moved all four planning artifacts into this feature folder and added the folder to the plan index.
- Verification:
  - `php -l templates/pages/users_pending.php`, `node --check public/assets/pending_approvals.js`, and `git diff --check` passed.
  - `php tests/run.php WriteApiSmokeTest`: 136 run, 134 passed, 2 failed. All pending-directory and approval tests passed.
  - `testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote` also failed in a detached worktree at the planning commit, before implementation, with the same expected-2/actual-0 assertion.
  - `testTaskQueueProcessesQueuedAgentReplyOnce` failed on missing `type=agent_reply`, including an isolated rerun. Test history records this failure since October 8, before this change; it passed in the isolated baseline worktree. This environment-dependent failure remains unresolved and the full suite is not green.
  - Local Chromium checks used application-rendered pages from an isolated fixture repository and the actual assets. Users-directory navigation, visual/source ordering, keyboard order, and no horizontal overflow passed at 360, 520, 521, and 1280px, including long activity and no-activity fallback. Mobile and desktop screenshots were visually inspected.
  - Controlled browser approval responses verified failure/retry, correct target identity, middle/first/last-user removal without orphaned activity, final empty state, and shared profile-approval failure/retry and success redirect. These checks stubbed signing responses; existing integration tests covered the approval API.
  - Local verification scripts and screenshots are in `/tmp/pending-approval-*`; they are temporary evidence, not repository artifacts.
- Notes:
  - Feature-specific checks passed. The two unrelated smoke failures above remain outside this presentation change.
  - No database, API contract, external service, or deployment configuration changes were needed. Production deployment was not performed.
  - Planning commit: `179e3b6c`; this summary accompanies the single implementation-stage commit on `feature/pending-approval-activity-order`.

## Stage 1 follow-up - User-requested column refinement

- Changes: Grouped the linked username and shortened profile identifier in User; renamed the second column Recent activity and kept the latest action/timestamp there. The identifier shows `openpgp-` followed by ten key characters and `...`, with the complete identifier available on hover and in the accessible link label. Approval remains the third column and last in mobile reading order.
- Verification: PHP lint and `git diff --check` passed. The existing directory-access and activity/fallback smoke tests both passed. Repeated Chromium checks passed at 360, 520, 521, and 1280px for layout, overflow, keyboard/source order, approval failure/retry, first/middle/last-user removal, empty state, and shared profile approval. Visually reviewed refreshed mobile and desktop screenshots.
- Notes: This refinement was explicitly requested after implementation review. The full smoke suite was not repeated; its previously documented unrelated failures remain. No deployment performed.
