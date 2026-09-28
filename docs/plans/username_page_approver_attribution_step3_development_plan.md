# Step 3: Development Plan — Show Approver on `/user/{username}` Page

## Stage 1
- Goal: derive the distinct, self-approval-excluded list of approver usernames from the identity's approved profiles in `ProfilePageController::username()`
- Dependencies: `ProfileRepository::byUsernameToken()` already selects `approved_by_identity_id` / `approved_by_label` (`ProfileRepository::COLUMNS`) — no query or schema changes needed
- Expected changes:
  - Add a helper (e.g. `ProfilePageController::aggregateApproverUsernames(array $approvedProfiles, array $ownIdentityIds): array`) that: collects `approved_by_label` from `$approvedProfiles` where `approved_by_identity_id` is present and not in `$ownIdentityIds`; de-duplicates the result
  - `$ownIdentityIds` derived from all of this username's profiles (approved + unapproved), so a self-approval across the user's own identities is excluded, not just same-profile self-approval
  - Pass result as new `approverUsernames` key in the `username.php` render array
- Verification approach: manually exercise `/user/{username}` for a fixture/user with (a) one approver, (b) multiple approvers across profiles, (c) an approver who is also one of the user's own identities, (d) zero approved profiles; confirm the computed array matches expectations before wiring the template
- Risks or open questions:
  - Confirm the "self" identity set should span all of this username's profiles (approved and unapproved), not just approved ones
  - Confirm ordering (e.g., alphabetical vs. first-approved) for deterministic output
- Canonical components/API contracts touched: `ProfilePageController::username()` render array only (adds `approverUsernames`); no `ProfileRepository` or API contract changes

## Stage 2
- Goal: render the "Approved by: ..." line on `username.php` immediately after the existing "Combined posts" summary line, only when non-empty
- Dependencies: Stage 1 must supply `approverUsernames` to the template
- Expected changes:
  - `templates/pages/username.php`: add a conditional block rendering `Approved by: <comma-separated, escaped usernames>` right after the existing summary stats, using the same `$e()` escaping helper already used elsewhere on this page
  - Omit the line entirely when `approverUsernames` is empty
- Verification approach: manual browser check of `/user/{username}` for the same four cases from Stage 1, confirming correct text/omission and consistent styling with the adjacent summary lines
- Risks or open questions:
  - None beyond Stage 1's open questions (list carries over unchanged)
- Canonical components/API contracts touched: `templates/pages/username.php` only (reuses existing `$e()` helper; no new component)
