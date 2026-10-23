> **Feature plan:** [Step 1](./railspike_workflow_visibility_step1_solution_assessment.md) · [Step 2](./railspike_workflow_visibility_step2_feature_description.md) · [Step 3](./railspike_workflow_visibility_step3_development_plan.md) · [Step 4](./railspike_workflow_visibility_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/railspike_workflow_visibility`, scope v2, [P2 ledger](../effort_estimation_trial.md#cycle-v3railspike_workflow_visibility). Steps 1–2 approved.

## Step 4 Entry

Step 3 scope v2 approved. Planning commit `4e176747`; branch `fix/activity-workflow-block`. R1/P2 total projection remains unavailable pending exact frozen sources, reaffirming Step 3. Same baseline and exposed rater; no measured effort.

## Stage 1 - Remove detached workflow integration
- Changes: removed Activity placeholder, workflow refresh branch and Activity workflow script inclusion; initialization now requires post mounts. Other signing/auth dependencies and documented API remain intact. Existing browser journey now checks rendered absence and no workflow requests over 5.5 seconds plus visibility refresh.
- Verification: changed PHP/JS syntax and whitespace checks passed. Workflow browser journey passed: signed actions, failure/retry, rebuild, stale approval, standalone post, saved-key resumption, privacy, Activity absence/network silence and overflow. Cached Node/Chromium and extracted library paths were needed after initial environment lookup failures; no project dependency changes.
- Notes: thread refresh/rendering preserved. Stage 2 covers additional roles, no-JS, desktop/themes, pagination/RSS and documentation.

## Stage 2 - Rendered acceptance and documentation
- Changes: added operator/anonymous × JS/no-JS × 390/1280 Activity checks to the existing workflow browser journey. Existing pagination browser check now also verifies absent workflow UI, opening an ordinary entry and RSS. Updated UI audit and effort ledger; repaired moved-plan references. Stage 1 commit: `2224e68c`.
- Verification: both browser scripts passed. Standard Activity covers native paging/history, empty view, invalid-position/HTTP recovery, source evidence, RSS, entry navigation and all 13 themes at phone/desktop without overflow; unchanged Forte append/detail flows passed. Workflow journey includes the role/script/viewport matrix, signed actions/retry/resumption and Activity network silence. Inspected `/tmp/activity-workflow-removal-phone.png` (heading/navigation and loaded entry) and `/tmp/activity-pagination-word97-390.png` (loaded content/pagination).
- Verification: targeted PHP totals 26 passed: RailspikeWorkflowTest 8, StandardActivityPaginationTest 11, ActivityItemLinkTest 4, AuthNavigationTest 3. Initial AuthNavigation execution failed because Node was absent from PATH; rerun with cached Node passed all three. Syntax/whitespace checks passed. Browser execution used cached Node, Chromium and `/tmp/retraction-browser-libs/extracted/usr/lib/x86_64-linux-gnu` via LD_LIBRARY_PATH. No project runtime dependencies changed.
- Notes: production deployment and reload of the reported URL, physical devices and full test suite are **pending/not performed**, not passed. Native reload after deployment is the live acceptance action. No navigation additions/icon changes, schema migrations or canonical record changes apply. Remaining private reading proposal is saved separately and unapproved; broader repository coverage remains outside scope.

## Study Outcome and Handoff

Scope v2 delivered locally in two stages after planning `4e176747`, baseline `cd2f84087a6584d2b1cb3ac8ebaa2395fa897ec6`. Original v1/split history retained; no independent split outcome. Step 3 total projection reaffirmed at entry. Retrospective scope assessment: bounded Activity-only removal plus existing-consumer/browser verification and documentation; numeric retrospective rating remains unavailable pending exact frozen R1 sources, separate from projected totals. R1/P2 and exposed single rater unchanged; no measured effort. Maintainer comfortable-fit/acceptance and live deployment verification pending; accepted-count unchanged. No additional scope arose during implementation.
