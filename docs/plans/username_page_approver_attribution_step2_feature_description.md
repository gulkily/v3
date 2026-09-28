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
- **(Amended)** Seeded root approvals (`approved_by_label === 'root'`, `approved_by_identity_id === null`) are **included** in the list rather than skipped, shown as `root`; multiple root-approved profiles still collapse to a single `root` entry.
- **(Amended)** Each entry in the list links to the approver's profile (`/profiles/{approved_by_profile_slug}`) when a profile slug exists, matching the existing single-profile "Approved by" link behavior; `root` has no profile slug, so it renders as plain (unlinked) text.
- If no qualifying approvers exist (e.g., zero approved profiles), the line is omitted entirely.

## Shared Component Inventory
- `templates/pages/profile.php` (and `templates/pages/forte_profile.php`) already render a single profile's `Approved by: <link-or-plain-text>` using `approved_by_label` / `approved_by_profile_slug` (linking only when a slug is present, else plain text — this is exactly the "root has no link" case). This feature reuses both that data source and that same link/plain-text conditional, rather than inventing a new link convention.
- No new API endpoint needed; `ProfilePageController::username()` already loads the identity's profiles and can access their `approved_by_*` fields.

## Simple User Flow
1. Visitor navigates to `/user/{username}`.
2. Page loads and computes the distinct set of approvers (label + optional profile slug) across the identity's approved profiles, excluding self-approvals but including root/seed approvals.
3. If the set is non-empty, a new "Approved by" line renders directly below the existing summary stats, each entry linked when a slug exists.

## Success Criteria
- Visiting `/user/{username}` for a user with one or more approved profiles shows a correct, de-duplicated, comma-separated list of approver usernames, each linked to its profile where a slug exists.
- A root/seed-approved profile contributes a single unlinked `root` entry to the list.
- A user who self-approved a profile does not see themselves listed as their own approver.
- A user with zero approved profiles shows no "Approved by" line (no empty/broken output).
