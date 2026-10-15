# Authentication Modes and Access

Use this reference to distinguish a saved browser identity, an authenticated session, and approval to enter a members-only site. Reviewed on 2026-10-13 against the source snapshot linked below. The [trust model](../architecture/public_architecture_and_trust.md) explains public records and private-key ownership.

## Identity, Authentication, and Approval

- **Anonymous participation:** no author identity is attached to the submitted post. This is an attribution choice, not proof that the visitor has no browser key or server session.
- **Browser identity:** a locally stored OpenPGP keypair, whether automatically prepared as `guest`, created with a chosen name, or restored from a saved private key. The fingerprint identifies the key; a display name is not an authentication credential.
- **Published identity:** the server knows the public key and identity records. Possessing only a public key, or publishing it, does not prove possession of the private key for a later request.
- **Signed action:** the browser signs an action and the server verifies its signature. Signing a post is distinct from establishing a viewer session, and neither automatically grants approval.
- **Authenticated session:** the browser signs a server challenge with its saved private key; the server verifies it against the published identity and records that identity in a PHP session. An unapproved identity can authenticate successfully.
- **Approved member:** the identity's current profile has approval. Members-only access requires both an authenticated session and approval; a local key or identity hint alone is insufficient.
- **Identity hint:** a presentation hint that may select a profile or label. The hint cookie and a remembered Lobby identity are not an authenticated session.

Evidence: [authentication controller](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Http/AuthApiController.php), [identity-hint controller](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Http/IdentityHintController.php), and [application viewer/access resolution](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Application.php).

## Site Settings

Both flags default to `false`. A valid deployment environment value overrides the saved site flag; otherwise the saved value overrides the default. Operators can inspect effective values at `/tools/feature-flags/`. These settings are independent of theme/site-profile selection.

- **Public, automatic guest off:** `FORUM_APPROVED_MEMBERS_ONLY=false`, `FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED=false`. Public browsing needs no identity. Account Key or a signed-action flow can set one up when needed.
- **Public, automatic guest on:** public browsing remains available. The browser attempts to prepare a local `guest` keypair if none is saved; preparation does not grant approval or require a session.
- **Members-only, automatic guest off:** `FORUM_APPROVED_MEMBERS_ONLY=true`. Visitors need an authenticated approved identity for protected content; they can use the entry/recovery surfaces below to set up or restore their key.
- **Members-only, automatic guest on:** the same access requirement applies. Automatic key preparation can reduce setup work but cannot bypass approval.

Automatic guest preparation normally defers public-key publication. Account Key's per-browser “Publish ... immediately” preference changes that preparation behavior. Later identity-readiness flows, including authentication and vote prewarming, can publish a saved key independently of that preference. Disabling automatic preparation does not delete an existing browser keypair.

Evidence: [flag defaults](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php), [precedence](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Support/FeatureFlags/FeatureFlagEvaluator.php), and [browser setup/publication](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/public/assets/browser_signing.js) (`scheduleAutomaticGuestIdentity`, `ensureStoredIdentityReady`).

## Access by Viewer State

Apply these rows to the selected site setting. Endpoint-specific permissions still apply after the site access gate passes.

- **No saved key, including anonymous visitors:** public pages are readable. On members-only sites, use entry/recovery surfaces; protected HTML navigation may first show Reconnecting and then Lobby. Set up or restore a key to authenticate.
- **Saved guest or named key, not yet published:** public browsing is unchanged. The identity must be published before challenge authentication can succeed; browser authentication ensures publication/readiness where needed. Members-only content remains gated pending authenticated approval.
- **Published identity or hint, no authenticated session:** public browsing is unchanged. A hint cannot unlock members-only content, even if the referenced profile is approved. A saved private key can restore the authenticated session.
- **Authenticated, unapproved identity:** public pages remain available, subject to each action's rules. On members-only sites, Lobby, Account Key, documentation, and the session-bound own profile remain accessible; other protected content is denied. Bare Board/Threads navigation redirects to Lobby; other protected application requests return access-required responses.
- **Authenticated, approved identity:** the members-only site gate passes. This is not blanket operator permission; action signatures and role checks still apply.

The authentication API has a naming trap: `/api/authenticate_identity` can return `status=ok` with `approved=0`, but `/api/auth_status` returns `status=authenticated` (HTTP 200) only for an approved authenticated viewer. It returns HTTP 401 otherwise, including for an authenticated unapproved identity. Do not interpret that status endpoint as a general “does any session identity exist?” test.

Evidence: [site access gate and redirects](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Application.php) (`membersOnlyRequestAllowed`, `membersOnlyLobbyRedirect`) and the authentication controller above. [Server-side access/session tests](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/tests/LocalAppSmokeTest.php) cover approved and pending viewers, public sessions, and protected navigation.

## Members-Only Entry and Route Exceptions

The site gate allows the following before approval; individual handlers still validate methods, input, signatures, and invitation state:

- Lobby (`/lobby/`) and Account Key (`/account/key/`), including their non-trailing-slash forms.
- Documentation GET requests under `/docs`, `/docs/`, and `/docs/...`.
- Authentication/status and identity setup: `/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status`, `/api/set_identity_hint`, `/api/clear_identity`, `/api/link_identity`, `/api/prepare_identity`, and `/api/create_identity`.
- Invitation inspection/redemption entry points: `/api/inspect_invitation`, `/api/prepare_invitation_redemption` and `/api/create_prepared_invitation`. Being allowed through the gate does not authorize arbitrary invitation creation.
- GET of `/profiles/<profile_slug>` for the own profile resolved from the authenticated or remembered Lobby identity. This is not a general exception for all profile or `/user/...` URLs.

Public assets required to load the site are served before the application gate. `/api/version` also responds before it. The internal media-preview endpoint has separate handling; none of these exceptions grants member-content access. Protected feeds, downloads, backups, and APIs do not receive automatic HTML reauthentication. Members-only mode disables the front controller's public static-page and offline-snapshot fast paths; it cannot retract copies already downloaded while content was public.

Evidence: [application request ordering/allowlist](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Application.php), [front controller](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Host/FrontController.php), and [public documentation tests](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/tests/PlatformDocsPageTest.php).

## Participation and Invitations

- The compose UI offers anonymous submission without an author identity, as well as a browser-signed path. On a members-only instance, choosing anonymous attribution does not bypass the site gate.
- Profile naming preparation/finalization (`/api/prepare_profile_name`, `/api/finalize_profile_name`) is available before member approval, but changes require the identity owner's signature.
- Browser-signed posts, reactions, approvals, and invitations have their own signature/eligibility checks. A session is not a substitute for signing an action. A `guest` label neither proves anonymity nor supplies member or operator privileges.
- Issuing invitations through `/api/prepare_invitation` requires an authenticated approved viewer, including on public sites. Finalizing issuance requires the issuer's signature and approval. Redemption uses a valid, unexpired, unused, unrevoked invitation and a signed recipient identity; successful redemption derives approval through the issuer's existing approval. Targeted links redeem automatically; signed same-recipient recovery and already-approved entry are described in [Invitation entry](invitation_entry.md).
- Approval-dependent and operator features keep their own rules. For example, mutable site-flag changes require a root-approved operator's signed action. See [private messaging](../architecture/private_messaging.md) for its separate sender/recipient requirements.

Evidence: [write and signature validation](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Write/LocalWriteService.php), [invitation controller](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Http/IdentityApprovalAndInvitationApiController.php), and [browser signing/setup tests](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/tests/BrowserSigningNormalizationTest.php).

## Session Persistence and Recovery

The implementation uses PHP viewer sessions, not application-owned opaque tokens or stateless login tokens. The cookie is configured for 34,560,000 seconds (400 days), with HttpOnly, SameSite=Lax, strict session IDs, and Secure when PHP sees HTTPS. This is a cookie lifetime, not a guarantee of server-side session retention: PHP/host cleanup and browser policies can remove state sooner. No separate application idle or absolute session timeout is enforced. Approval is read from the current profile.

1. **Lost/expired session, saved key:** for eligible protected HTML GETs without an authenticated profile, members-only mode serves Reconnecting (HTTP 401). The browser signs a challenge, authenticates, and returns an approved member to the validated destination. A fresh challenge expires after five minutes; the browser retries once on the explicit missing/expired-challenge error.
2. **Following links from an approved members-only page:** the installed navigation guard checks session status before eligible same-origin navigation and authenticates if needed. New tabs, modified clicks, downloads, API links, and dedicated invitation links are not all handled by this guard; server-side gating remains necessary.
3. **Public browsing:** pages configured for opportunistic authentication can restore a saved identity without requiring one to read public content. A keyless visitor stays on the public page; an unapproved identity can return to it after authentication.
4. **Missing key:** use Account Key to set up a browser, or Advanced / technical details → Restore Private Key to restore a saved unencrypted browser private key. A public key or remembered name alone cannot sign a challenge. A newly generated key is a different identity and does not inherit the old key's approval. Protected recovery routes to Lobby or Account Key depending on the entry path.
5. **Pending approval:** authentication verifies identity but leaves members-only access pending. Use Lobby to obtain approval or redeem a valid invitation; repeatedly generating keys does not solve the approval requirement.
6. **Network, loader, or signature failure:** Reconnecting provides Account Key/Lobby fallback links and a status message; the navigation guard falls back to Account Key. Restore connectivity or resolve the displayed key/loader problem, then retry. Do not assume the failed navigation or write completed.

Return targets are validated local paths. Recovery preserves path/query; in-page navigation can also retain a fragment that a cold HTTP request never sends. These authentication flows do not automatically replay POSTs or write/API requests. API clients must establish their session and meet the endpoint's own action requirements explicitly.

Clearing server authentication via `/api/clear_identity` also retains a weaker Lobby identity; it does not itself erase the browser key. Account Key's clear controls remove the active saved keypair as well and provide an Undo path. A retained or restored private key can authenticate again, so clearing session state alone is not durable key revocation.

Evidence: [PHP session policy](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/src/ForumRewrite/Application.php) (`startViewerSession`, `resumeViewerSession`), [browser recovery](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/public/assets/private_site_auth.js), [navigation guard](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/public/assets/auth_navigation.js), [authentication tests](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/tests/PrivateSiteAuthTest.php), and [navigation tests](https://github.com/gulkily/v3/blob/7948badf6555aa52c2515981caba77bd5860134d/tests/AuthNavigationTest.php). Key restoration/clearing is in the browser setup source linked above.

## Evidence Boundary

Source and test links identify the reviewed repository revision, rather than silently following future changes. The access model is an account of current code, not a deployment security audit or a promise that every endpoint has identical permissions. Host-specific session retention and a visitor's actual browser storage availability remain environment-dependent. The [implementation summary](../plans/authentication_modes_documentation/authentication_modes_documentation_step4_implementation_summary.md) records which checks were executed and which behavior was inspected only. The [session design history](../plans/session_reauthentication_reference.md) retains earlier alternatives; they are not additional supported authentication modes.
