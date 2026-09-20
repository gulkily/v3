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

## Stage 3 - Remember successful browser-local choices
- Changes:
  - Added a bounded, de-duplicated browser-local destination history.
  - Filtered stored values through the same client-side suggestion constraints.
  - Persisted a selected destination only after final invitation creation succeeds; unavailable storage does not interrupt issuance.
- Verification:
  - `node --check public/assets/invite_issuance.js`
  - `./v3 test InvitationIssuanceTest`
  - Attempted `./v3 test`; the full suite reported unrelated existing failures in activity/signature and lazy-compose tests before exceeding the 60-second check window.
- Notes:
  - Destination history remains browser-local and is intentionally absent from invitation API payloads beyond the selected destination.

## Stage 4 - Separate unfiltered destination chooser
- Changes:
  - Replaced the native filtered datalist with an unfiltered “Choose destination” disclosure menu.
  - Kept the typed destination field blank and moved the clicked-through page to a selectable source-location option.
  - Reused the chooser for curated and browser-local recent destinations; choosing an option fills and refocuses the text field.
- Verification:
  - `node --check public/assets/invite_issuance.js`
  - `./v3 test InvitationIssuanceTest`
- Notes:
  - This member-requested refinement avoids browser-specific datalist filtering while retaining free-form entry.

## Stage 5 - Inline destination chooser button
- Changes:
  - Positioned the “Choose destination” disclosure button in the right side of the destination text field.
  - Positioned the unfiltered option menu beneath the inline button and preserved the compact option-button layout.
- Verification:
  - `node --check public/assets/invite_issuance.js`
  - `./v3 test InvitationIssuanceTest`
- Notes:
  - The existing disclosure control retains native keyboard activation while presenting as an input-adjacent button.

## Stage 6 - Equal chooser button inset
- Changes:
  - Moved the field's external top spacing to its wrapper.
  - Applied an equal 0.35rem top, right, and bottom inset to the inline chooser button.
- Verification:
  - `node --check public/assets/invite_issuance.js`
  - `./v3 test InvitationIssuanceTest`
- Notes:
  - The button fills its inset area vertically while retaining room for the destination text.
