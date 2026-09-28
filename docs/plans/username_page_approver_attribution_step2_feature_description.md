# Step 2: Feature Description — Show Approver on `/user/{username}` Page

## Problem
On `/user/{username}`, there's no way to see who vouched for/approved this user's account(s), even though that information already exists and is shown elsewhere in the app.

## User Stories
- As a visitor viewing a user's page, I want to see who approved that user so that I can gauge trust/provenance without navigating to each individual profile.
- As a moderator/admin, I want a quick summary of approvers on the user page so that I can spot patterns (e.g., a single approver vouching for many accounts) without cross-referencing individual profile pages.

## Core Requirements
- Add one line after the existing "Approved profiles / Combined threads / Combined posts" summary stats on `username.php`.
- Line reads `Approved by: <usernames>`, comma-separated.
- Usernames are sourced only from the identity's **approved** profiles (unapproved profiles contribute nothing).
- List is de-duplicated (an approver who approved multiple of this user's profiles appears once).
- Self-approvals are excluded from the list.
- If no qualifying approvers exist (e.g., zero approved profiles), the line is omitted entirely.

## Shared Component Inventory
- `templates/pages/profile.php` (and `templates/pages/forte_profile.php`) already render a single profile's `Approved by: <link>` using `approved_by_label` / `approved_by_profile_slug`. This feature reuses that same underlying data field (`approved_by_label`) as its source of truth, but aggregates across multiple profiles into one line rather than reusing the per-profile markup directly — no existing component already produces an aggregated, de-duplicated approver list, so this aggregation logic is new.
- No new API endpoint needed; `ProfilePageController::username()` already loads the identity's profiles and can access their `approved_by_*` fields.

## Simple User Flow
1. Visitor navigates to `/user/{username}`.
2. Page loads and computes the distinct set of approvers across the identity's approved profiles, excluding self-approvals.
3. If the set is non-empty, a new "Approved by" line renders directly below the existing summary stats.

## Success Criteria
- Visiting `/user/{username}` for a user with one or more approved profiles shows a correct, de-duplicated, comma-separated list of approver usernames.
- A user who self-approved a profile does not see themselves listed as their own approver.
- A user with zero approved profiles shows no "Approved by" line (no empty/broken output).
