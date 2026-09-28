# Offline Support Feature Assessment

## Conclusion

The offline-support stub is feasible, but it is a substantial feature rather
than a small service-worker addition. It is medium-to-high complexity and has
six major workstreams.

The codebase has strong foundations for the reading portion: it already builds
complete static HTML releases, fingerprints immutable assets, and routes
content writes through a limited set of APIs. The difficult part is preserving
the signed-content model while allowing a browser to create durable work with
no network connection.

## Intended Product Scope

The most accurate initial scope is:

> Offline reading plus queued signed threads, replies, and reactions; sync and
> server validation resume when connectivity returns.

This meets the stub's core intent:

- A visitor who has used zenmemes.com can return while offline.
- Previously cached thread listings, threads, and comments remain readable.
- Voting/tagging, commenting, and replying work as locally queued actions.
- The UI makes queued and later-synchronized work explicit.

"All features offline" should not be literal. Server-side analysis,
approvals/invitations, administration, and actions requiring a new or current
server authorization cannot complete offline. They can be unavailable or
queued only where the eventual server-side operation is safe and well-defined.

## Major Pieces

### 1. Offline application shell and content cache

Add a service worker, web app manifest, and versioned caches. Cache the
application shell and immutable fingerprinted assets, then cache either a
defined content snapshot or pages as the visitor reaches them.

The requirement that a user can come back after a single prior visit means the
feature needs an explicit policy for how much content to retain. Caching only
the current page will not support broad offline browsing. A full public static
release is a promising baseline where download size is acceptable; otherwise
use on-demand caching plus a clear indication of which content is available.

### 2. Offline navigation and reading behavior

Define navigation fallbacks for listings, threads, posts, profiles, tags,
pagination, and any JavaScript-enhanced views. Cached pages should open
normally and distinguish cached/stale content from a current online view.

The existing static-artifact machinery is a favorable starting point:

- `src/ForumRewrite/Host/StaticArtifactBuilder.php` renders shared pages plus
  visible tag, thread, post, and profile routes.
- `src/ForumRewrite/Host/FrontController.php` already serves fingerprinted
  assets with immutable cache headers and resolves public static artifacts.

The browser currently does not retain an entire release, so the service worker
must provide that browser-side retention layer.

### 3. Durable browser-side mutation outbox

Store pending writes in IndexedDB rather than only in localStorage. Every
queued operation should include a stable operation ID, operation type and
payload, author identity, creation time, dependency references, retry state,
server result, and any human-readable failure detail.

LocalStorage is already appropriate for compose drafts, but it is not a
reliable enough transactional queue for durable offline mutations.

### 4. Offline compose and signing protocol

This is the principal design problem. Current signed post creation is an
online two-stage operation:

1. The server prepares a canonical record containing a post ID, timestamp,
   path, and expiring prepare token.
2. The browser signs that exact record and submits it for server verification
   and persistence.

The browser key is local, so it can sign offline. However, it cannot obtain a
server-generated canonical record or prepare token while disconnected. The
protocol needs an offline-safe alternative, such as client-generated IDs and
a signed mutation envelope that the server validates and commits later, or a
carefully designed advance-reservation scheme.

The design must preserve canonical-record validation and detached-signature
verification rather than bypassing them for offline work.

Relevant current implementation:

- `public/assets/browser_signing.js`
- `src/ForumRewrite/Http/WritePostAndIdentityApiController.php`
- `src/ForumRewrite/Write/LocalWriteService.php`

### 5. Reconnect, ordering, conflicts, and idempotency

When connectivity returns, the client must replay queued writes in dependency
order: any identity prerequisite, a new thread, replies to that thread, then
reactions. The server needs stable client operation IDs and idempotent
acceptance so a retry never creates duplicate records.

The sync design also needs explicit outcomes for cases such as:

- a reply target was deleted, hidden, or otherwise no longer valid;
- authorization changed while the browser was offline;
- an identity changed after operations were queued;
- an already-applied reaction is replayed;
- a queued post is malformed or its signature is rejected.

### 6. User state, recovery controls, and verification

The UI should make network and queue state visible: Offline, Queued, Syncing,
Sent, and Needs attention. Pending posts and reactions need a way to retry,
inspect a failure, or discard an operation. A background sync may improve
delivery, but the application must also synchronize reliably when it is next
opened online because background sync availability varies by browser.

Testing needs real offline transitions and durable-queue scenarios, including
reload while queued, browser restart, duplicate replay, cache upgrades,
outdated content, identity changes, rejected writes, and successful recovery.

## Recommended Delivery Sequence

### Release 1: Offline reading foundation

- Service worker and web manifest.
- Versioned asset and HTML cache.
- Offline navigation fallback and stale-content indicator.
- Persistent compose drafts and a global connectivity indicator.

This is a valuable, independently releasable PWA/read-only milestone.

### Release 2: Queued writes

- IndexedDB outbox and queue UI.
- Idempotent sync API and server-side operation ledger.
- Offline-safe signed thread/reply protocol.
- Queued reactions/tags, dependency ordering, recovery controls, and end-to-end
  offline tests.

## Feasibility and Risk

Offline reading is relatively straightforward because static HTML and
fingerprinted assets already exist. Queued reactions are moderately difficult
but bounded. Offline signed posting is the high-risk component because it
requires a deliberate evolution of the prepare/sign/finalize protocol.

The feature is a good product fit for the forum's durable-record architecture.
It should begin with the reading milestone rather than attempting a single
large implementation that treats every server-backed feature as offline-capable.
