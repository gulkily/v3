# Production Slow Actions Slice 5 Identity Prewarm and Prepare Plan V1

This plan breaks out the identity-preparation part of Slice 5 from `production_slow_actions_client_responsiveness_plan_v1.md`. The goal is to make the first signed thread, reply, like, or flag feel intentionally in progress instead of frozen while OpenPGP loads, a browser keypair is generated, and the public key is linked.

## Goal

On pages with signed actions, the browser should begin safe identity preparation work before the first click and offer an explicit `Prepare browser identity` affordance when the user has not yet created or linked a browser identity.

The server remains authoritative for identity linking, fingerprint uniqueness, public-key parsing, profile state, approval state, canonical writes, git commits, read-model updates, and artifact invalidation.

## Non-Goals

- Do not change canonical identity record semantics.
- Do not change `LocalWriteService::linkIdentity()` validation or write behavior.
- Do not auto-generate or publish a browser keypair without user consent.
- Do not remove anonymous compose submit paths.
- Do not treat a browser keypair as linked until `/api/link_identity` succeeds or confirms the fingerprint already exists.
- Do not require JavaScript for the existing manual `/account/key/` flow.
- Do not persist private key material anywhere new; continue using the existing local browser storage behavior.

## Current State

- Thread, post, compose, and account-key pages already include `public/assets/openpgp_loader.js` and `public/assets/browser_signing.js`.
- `openpgp_loader.js` starts loading the selected OpenPGP bundle immediately when the script is present.
- `browser_signing.js` exposes `window.__forumBrowserIdentity.ensureReadyIdentity()` and inline identity progress states.
- Compose submits call `ensureComposeIdentity()` before optimistic thread or inline reply submission.
- Reaction clicks call `ensureReadyIdentity()` before optimistic tag application.
- Fresh sessions still wait until the first signed action to prompt for a username, generate the keypair, publish the public key, set the identity hint, and continue the original action.
- Compose pages show a generic `Ready.` identity status node, but there is no explicit pre-action prepare control.
- Reaction surfaces have feedback nodes for clicked actions, but no standing identity-preparation affordance before the user clicks like or flag.

## Desired User Experience

For compose pages and thread/post reaction pages:

- OpenPGP bundle loading should be started or awaited during idle time before the first signed click.
- If the browser already has a keypair, the identity hint should be synced in the background.
- If the browser has a saved keypair whose public key may not be linked, the page may verify or finish publication only after explicit user action or when the user starts a signed action.
- If the browser has no keypair, the UI should expose a clear `Prepare browser identity` control near signed action areas.
- Clicking `Prepare browser identity` should prompt for a username, generate the keypair, publish the public key, set the identity hint, and update nearby controls to an identity-ready state.
- If a user skips preparation and clicks a signed action directly, the current inline flow should still show immediate status and continue the original action after identity setup succeeds.
- If preparation fails, the UI should leave authored content and the original action recoverable, with anonymous compose or manual `/account/key/` alternatives still visible.

## Integrity Rules

- User consent is required before generating and storing a browser keypair.
- A prepared identity is only considered ready after the server confirms `/api/link_identity` success or an already-existing fingerprint.
- `/api/set_identity_hint` remains only a hint; write endpoints must keep resolving viewer identity server-side.
- Failed identity preparation must not submit a signed compose, reaction, or moderation action.
- Debug/timing output must not include private keys, public key blocks, authored body text, or usernames beyond values already visible in the UI.
- Multiple tabs may race; the UI must tolerate a second tab linking the same fingerprint and handle the existing-id response as success.

## Implementation Slices

### Slice 5I-A: Identity Surface Discovery and Shared State

Goal:

- centralize identity-preparation status without changing action behavior

Work:

- add small helper functions in `browser_signing.js` for:
  - determining whether a page has signed-action surfaces
  - finding identity status nodes near compose and reaction roots
  - rendering `idle`, `loading_openpgp`, `needs_consent`, `generating`, `publishing`, `ready`, and `failed` states
  - enabling/disabling prepare buttons consistently
- expose a minimal test hook under `window.__forumBrowserIdentity` for status classification only
- keep existing `ensureReadyIdentity()` as the canonical path for actual key generation and linking

Acceptance:

- existing compose and reaction tests still pass
- unit/browser tests can classify saved-keypair, no-keypair, and published-fingerprint states without generating a real key

### Slice 5I-B: Safe OpenPGP Prewarm

Goal:

- remove avoidable first-click OpenPGP bundle latency without triggering identity creation

Work:

- on pages with signed-action surfaces, schedule idle prewarm that awaits `window.__forumOpenPgpLoader.ready`
- record browser performance marks for:
  - identity prewarm start
  - OpenPGP loader ready
  - OpenPGP loader failure
- if a browser keypair already exists, compute or verify the stored fingerprint during idle time only when the required OpenPGP API is available
- background-sync the identity hint for an already-known identity id
- never call `generateKey()` from prewarm
- never call `/api/link_identity` from prewarm unless a later slice explicitly opts into a consented prepare action

Acceptance:

- first signed action does not need to wait for initial OpenPGP script download when prewarm succeeds
- prewarm failure does not break anonymous compose, manual account linking, or direct signed-action setup
- tests prove no keypair is generated and no link request is sent during idle prewarm

### Slice 5I-C: Compose Prepare Affordance

Goal:

- let users prepare identity before writing or submitting a signed thread/reply

Work:

- add a `Prepare browser identity` button near the existing compose identity status on:
  - `templates/pages/compose_thread.php`
  - `templates/pages/compose_reply.php`
  - inline reply composer in `templates/pages/thread.php`
- hide or disable the button once the current browser identity is ready
- clicking the button calls the existing `ensureReadyIdentity()` flow with the compose username prompt
- after success:
  - set the identity hint
  - update the status to ready
  - populate hidden `author_identity_id` fields where applicable
  - keep form drafts untouched
- after failure:
  - keep draft fields untouched
  - show the friendly error and technical details toggle using the existing status rendering pattern
  - leave anonymous compose controls available

Acceptance:

- on a fresh compose page, clicking prepare produces visible progress before key generation starts
- successful preparation does not submit the form
- a subsequent signed submit skips username/key-generation work and proceeds to the existing optimistic submit path
- failure leaves the draft recoverable

### Slice 5I-D: Reaction Prepare Affordance

Goal:

- make identity setup visible before the first like or flag click

Work:

- add a compact identity-preparation control near thread reaction feedback and post reaction feedback where signed reaction buttons are rendered
- bind prepare controls from `thread_reactions.js` by delegating to `window.__forumBrowserIdentity.ensureReadyIdentity()`
- use `verifyPublishedIdentity: false` for the same low-latency behavior as current reaction setup unless measurement shows server verification is needed
- after success:
  - sync the identity hint
  - update feedback to identity-ready
  - leave reaction buttons enabled for the user to choose the actual tag
- after failure:
  - restore button availability
  - show a friendly error and technical details

Acceptance:

- preparing identity from a thread/reaction area does not apply a tag
- a subsequent reaction click skips username/key-generation work and uses the existing optimistic reaction flow
- failed preparation does not mark any reaction button as applied

### Slice 5I-E: Timing and Debug Integration

Goal:

- make identity-preparation latency visible in production debugging

Work:

- add or reuse browser performance marks for:
  - prepare click start
  - prewarm start
  - OpenPGP ready
  - username prompt returned
  - key generation start/end
  - `/api/link_identity` fetch start/response
  - `/api/set_identity_hint` fetch start/response
  - prepare complete
- include identity-preparation duration in the existing opt-in debug log shape used by compose and reactions
- avoid logging private key material, public key material, post bodies, or full username prompt values

Acceptance:

- `?debug_timing=1` or `localStorage.forum_debug_timing=1` can distinguish OpenPGP load, key generation, link identity, identity hint sync, and total preparation time
- debug payloads are browser-safe and do not include authored content or key material

### Slice 5I-F: Tests and Compatibility

Goal:

- protect no-JavaScript fallbacks and existing signed-action flows

Work:

- add Node-backed browser tests in `BrowserSigningNormalizationTest` for:
  - OpenPGP prewarm without key generation
  - prepare button success on compose
  - prepare button failure preserving drafts
  - prepare button success on reaction roots without applying tags
  - direct signed action still works when prepare was not clicked first
  - duplicate prepare clicks are ignored while one preparation is in flight
  - existing anonymous compose behavior remains unchanged
- add template smoke assertions that prepare controls render only on pages that load the identity scripts
- run source checks for changed JavaScript and PHP templates

Acceptance:

- all existing compose, reaction, and identity tests pass
- tests cover both explicit prepare and direct first-action identity setup
- non-JavaScript compose and account-key flows remain available

### Slice 5I-G: Quiet Prewarm and No Standalone Prepare UI

Goal:

- keep identity prewarm invisible during normal page load and remove the explicit `Prepare browser identity` controls

Problem:

- `/compose/thread` can show `Loading browser identity tools...` and leave the compose form looking like an identity workflow instead of a normal compose form
- thread pages can show persistent `Browser identity ready.` text even when the user has not clicked a signed action
- the explicit `Prepare browser identity` button adds UI weight without matching the desired product feel

Work:

- remove `Prepare browser identity` buttons from:
  - `templates/pages/compose_thread.php`
  - `templates/pages/compose_reply.php`
  - inline reply composer in `templates/pages/thread.php`
  - reaction areas in `templates/partials/thread_root_card.php`
  - reaction areas in `templates/partials/post_card.php`
- keep idle OpenPGP prewarm and saved-key fingerprint/hint sync, but make it silent:
  - do not call `setStatus()` or `renderIdentityPreparationState()` from idle prewarm
  - do not reveal `compose-identity-status`, `identity-prepare-status`, `thread-reaction-feedback`, or `post-reaction-feedback` for prewarm-only states
  - keep timing marks and debug logs opt-in only
- keep status messages only for user-initiated signed actions:
  - signed compose submit
  - signed reaction click
  - account-key page actions
- after successful inline identity setup for a signed action, clear or hide transient identity status once the action moves into its own visible pending state, such as `Creating thread...`, `Posting reply...`, or `Saving tag...`
- remove now-unused explicit prepare click handlers and tests, while preserving direct first-action setup behavior

Acceptance:

- fresh `/compose/thread` renders with no identity-loading or identity-ready message at rest
- fresh thread pages render with no persistent `Browser identity ready.` message at rest
- no `Prepare browser identity` button appears on compose, inline reply, thread root, or reply cards
- OpenPGP prewarm still runs silently on signed-action pages
- first signed submit/click still shows immediate identity-preparation progress if identity setup is needed
- anonymous compose submit paths remain visible and unchanged

### Slice 5I-H: Back Button Optimistic Compose Cleanup

Goal:

- prevent stale optimistic compose artifacts from remaining visible when the user returns with the browser Back button

Problem:

- after an optimistic thread creation, returning to `/compose/thread` with Back can show the pre-rendered pending/new post shell that was inserted before navigation
- stale pending compose UI makes the user think the old operation is still active or that the form contains server-confirmed content

Work:

- audit `pageshow` handling in `browser_signing.js` for bfcache restores after optimistic thread/reply navigation
- on `pageshow`, remove any optimistic-only DOM nodes created by:
  - `createPendingThreadShell()`
  - `createPendingReplyCard()`
- clear pending operation keys from form datasets and in-memory sets
- restore submit buttons and compose normalization state
- keep draft-clearing behavior from `forum_recently_cleared_compose_draft` intact for confirmed submissions
- do not clear authored fields unless the existing successful-submit draft-clear marker applies

Acceptance:

- after successful optimistic thread creation and Back navigation, `/compose/thread` does not show the previously inserted pending thread shell
- after successful optimistic inline reply and Back navigation, the thread page does not show the previously inserted pending reply card
- failed submissions still restore recoverable draft text
- successful submissions still clear drafts according to the existing recently-cleared draft marker
- tests cover `pageshow` cleanup for pending thread shells and pending reply cards

## Reconciliation Strategy

Initial recommendation:

- keep one browser-local identity preparation operation in flight per page
- do not persist preparation operations across reloads
- rely on existing local storage markers for saved keypair, fingerprint, and published fingerprint
- treat `/api/link_identity` already-existing fingerprint responses as successful reconciliation
- after preparation, let each original action run through its existing server-owned validation and optimistic reconciliation path

Rationale:

- identity preparation is a prerequisite, not a canonical content operation
- persisting an in-flight key-generation/link request across reloads risks confusing consent and recovery semantics
- the existing signed-action paths already know how to continue or fail once identity is ready

## Test Plan

Run after each implementation slice:

- `node --check public/assets/browser_signing.js`
- `node --check public/assets/thread_reactions.js`
- `php -l templates/pages/compose_thread.php`
- `php -l templates/pages/compose_reply.php`
- `php -l templates/pages/thread.php`
- `php tests/run.php`

Manual checks:

- fresh browser storage on `/compose/thread`
- fresh browser storage on a thread page with like/flag controls
- saved keypair but missing published-fingerprint marker
- HTTP or otherwise non-secure context fallback, if locally reproducible
- failed `/api/link_identity` response

## Suggested Commit Sequence

1. Add shared identity status/prewarm helpers and tests.
2. Add idle OpenPGP prewarm and debug marks.
3. Add compose prepare controls and success/failure tests.
4. Add reaction prepare controls and no-tag-side-effect tests.
5. Add final timing/debug coverage and run the full suite.

## Implementation Log

- 2026-06-16: Slice 5I-A complete - added browser identity preparation state classification, signed-surface discovery, shared status-node and prepare-button rendering helpers, and exported test hooks under `window.__forumBrowserIdentity`. Verification: `node --check public/assets/browser_signing.js`.
- 2026-06-16: Slice 5I-B complete - added idle identity prewarm on signed-action pages, OpenPGP loader timing marks, saved-key fingerprint completion, and background identity-hint sync without key generation or `/api/link_identity`. Verification: `node --check public/assets/browser_signing.js`.
- 2026-06-16: Slice 5I-C complete - added compose and inline-reply `Prepare browser identity` controls, bound them to the existing compose identity setup flow, kept form drafts untouched, populated hidden author identity fields on success, and preserved failure recovery. Verification: `node --check public/assets/browser_signing.js`; `php -l templates/pages/compose_thread.php`; `php -l templates/pages/compose_reply.php`; `php -l templates/pages/thread.php`.
- 2026-06-16: Slice 5I-D complete - added reaction-area `Prepare browser identity` controls for thread roots and replies, bound them through `thread_reactions.js` to identity preparation without applying tags, and kept failure feedback reversible. Verification: `node --check public/assets/browser_signing.js`; `node --check public/assets/thread_reactions.js`; `php -l templates/partials/thread_root_card.php`; `php -l templates/partials/post_card.php`.
- 2026-06-16: Slice 5I-E complete - threaded browser-safe timing marks through username prompt, key generation, identity linking, identity-hint sync, explicit prepare, direct compose setup, and direct reaction setup; expanded opt-in debug payloads with identity subphase durations. Verification: `node --check public/assets/browser_signing.js`; `node --check public/assets/thread_reactions.js`.
- 2026-06-16: Slice 5I-F complete - added browser-script coverage proving idle prewarm does not generate/link identities and reaction prepare does not apply tags, updated timing expectations for identity-hint marks, and kept legacy reaction fixtures compatible. Verification: `node --check public/assets/browser_signing.js`; `node --check public/assets/thread_reactions.js`; `php -l templates/pages/compose_thread.php`; `php -l templates/pages/compose_reply.php`; `php -l templates/pages/thread.php`; `php -l templates/partials/thread_root_card.php`; `php -l templates/partials/post_card.php`; `php tests/run.php`.
- 2026-06-16: Slice 5I-G complete - removed standalone `Prepare browser identity` controls, made compose identity status nodes hidden at rest, removed reaction prepare handlers, and kept idle OpenPGP prewarm silent while preserving user-initiated signed-action progress. Verification: `node --check public/assets/browser_signing.js`; `node --check public/assets/thread_reactions.js`; PHP syntax checks for changed templates and partials; `php tests/run.php`.

## Open Questions

- Should prepare controls be visible by default or only revealed when no linked browser identity is detected? Initial recommendation: show only when not ready, but reserve layout space so the compose status area does not shift.
- Should reaction pages verify server profile existence during explicit prepare? Initial recommendation: yes for explicit prepare if it does not add noticeable delay; keep direct reaction setup on the current lower-latency path unless production timings show stale identity hints are common.
- Should account-key page reuse the same prepare button label? Initial recommendation: no; that page already has more specific generate/load/link controls.
