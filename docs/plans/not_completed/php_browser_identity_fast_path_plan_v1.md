# PHP Browser Identity Fast Path Plan V1

This document records the implementation plan for reducing identity-preparation latency on compose and thread-reaction flows without changing the canonical identity architecture.

## Goal

Avoid an extra server round trip before the normal returning-user submit path.

The target user experience is:

- a browser that already generated and published its key should submit immediately
- brand-new browsers still publish the public key before the write can be accepted
- server validation remains authoritative
- stale browser-local state is handled by a retry or a clear fallback, not by weakening backend checks

## Current Behavior

The browser identity helper currently does this before compose submit or in-place thread reactions:

1. ensure a browser keypair exists
2. derive or load the key fingerprint
3. compare the fingerprint with `forum_pki_published_fingerprint`
4. if the fingerprint appears unpublished, call `/api/link_identity`
5. if the fingerprint appears published, call `/api/get_profile` to confirm the server still knows the identity
6. sync the `identity_hint` cookie through `/api/set_identity_hint`
7. continue the original post or reaction action

The `/api/get_profile` check is useful for recovery after repository/database resets, but it is latency on the common path.

## Decision Summary

### Fast-path rule

Treat this browser-local state as sufficient to skip identity preflight:

- `forum_pki_public_key` exists
- `forum_pki_private_key` exists
- current fingerprint can be derived or loaded
- `forum_pki_published_fingerprint` equals the current fingerprint

When those conditions are true:

- do not call `/api/get_profile` before submit
- do not call `/api/link_identity` before submit
- set the compose hidden `author_identity_id` directly from the fingerprint
- allow the existing write request to be the first server round trip

### Server authority

The backend write APIs must continue to reject unknown or invalid identities.

This plan does not change:

- canonical identity record formats
- public-key storage
- identity bootstrap semantics
- git commit behavior
- read-model refresh behavior
- approval rules

### Recovery rule

If the fast-path write fails because the identity is unknown, missing, or not resolvable:

1. call the existing public-key publish flow
2. retry the original action once when the publish succeeds
3. if retry fails, show the normal manual `/account/key/` fallback message

This preserves correctness while avoiding preflight latency for healthy returning browsers.

## Scope

In scope:

- `public/assets/browser_signing.js`
- compose thread and reply submits
- thread reaction identity preparation
- focused browser-signing normalization tests
- smoke coverage for stale fast-path recovery where practical

Out of scope:

- pending server-side write storage
- combining post creation and identity linking into one API request
- per-post detached signatures
- changing `/api/link_identity` response shape
- changing server-side identity validation rules

## Implementation Slices

### Slice 1: Browser Helper Contract

Add helper functions that separate "browser identity is locally ready" from "server identity has been verified".

Checklist:

- add a helper that returns the current local OpenPGP identity ID when the keypair and published fingerprint match
- keep fingerprint derivation behavior compatible with existing local storage
- keep `ensureReadyIdentity()` available for first-time and repair paths
- expose the fast-path helper through `window.__forumBrowserIdentity` for thread reactions

Expected outcome:

- compose and reaction code can choose a no-preflight path without duplicating fingerprint logic

### Slice 2: Compose Fast Path

Use the local-ready helper before submit.

Checklist:

- after normalization passes, check whether the local published identity is ready
- if ready, populate `author_identity_id`
- set a short status such as `Identity ready. Sending post...`
- submit without calling `ensureComposeIdentity()`
- preserve the existing full `ensureComposeIdentity()` path when the fast path is not ready

Expected outcome:

- returning browsers submit compose forms with no identity-preparation server round trip
- new browsers still prompt, generate, publish, and then submit as they do today

### Slice 3: Thread Reaction Fast Path

Use the same local-ready helper for in-place thread reactions.

Checklist:

- skip `ensureReadyIdentity()` when the helper reports a local published identity
- ensure the identity hint is already good enough for `/api/apply_thread_tag`
- if the tag request fails with an identity-resolution error, publish and retry once
- keep button disabling and feedback behavior unchanged

Expected outcome:

- returning browsers can apply a tag with a single `/api/apply_thread_tag` request in the healthy case

Open design note:

- `/api/apply_thread_tag` currently resolves the actor from the `identity_hint` cookie.
- If relying on an existing cookie proves brittle, the minimal follow-up is to let the reaction request include `author_identity_id` and have the server validate it against the same identity rules.

### Slice 4: Retry And Error Classification

Add narrow retry behavior for stale local state.

Checklist:

- classify server errors that mean "identity not known here"
- publish the public key only for those errors
- retry the original write at most once
- avoid retry loops when publication fails or the write fails for content/validation reasons
- keep the existing friendly bootstrap failure messages

Expected outcome:

- repository resets or stale local storage recover automatically when possible
- unrelated write failures remain visible and are not masked by identity retry logic

### Slice 5: Tests

Extend existing browser-signing and smoke tests.

Checklist:

- test that a matching `publishedFingerprint` skips `/api/get_profile`
- test that unpublished fingerprints still call `/api/link_identity`
- test compose hidden `author_identity_id` population on the fast path
- test stale fast-path failure publishes and retries once if feasible in the JS harness
- test that non-identity write failures do not trigger publication retry

Expected outcome:

- the optimization is covered at the branch points that matter for latency and recovery

## Acceptance Criteria

This work is complete when:

- returning compose users do not perform identity preflight before the post request
- returning thread-reaction users do not perform identity preflight before the tag request in the healthy case
- brand-new users still complete the existing username/key generation/public-key publication flow
- stale local state can recover by publishing and retrying once
- backend identity validation remains unchanged
- tests cover fast path, slow path, and stale-state recovery

## Deferred Enhancement

A later enhancement may store attempted writes server-side while waiting for public-key publication:

- initial write returns `pending_identity`
- server stores the attempted payload temporarily
- `/api/link_identity` completes identity bootstrap and replays the pending write

That design is intentionally deferred because it requires new pending-write storage, replay semantics, expiry rules, and a larger server-side contract change. The fast-path plan above keeps the current architecture and only changes when the browser chooses to preflight.
