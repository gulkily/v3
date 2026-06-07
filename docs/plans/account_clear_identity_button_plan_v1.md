# Clear Identity Button On Account Page

## Goal

Add a clear identity control to `/account/key/` so a user can remove the browser-local account identity from the visible account surface without opening advanced technical details.

## Current State

- `templates/pages/account_key.php` renders the account page and simple account status surface.
- `public/assets/browser_signing.js` owns browser-local identity storage behavior for `forum_pki_username`, `forum_pki_public_key`, `forum_pki_private_key`, and `forum_pki_identity_id`.
- The advanced account controls already include `data-action="clear-browser-key"`, which clears the saved keypair and related localStorage values.
- `tests/BrowserSigningNormalizationTest.php` and `tests/LocalAppSmokeTest.php` cover account-page browser signing behavior and account-page markup.

## Implementation Plan

1. Add a simple-surface button on `templates/pages/account_key.php` labeled `Clear identity`.
2. Reuse the existing browser-signing clear action where possible so the new control clears the same identity state as the advanced control.
3. Update the account simple UI sync code so the button is visible only when a browser identity/keypair is present.
4. Ensure clearing identity updates the visible signed-in username, status badge, and saved identity field without a page reload.
5. Add or update tests covering the new button markup and the clear behavior from the simple account surface.

## Verification

- Run the focused PHP test suite that covers account page rendering and browser signing JavaScript.
- Manually review the account page markup for progressive enhancement: without JavaScript, the advanced clear action remains available.
