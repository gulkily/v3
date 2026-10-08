# Private-Window Lobby Fallback: Step 3 Development Plan

## Stage 1
- Goal: Send a recovery-page visitor with no usable saved browser key to the permitted Lobby.
- Dependencies: Approved Steps 1–2; existing safe return-destination handling.
- Expected changes: Extend the browser identity recovery absent-key outcome to replace the recovery page with Lobby, carrying the validated requested destination as `return_to`; retain the current absent-key result for callers that have no requested destination.
- Verification approach: Manually open a protected URL in a private window on an approved-members-only instance and confirm the browser reaches Lobby with the requested destination retained.
- Risks or open questions:
  - Only an absent key may use the fallback; malformed keys and authentication failures must remain visible errors.
  - History replacement must prevent Back from returning the visitor to the same Reconnecting page.
- Canonical components/API contracts touched: `private_site_auth.js` recovery outcome and existing `/lobby/?return_to=` destination contract; no new API or database changes.

## Stage 2
- Goal: Prevent regression of keyless Lobby entry while preserving approved-member recovery.
- Dependencies: Stage 1; existing browser-authentication test harness.
- Expected changes: Add focused browser-authentication coverage for a missing key and protected return target, including Lobby navigation, safe target encoding, and no authentication request; retain or strengthen coverage that authentication failures remain visible and an approved key returns to its requested page.
- Verification approach: Run the focused private-site authentication tests, then the relevant approved-members-only application smoke coverage.
- Risks or open questions: Test doubles must provide `location.replace`, matching the recovery page's navigation behavior.
- Canonical components/API contracts touched: `PrivateSiteAuthTest` and existing private-site smoke coverage; no production contract beyond Stage 1.
