# Invite Destination Selector — Step 3: Development Plan

## Stage 1 — Gate the destination field
- Goal: Show and enable the destination selector only while the member opts in.
- Dependencies: Approved Step 2 feature description.
- Expected changes: Group the existing label and input in the invites template; extend the existing checkbox synchronization to toggle visibility, disabled state, and matching accessibility state; make the input an editable dropdown host.
- Verification approach: Add focused browser-script coverage for initial, checked, and unchecked states; smoke-check the rendered invites markup.
- Risks or open questions:
  - Hidden controls must not submit a stale destination after the checkbox is cleared.
- Canonical components/API contracts touched: `templates/pages/invites.php`; `public/assets/invite_issuance.js`; existing `destination` request field.

## Stage 2 — Offer curated and source-location suggestions
- Goal: Make the editable dropdown useful on first use.
- Dependencies: Stage 1 selector shell; existing Invite navigation destination handoff.
- Expected changes: Add curated Board, Activity, Users, and Tools internal-path suggestions; offer the valid destination supplied when the Invite page opens; filter displayed dynamic candidates with a client-side mirror of the existing internal-path rules.
- Verification approach: Assert the rendered curated choices and browser behavior for a valid source location; cover rejection of external, fragment, and malformed candidates.
- Risks or open questions:
  - The source-location suggestion is a convenience only and must not weaken server-side validation.
- Canonical components/API contracts touched: `templates/pages/invites.php`; `public/assets/invite_navigation.js`; `public/assets/invite_issuance.js`; `LocalWriteService::normalizeInvitationDestination()` remains authoritative.

## Stage 3 — Remember successful browser-local choices
- Goal: Surface the member's latest valid invitation destinations without server-side history.
- Dependencies: Stage 2 candidate filtering; existing successful invitation response.
- Expected changes: Add bounded, de-duplicated browser-local recent-destination helpers; update recents only after invitation creation succeeds; merge recents with the curated choices without duplicates.
- Verification approach: Add browser-script coverage for ordering, deduplication, storage failure fallback, and no persistence after failed issuance; run focused PHP and JavaScript syntax checks.
- Risks or open questions:
  - Browser storage may be unavailable or cleared, so curated suggestions remain the fallback.
- Canonical components/API contracts touched: `public/assets/invite_issuance.js`; browser local storage; existing invitation preparation and creation APIs remain unchanged.
