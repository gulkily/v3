# Signed User-to-User Approvals — Step 4: Implementation Summary

## Stage 1 - Prepare canonical approval replies
- Changes:
  - Added a signed-approval preparation contract and `/api/prepare_approval` endpoint.
  - The endpoint applies existing approver, self-approval, target, and approval-state checks, then returns a short-lived canonical approval reply without writing it.
- Verification:
  - `php tests/run.php WriteApiSmokeTest` passed.
  - Coverage confirms prepared replies contain the expected approval fields, do not create a post or approval, and reject unapproved/self approvers.
- Notes:
  - Stage 2 will verify the detached signature and make the paired record/signature write atomic.
