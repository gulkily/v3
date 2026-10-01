# OpenPGP Asset and Identity Code Fixes

## Objective

Make browser OpenPGP work on both supported public origins without relying on
raw asset filenames being present in a particular deployment package. A failed
OpenPGP load must never ask a user for a username, and concurrent identity
requests must not create duplicate prompts or key-generation attempts.

This is a code-fix inventory, not an implementation log. The immediate
production recovery remains deploying the missing HTTP v5 bundle at
`/assets/openpgp.v5.11.3.min.js`.

## Implementation status

- [x] Server-emitted fingerprinted runtime asset paths (working tree; deploy pending).
- [x] Safe raw public-asset fallback through `FrontController` (working tree; deploy pending).
- [x] Fingerprint-aware OpenPGP and lazy-compose loaders (working tree; deploy pending).
- [x] OpenPGP-before-prompt and one in-flight identity preparation (working tree; deploy pending).
- [x] Focused regression coverage for the completed work.
- [ ] Deploy and verify both origins on `zenmemes.com`.

## 1. Emit runtime asset URLs from PHP

**Problem:** `public/assets/openpgp_loader.js` hard-codes raw paths, while
pages emit fingerprinted script URLs and static releases copy only declared
fingerprinted dependencies.

**Change:** Have server rendering emit a small, JSON-safe asset configuration
before the loader executes. It must contain fingerprinted current URLs for:

- OpenPGP v6;
- OpenPGP v5;
- `openpgp_loader.js`;
- `browser_signing.js`.

The values must come from the same `TemplateRenderer::assetPath()`/
`AssetFingerprint` path used by normal script tags. Do not calculate hashes in
browser JavaScript.

**Acceptance criteria:** A rendered HTTP page exposes a v5 fingerprinted URL;
a rendered HTTPS page exposes a v6 fingerprinted URL; changing either source
asset changes the emitted URL.

## 2. Add a safe application fallback for raw public assets

**Problem:** On the managed host, direct web-server configuration is not
available. If the host does not map a physical raw asset into the public URL
space, the checked-in `.htaccess` routes the request to `index.php`. The
current `FrontController` serves validated fingerprinted assets only, so the
raw request becomes a 404 even when the source file exists under the
application's `public/assets/` directory.

**Change:** In `FrontController`, add a narrowly validated GET/HEAD fallback
for raw `/assets/<filename>` requests that have reached PHP. Resolve only a
single, flat asset filename under the application `public/assets` directory;
reject traversal, nested paths, and non-files. Reuse `sendAsset()` for headers
and body delivery. Existing physical-file serving through `.htaccess` remains
the fast path.

**Acceptance criteria:** When an asset request reaches PHP, both
`/assets/openpgp.v5.11.3.min.js` and `/assets/openpgp.min.js` return their
source JavaScript bodies. Invalid paths cannot read outside `public/assets`.

This is the immediate code-level protection for a host where Apache settings
cannot be changed. It should remain safe even after the longer-term
fingerprinted-runtime contract is complete.

## 3. Make the OpenPGP loader consume emitted URLs

**Problem:** The loader currently selects the correct version but requests
`/assets/openpgp*.min.js` raw filenames.

**Change:** Keep the `window.isSecureContext` selection logic, but select the
corresponding emitted URL from the runtime asset configuration. Fail with a
clear configuration error if the selected URL is absent.

**Acceptance criteria:** HTTP selects v5, HTTPS selects v6, and neither code
path contains a hard-coded raw OpenPGP bundle filename.

## 4. Declare runtime asset dependencies to static releases

**Problem:** `StaticArtifactBuilder` and `check_static_artifacts.php` discover
only fingerprinted URLs written in HTML. They cannot see URLs that a script
will create later.

**Change:** Add an explicit runtime-dependency manifest or equivalent API to
the static artifact builder. For every page that can execute browser identity
code, it must copy the selected OpenPGP bundles and any loader/signing asset
needed dynamically. The check script must validate those declared files.

**Acceptance criteria:** A clean `build-static` release contains the declared
fingerprinted v5 and v6 bundle files. The validation command fails when either
file is removed. `build-static --shared-only` retains or regenerates them.

## 5. Fix lazy compose asset loading

**Problem:** `public/assets/lazy_compose_signing.js` dynamically appends raw
`/assets/openpgp_loader.js` and `/assets/browser_signing.js` URLs.

**Change:** Read the same server-emitted configuration and append the
fingerprinted loader and browser-signing URLs. Keep its existing one-promise
deduplication behavior.

**Acceptance criteria:** The board/compact composer can lazy-load signing from
a fresh static release without relying on raw filenames.

## 6. Check loader availability before prompting for a username

**Problem:** `ensureReadyIdentity()` prompts first, then `generateBrowserKey()`
calls `ensureOpenPgpApi()`. If bundle loading fails, the entered username and
keypair are never stored, so the next identity attempt prompts again.

**Change:** Move the OpenPGP readiness check ahead of `promptForUsername()`.
If readiness fails, render the existing actionable unavailable message and
anonymous-post option without opening a username prompt.

**Acceptance criteria:** Simulating a v5 or v6 script-load failure produces
zero username prompts, preserves the draft, and leaves anonymous posting
available.

## 7. Deduplicate identity preparation

**Problem:** `ensureReadyIdentity()` has no shared in-flight promise. Two
callers can both observe that no keypair exists and independently prompt the
user.

**Change:** Add one module-scoped identity-preparation promise/lock. Compose,
reactions, invitations, account setup, and private-site authentication must
await the same work. Clear the promise after success or a handled failure.

**Acceptance criteria:** Two simultaneous identity requests display one
prompt, execute one key-generation call, and receive the same resolved or
rejected result.

## 8. Make failure state deliberate and recoverable

**Problem:** A loader rejection is cached by the loader, but each new identity
attempt can still reach the username-prompt branch before it discovers the
cached failure.

**Change:** Expose a loader state (`idle`, `loading`, `ready`, `failed`) and
have identity UI consult it before asking for input. On `failed`, show the
technical detail plus the anonymous option; do not automatically retry until a
page reload or an explicit retry control after the asset path is repaired.

**Acceptance criteria:** Repeating the same action on a broken page produces
no prompt loop and no extra script fetches.

## 9. Preserve raw URLs only as a compatibility bridge

**Problem:** Direct raw URLs remain useful for emergency recovery and old
cached pages, but they are not sufficient as the permanent runtime contract.

**Change:** During migration, deploy raw aliases for both OpenPGP bundles and
the current fingerprinted files. After all runtime callers use emitted
fingerprints and release validation covers them, decide whether raw aliases
remain documented compatibility paths or are retired with a migration window.

**Acceptance criteria:** No supported current page depends on raw asset URLs;
old cached pages have a documented compatibility/expiry policy.

## 10. Required tests

- Loader chooses v5 on insecure public contexts and v6 on secure contexts.
- Rendered runtime configuration supplies current fingerprinted URLs.
- A raw asset request routed through `index.php` serves only an allowed file
  under `public/assets` and rejects traversal/nested-path attempts.
- Complete and shared-only static releases contain every declared runtime
  dependency.
- Static validation fails for a missing declared runtime dependency.
- Lazy compose loads fingerprinted loader/signing assets exactly once.
- Failed v5/v6 load asks for zero usernames, retains a draft, and permits
  explicit anonymous submission.
- Two concurrent identity requests result in one prompt and one keypair.
- Successful first setup persists the username/keypair; the next same-origin
  signed action does not prompt.
- A production smoke check fetches the actual HTTP v5 and HTTPS v6 URLs and
  verifies status, JavaScript content type, and non-error body.
