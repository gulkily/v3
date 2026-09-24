# Invite Destination Selector — Step 2: Feature Description

## Problem

The invites page always shows a disabled destination input and makes members type every destination, even when they are repeatedly inviting people to common or recently used locations.

## User stories

- As an approved member, I want the destination field hidden until I opt in so that the default invite flow stays focused.
- As an approved member, I want to type any permitted internal destination or select a suggestion so that I can create an invite quickly.
- As an approved member, I want my recently used invite destinations available on this browser so that I can reuse them without server-side tracking.
- As an approved member, I want useful shortcuts before I have any recents so that the selector is useful immediately.

## Core requirements

- The destination field group is visible and enabled only while “Include destination URL” is checked; unchecking it excludes any destination from the invite.
- The destination control accepts typed internal paths and presents suggestions in an editable dropdown.
- Suggestions combine a small curated set—Board, Activity, Users, and Tools—with valid destinations recently used to create invitations in the same browser.
- A destination joins the recent list only after a successful invitation, while the location supplied when the invite page opens may be offered as a suggestion.
- All destination values continue through the existing canonical internal-path validation; no destination history is sent to or retained by the server.

## Shared component inventory

- Invites page template: extend the canonical issue form; do not create a second destination form.
- Invitation issuance script: extend its existing checkbox synchronization and issue-success behavior to control the field group and maintain suggestions.
- Shared Invite navigation action: reuse its current-location handoff as the source-location suggestion.
- Invitation preparation and creation APIs: reuse unchanged; they remain the canonical signing and issuance path.
- Canonical invitation destination validation: reuse unchanged as the final authority for permitted paths.
- Browser-local storage: add a narrowly scoped client-side preference because no existing shared recent-location component exists and cross-device history is out of scope.

## Simple user flow

1. An approved member opens Invite, optionally from a page whose current location is supplied to the form.
2. They check “Include destination URL,” revealing the editable destination selector.
3. They type a valid path or choose a curated or recent suggestion.
4. After a successful invitation, that valid selected path is available as a recent suggestion in that browser.

## Success criteria

- On initial load and after unchecking, no destination label or control is visible or enabled.
- Checking reveals an editable selector with all four curated shortcuts and any available valid local recents.
- Members can submit a typed valid internal path or a suggestion through the existing invite flow.
- A successfully used destination is suggested on the next invite in the same browser; failed or invalid values are not retained.
- No API, canonical record, or server-side store receives destination-history data beyond the single destination already associated with an issued invitation.
