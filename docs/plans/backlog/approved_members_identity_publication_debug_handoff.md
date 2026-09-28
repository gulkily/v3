# Approved Members Identity Publication: Debug Handoff

## Purpose

This document captures the current state of the approved-members-only feature and the remaining authentication/profile-access problem. It is intended for a higher-capability debugging pass.

## Product Requirements

- The feature is controlled by the site-independent flag `FORUM_APPROVED_MEMBERS_ONLY`.
- A user creates or imports a browser OpenPGP keypair; the public key should be shared automatically.
- A new identity/profile should be created automatically.
- Existing profiles should be reused without duplicate creation.
- Existing approval seeds should apply to the profile.
- Approved users should access the full site.
- Unapproved users should access only Lobby, Account, and their own profile.
- Unauthenticated or unapproved access to all other content, APIs, feeds, backups, downloads, and static content artifacts should return 404.
- An authenticated user should be able to view their own profile regardless of approval status.

## Current Branch and Commits

Branch:

```text
feature/approved-members-identity-publication
```

FDP planning commit and stage commits:

```text
669330e docs: plan approved members identity publication
922c575 feat(stage 1): make identity lookup private-mode safe
1a5f9b2 feat(stage 2): publish lobby browser identities automatically
98caf0a feat(stage 3): verify approved private profile access
57a00d6 feat(stage 4): close private lobby query access
efac374 feat(stage 5): document automatic identity publication
```

Earlier related private-access commits are also in the branch history:

```text
c279e1d fix(private auth): derive identity from saved public key
340febc fix(private auth): persist session before response
29fb26d fix(account): show browser profile link before session refresh
```

## Verified Canonical State for the Reported Identity

Reported identity:

```text
openpgp:d0ee2c64baaf0a7e1e6876465e2deca112f97682
```

Current local repository: `state/local_repository`.

The following records now exist:

```text
records/approval-seeds/openpgp-d0ee2c64baaf0a7e1e6876465e2deca112f97682.txt
records/identity/identity-openpgp-d0ee2c64baaf0a7e1e6876465e2deca112f97682.txt
records/public-keys/openpgp-D0EE2C64BAAF0A7E1E6876465E2DECA112F97682.asc
records/posts/2026/09/13/bootstrap-20260913184457-629af228.txt
```

The approval seed contains:

```text
Approved-Identity-ID: openpgp:d0ee2c64baaf0a7e1e6876465e2deca112f97682
```

The read model contains:

```text
identity_id:         openpgp:d0ee2c64baaf0a7e1e6876465e2deca112f97682
profile_slug:        openpgp-d0ee2c64baaf0a7e1e6876465e2deca112f97682
username:            ilyag
is_approved:         1
signer_fingerprint:  D0EE2C64BAAF0A7E1E6876465E2DECA112F97682
```

Therefore the identity/profile/approval data is no longer missing.

## Expected Server Authentication Model

On each private-mode request:

1. PHP reads the `PHPSESSID` cookie.
2. PHP loads `$_SESSION['authenticated_identity_id']`.
3. The application looks up that identity in the read model.
4. The application checks `profiles.is_approved`.
5. The request is either allowed or returns 404.

The browser’s localStorage fingerprint is not itself an authenticated session. It is used to identify the key during the challenge/signature handshake.

## Relevant Code Paths

### Request/session setup

`src/ForumRewrite/Application.php`:

- `handle()` starts a session when private mode is enabled.
- `resolveViewerProfileFromIdentityHint()` uses only the authenticated session in private mode.
- It intentionally does not trust the old `identity_hint` cookie in private mode.
- `membersOnlyRequestAllowed()` allows Lobby, Account, identity lifecycle endpoints, and an authenticated user’s matching `/profiles/<slug>` route.
- `membersOnlyLobbyRedirect()` redirects only a plain `/` or `/threads` request with no query string.

### Identity authentication

- `GET /api/auth_challenge` creates a challenge in the PHP session and explicitly calls `session_write_close()`.
- `POST /api/authenticate_identity` verifies the detached signature against the canonical profile public key.
- On success it writes `$_SESSION['authenticated_identity_id']`, explicitly closes the session, and returns `approved=1` or `approved=0`.

### Browser flow

`public/assets/private_site_auth.js`:

- Reads the saved browser public/private keys.
- Normalizes an optional `openpgp:` prefix.
- Derives the fingerprint from the saved public key.
- Verifies the saved private key has the same fingerprint.
- In Lobby, calls `window.__forumBrowserIdentity.ensureReadyIdentity()` before authentication.
- Fetches a challenge, signs it, posts the signature, then navigates to `/` when `approved=1`.

`public/assets/browser_signing.js`:

- Performs prepare/sign/finalize identity publication.
- In private mode, it no longer calls the protected general `/api/get_profile` endpoint to detect an existing identity.
- The identity prepare endpoint’s `Identity already exists for this fingerprint.` response is now the duplicate signal.

## Access Behavior Verified Locally

With `FORUM_APPROVED_MEMBERS_ONLY=true`:

- An unauthenticated `/profiles/openpgp-d0ee...` request returns 404.
- An unauthenticated content request returns 404.
- Lobby and Account render.
- Root RSS returns 404 rather than redirecting to Lobby.
- A manually populated PHP session containing the approved identity returns 200 for:
  - the identity’s own profile
  - the board root

The focused regression test proving this is:

```bash
php tests/run.php LocalAppSmokeTest::testApprovedPrivateSessionCanViewOwnProfileAndBoard
```

The private lobby route test is:

```bash
php tests/run.php LocalAppSmokeTest::testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces
```

Both pass.

## Original Failure Timeline

For the new identity, the initial observed state was:

- approval seed present
- temporary prepared identity bootstrap present
- identity record absent
- public-key record absent
- profile absent

That explained the initial 404: an approval seed alone does not create a profile.

After the automatic Lobby publication changes, the identity was successfully finalized. The canonical identity and public-key records now exist, and the read model marks the profile approved.

The profile still returns 404 in the user’s browser, which means the remaining failure is now downstream of canonical identity creation.

## Remaining Contradiction

The server returns 404 for the user’s own profile even though:

- the profile exists;
- it is approved;
- the route works when a valid authenticated PHP session is manually supplied.

This means the browser request is reaching the private gate without a usable `authenticated_identity_id` session value, or the user’s running process is not using the same repository/database/session environment that was inspected.

The current code does not expose enough diagnostic information in the browser because `private_site_auth.js` silently returns on most failures.

## Highest-Value Next Investigation

Use the browser Network panel and inspect the exact same-origin sequence on `http://127.0.0.1:8000`:

1. `/lobby/` response:
   - confirm it contains the current fingerprinted `browser_signing` and `private_site_auth` scripts;
   - confirm `data-approved-members-only="1"`.
2. `/api/auth_challenge`:
   - status must be 200;
   - response must set or reuse `PHPSESSID`.
3. `/api/authenticate_identity`:
   - request must include the same `PHPSESSID` cookie;
   - status must be 200;
   - response must contain `approved=1`.
4. The subsequent `/` request:
   - must include the same `PHPSESSID` cookie.
5. The profile request:
   - must include the same `PHPSESSID` cookie;
   - should then return 200.

Interpretation:

- No authentication requests: stale/missing scripts or localStorage keypair state.
- Challenge 200, authentication 404/400: route/configuration or identity input problem.
- Authentication 403: signature/public-private key mismatch or verification failure.
- Authentication 200 `approved=1`, but `/` redirects to Lobby: session cookie is not retained/sent, or the running request uses a different PHP session store.
- Profile 404 after `/` succeeds: profile slug mismatch or a separate server process/repository/database configuration.

## Environment Checks

The reported URL uses `127.0.0.1:8000`. The active local process observed during debugging was:

```text
php -S 127.0.0.1:8000 -t /home/wsl/v3/public /home/wsl/v3/public/router.php
```

Confirm the process was restarted after the latest commits and is not an older process, different checkout, or different site-scoped repository/database.

The default local repository is:

```text
/home/wsl/v3/state/local_repository
```

The default read model is:

```text
/home/wsl/v3/state/cache/post_index.sqlite3
```

The PHP CLI session save path observed was:

```text
/var/lib/php/sessions
```

If the browser uses a different PHP/web-server configuration, its session save path may differ.

## Known Test Status

Passing focused checks:

```bash
php tests/run.php FeatureFlagEvaluatorTest
php tests/run.php BrowserSigningNormalizationTest
php tests/run.php LocalAppSmokeTest::testApprovedPrivateSessionCanViewOwnProfileAndBoard
php tests/run.php LocalAppSmokeTest::testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces
```

PHP and JavaScript syntax checks pass for changed runtime files.

The complete legacy test suite is not green and includes unrelated existing failures. It should not be used as evidence that this specific session issue is resolved.

## Important Security Constraint

Do not solve this by making `/profiles/<slug>` or `/api/get_profile` publicly readable in private mode. The server must establish identity through a verified key signature and session before exposing the user’s own profile or any protected content.
