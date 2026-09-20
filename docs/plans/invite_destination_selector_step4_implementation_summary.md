# Invite Destination Selector — Step 4: Implementation Summary

## Stage 1 - Gate the destination field
- Changes:
  - Grouped the destination label and input so the group can be hidden with its checkbox disabled.
  - Added accessibility state for the checkbox and an editable dropdown host for later suggestions.
  - Added focused invitation-issuance browser-script and template coverage.
- Verification:
  - `node --check public/assets/invite_issuance.js`
  - `./v3 test InvitationIssuanceTest`
- Notes:
  - Suggestion sources and browser-local recents are deferred to later stages.

## Stage 2 - Offer curated and source-location suggestions
- Changes:
  - Added Board, Activity, Users, and Tools as curated editable-dropdown choices.
  - Added the valid source location supplied to the Invite page without duplicating an existing choice.
  - Mirrored the existing internal-path constraints for client-side suggestion filtering only.
- Verification:
  - `node --check public/assets/invite_issuance.js`
  - `./v3 test InvitationIssuanceTest`
- Notes:
  - Server-side canonical validation remains the authority for the submitted destination.
