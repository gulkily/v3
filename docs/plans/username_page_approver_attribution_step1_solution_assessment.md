# Step 1: Solution Assessment — Show Approver on `/user/{username}` Page

## Problem Statement
The `/user/{username}` page (`ProfilePageController::username()`, `templates/pages/username.php`) lists a user's approved profiles but does not show who approved each one, even though that data (`approved_by_identity_id`, `approved_by_profile_slug`, `approved_by_label`) already exists on `profiles` and is already displayed on the per-identity page (`templates/pages/profile.php`).

## Option A: Inline "Approved by" line per profile (reuse existing pattern)
- Add the same `Approved by: <link>` line already used in `templates/pages/profile.php` (lines 38-47) under each entry in the "Approved Profiles" list on `username.php`.
- **Pros:** No schema/query changes if `approved_by_*` columns are already fetched for this page (verify in Step 2/3); visually consistent with the existing per-profile page; smallest change.
- **Cons:** Adds a line per profile; could look repetitive for users with many approved profiles.

## Option B: Link to the approval activity post instead of/alongside inline text
- Reuse the existing "Open approval post" link pattern (`ProfilePageController::profileNoticeFromQuery()`) so each profile row links out to its board-tagged approval post in the activity feed.
- **Pros:** Leverages the canonical audit trail (activity posts tagged `identity`+`approval`) rather than the denormalized cache; gives full context (timestamp, discussion) in one click.
- **Cons:** Extra navigation step to see "who approved"; less scannable than inline text; approval post may not exist/be findable for all historical approvals.

## Option C: Expandable/collapsed detail per profile
- Show approved profiles as-is by default; add a toggle or icon that expands to reveal approver info.
- **Pros:** Keeps the default list compact.
- **Cons:** New UI interaction pattern not used elsewhere in this codebase for this kind of detail; more implementation and design work for marginal benefit.

## Option D: Single aggregate summary line (chosen)
- Add one new line after the existing "Approved profiles / Combined threads / Combined posts" summary stats: `Approved by: <comma-separated usernames>`.
- Source: `approved_by_label` from the identity's **approved** profiles only; de-duplicated; excludes any entry where the approver equals the profile owner (self-approval).
- **Pros:** Answers "who approved this user" at a glance without per-profile clutter; fits directly into the existing summary block; no new UI pattern.
- **Cons:** Loses the profile-to-approver mapping (which approver approved which specific profile isn't shown, only the aggregate set).

## Recommendation
**Option D**, per explicit direction: a single aggregate "Approved by" line placed after the existing summary stats, listing distinct approving usernames sourced from approved profiles only, excluding self-approvals.
