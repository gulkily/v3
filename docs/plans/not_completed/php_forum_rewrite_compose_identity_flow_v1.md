# PHP Forum Rewrite Compose Identity Flow V1

This document records the agreed V1 behavior for browser keypair generation and automatic identity bootstrap on compose routes.

## Goal

Make the keypair/bootstrap process transparent for brand-new users on compose pages while keeping the backend write/bootstrap contract unchanged.

The user-visible requirement is:

- the user should normally only need to choose a username
- key generation and public-key publication should happen automatically when the user actually tries to post
- existing browser-local keypairs should be reused without prompting again

## Decision Summary

### Trigger point

Do not generate a browser keypair on page load.

Instead:

- watch both `/compose/thread` and `/compose/reply`
- trigger the identity flow only when the user presses the submit button for the first real post attempt

Reason:

- avoids creating unused keys for abandoned drafts
- avoids surprising side effects on page load
- keeps the compose page usable immediately

### Existing keypair rule

Treat a browser-local keypair in local storage as "already have a key".

If a keypair already exists:

- do not prompt for username again
- do not generate a new keypair
- continue with identity publication only if the browser does not yet consider the key published

### Username prompt behavior

On first send without a keypair:

1. prompt for username
2. normalize the value to the existing username rules
3. if the prompt is dismissed or blank, ask for explicit confirmation to continue as `guest`
4. if the user declines that fallback, cancel submission and keep the draft intact

The browser should store a cancellation marker, but submission attempts should still prompt again when the user presses send later.

### Automatic bootstrap

After a keypair exists but before the compose form submits:

- publish the public key in the background using the retained `/api/link_identity` flow
- set the identity-hint cookie via `/api/set_identity_hint`
- then continue the original thread/reply submission

For the earlier bridge slice, automatic compose bootstrap could use the existing stable bootstrap anchor post `root-001`.

Current implementation note:

- normal identity bootstrap may now auto-create a hidden bootstrap post when no explicit `bootstrap_post_id` is supplied

### Failure handling

If any automatic step fails:

- keep the compose draft intact
- show a clear inline error
- do not discard generated local key material if generation already succeeded
- point the user to the manual `/account/key/` flow as fallback

### Scope boundary

This slice is limited to browser-side UX and compose/account integration.

It does not change:

- canonical record formats
- `/api/link_identity`
- thread/reply write APIs
- git commit behavior
- read-model/storage contracts

## Implementation Notes

V1 implementation should:

- reuse the existing browser local-storage key model from the account page
- reuse the vendored browser OpenPGP asset
- reuse the existing `/api/set_identity_hint` route
- reuse the existing `/api/link_identity` route
- attach compose enhancement only to `/compose/thread` and `/compose/reply`

## Acceptance Criteria

This flow is complete when:

- first submit on compose generates a keypair only when needed
- the user is prompted for username only at submit time
- existing browser-local keypairs skip regeneration
- public-key bootstrap is attempted automatically before the post submit continues
- compose drafts remain intact on failure
- manual account-key fallback remains available
