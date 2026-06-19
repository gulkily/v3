# Account Setup Auto-Publish Public Key Plan V1

## Goal

When a user clicks `Set up this browser` or `Re-setup / change name` on `/account/key/`, a successful setup should publish the generated public key to the server before the flow reports success.

The Account page should behave like the first signed compose/reaction path: after user consent and key generation, the browser calls `/api/link_identity`, records the published fingerprint on success, syncs the identity hint, and updates the Account UI.

## Current State

- `templates/pages/account_key.php` renders the visible Account Key surface.
- `public/assets/browser_signing.js` owns browser-local key generation, saved-key rendering, identity-hint sync, and public-key publishing.
- Shared signed-action setup already uses `ensureReadyIdentity()`, which:
  - prompts for a username when needed
  - generates and stores an OpenPGP keypair
  - publishes the public key through `/api/link_identity`
  - stores `forum_pki_published_fingerprint`
  - syncs the identity hint
- The Account page generate button currently calls `generateBrowserKey()` directly and then shows `Browser keypair ready for {username}. Submit the form to link it.`
- Because of that, the simple Account setup path can leave a valid local keypair that has not been linked server-side until the user opens advanced details and submits the manual form.

## Desired Behavior

- Clicking `Set up this browser` with no saved keypair prompts for a username, generates the keypair, publishes the public key, syncs the identity hint, fills the advanced public-key field if present, and shows a final success message.
- Clicking `Re-setup / change name` prompts for a username, generates a replacement keypair, clears any stale published-fingerprint state as part of the replacement, publishes the new public key, syncs the identity hint, fills the advanced public-key field if present, and shows a final success message.
- If `/api/link_identity` reports that the identity already exists for the fingerprint, treat it as success, matching the existing `publishPublicKey()` behavior.
- If publishing fails, keep the generated keypair in local storage so the user can retry through the same button or the advanced manual form.
- Do not remove the advanced manual `Link identity` form; it remains the progressive-enhancement and recovery path.

## Non-Goals

- Do not change canonical identity record semantics.
- Do not change `LocalWriteService::linkIdentity()` or `/api/link_identity` validation.
- Do not publish private key material.
- Do not generate a browser keypair without the existing username prompt/consent.
- Do not require JavaScript for the advanced manual account-linking fallback.

## Implementation Slices

### Slice A: Account Generate Flow Uses Shared Publish Step

Focus:

- update only the Account page generate-button click path.

Work:

- In `bindAccountKeyPage()` in `public/assets/browser_signing.js`, after `generateBrowserKey(root, username)`, call `publishPublicKeyWithRetry(root)` before reporting success.
- Set the status to `Publishing your public key in the background...` before the publish call so the simple Account UI mirrors the advanced status through its existing mutation observer.
- Keep the existing `publicKeyField.value = localStorage.getItem(storageKeys.publicKey) || ""` update after generation and/or after publish so advanced details remain populated.
- Replace the final message `Browser keypair ready for {username}. Submit the form to link it.` with a success message that reflects server publication, such as `Browser keypair ready for {username}. Public key linked.`
- Confirm `generateButton.disabled` is restored in `finally` on both generation and publish failures.

Acceptance:

- A successful Account setup performs a `/api/link_identity` request before showing the final success state.
- `forum_pki_published_fingerprint` is written after success.
- The button remains usable after a failed publish.

### Slice B: Stale Published-Fingerprint Safety

Focus:

- make replacement keypairs unambiguously require publication.

Work:

- Review `generateBrowserKey()` and ensure replacing a keypair cannot leave `forum_pki_published_fingerprint` pointing at an older fingerprint.
- If needed, remove `storageKeys.publishedFingerprint` before or immediately after storing a newly generated keypair, before the publish attempt.
- Preserve the existing successful publish behavior that writes the new fingerprint.

Acceptance:

- After `Re-setup / change name`, a failed publish cannot make the UI classify the new keypair as already published because of an old marker.
- Existing signed-action setup still publishes when the stored published fingerprint does not match the stored key fingerprint.

### Slice C: Focused JavaScript Tests

Focus:

- cover the Account page button behavior with the existing Node-executed JS test style.

Work:

- Add a test in `tests/BrowserSigningNormalizationTest.php` that mocks:
  - `document.querySelector('[data-account-key-root]')`
  - the generate button click handler
  - `window.prompt`
  - `window.openpgp.generateKey()` and `readKey()`
  - `fetch()` for `/api/set_identity_hint` and `/api/link_identity`
- Trigger the captured generate-button click handler.
- Assert:
  - generated username is the prompted username
  - `/api/link_identity` is called with `public_key=...`
  - `forum_pki_published_fingerprint` is stored
  - the final account status is the new linked success message
  - the public-key textarea is populated.
- Add a second focused assertion or test for replacement setup if Slice B changes `generateBrowserKey()` to clear stale published-fingerprint state.

Acceptance:

- The new test fails against the current behavior because no `/api/link_identity` call occurs.
- The new test passes after Slice A.

### Slice D: Account Page Copy and Smoke Coverage

Focus:

- align visible copy with the new automatic linking behavior.

Work:

- Review `templates/pages/account_key.php` inline `friendlyStatusMessage()` mappings for any copy that still tells the user to submit the form after setup.
- If the final JS status message changes, add or update a friendly mapping so the simple status line says something direct, such as `All set! Posting as {username}.`
- Update `tests/LocalAppSmokeTest.php` only if rendered Account page copy or button labels change.

Acceptance:

- The simple Account surface no longer implies that advanced manual submission is required after a successful setup.
- The advanced manual form remains visible under `Advanced / technical details`.

## Verification

- `node --check public/assets/browser_signing.js`
- `php tests/run.php BrowserSigningNormalizationTest`
- If Account markup changes: `php tests/run.php LocalAppSmokeTest`

## Rollout Notes

- This is a client-flow change over the existing `/api/link_identity` endpoint; no data migration is required.
- Existing users with an unpublished browser keypair can click `Re-setup / change name` to generate and publish a replacement, or use the advanced manual form.
- Existing signed-action flows keep their current `ensureReadyIdentity()` behavior.

## Implementation Log

- 2026-06-19: Slice A implemented on branch `account-setup-auto-publish-public-key`. The Account page generate-button path now calls `publishPublicKeyWithRetry()` after key generation, keeps the advanced public-key field populated, and reports linked success only after publication completes.
- 2026-06-19: Slice B implemented. Generating a new browser keypair now clears `forum_pki_published_fingerprint` before any publish attempt, so replacement setup cannot inherit stale publication state from the previous keypair.
